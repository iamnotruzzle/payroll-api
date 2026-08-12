<?php

namespace App\Services\Schedule;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleUnit;
use App\Models\Schedule\Schedulev2LegacyMap;
use App\Models\Schedule\Schedulev2SyncRun;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class Schedulev2SyncService
{
    public function __construct(
        private readonly ScheduleDivisionService $divisionService
    ) {}

    /**
     * Pull approved schedulev2 employee_schedules into payroll_scheduler.
     *
     * Source statuses (schedulev2): P=pending, S=submitted, C=checked, R=recommended, A=approved, D=deleted.
     * Only A is imported. Non-approved / deleted source rows are not fetched; previously imported
     * locked local assignments are left alone (never auto-deleted — avoids payroll DTR side effects).
     *
     * Every run re-compares mapped rows (by legacy_emp_sched_id) and updates in place when changed;
     * identical rows count as unchanged. Months that receive imports are set locked without
     * calling ScheduleLockService / DTR.
     *
     * @return array{
     *   dry_run: bool,
     *   batch_key: string,
     *   from: string,
     *   to: string,
     *   source_count: int,
     *   created: int,
     *   updated: int,
     *   unchanged: int,
     *   skipped: int,
     *   skip_reasons: array{
     *     skipped_oc_or_empty_label: int,
     *     skipped_no_employee: int,
     *     skipped_department_filter: int,
     *     skipped_division_filter: int,
     *     skipped_no_shift_code: int
     *   },
     *   accounted: int,
     *   months_touched: int,
     *   locked_months: int,
     *   errors: list<string>,
     *   connection_ok: bool,
     *   message?: string
     * }
     */
    public function sync(
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $dryRun = true,
        ?int $departmentId = null,
        ?int $divisionId = null,
        ?string $empId = null,
        ?int $limit = null,
        ?string $batchKey = null,
    ): array {
        $batchKey = $batchKey ?: ('sv2-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)));
        $connection = (string) config('schedule.schedulev2.connection', 'schedulev2');

        $stats = [
            'dry_run' => $dryRun,
            'batch_key' => $batchKey,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'source_count' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'skip_reasons' => [
                'skipped_oc_or_empty_label' => 0,
                'skipped_no_employee' => 0,
                'skipped_department_filter' => 0,
                'skipped_division_filter' => 0,
                'skipped_no_shift_code' => 0,
            ],
            'accounted' => 0,
            'months_touched' => 0,
            'locked_months' => 0,
            'errors' => [],
            'connection_ok' => false,
        ];

        try {
            $this->assertConnection($connection);
            $stats['connection_ok'] = true;
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            $stats['message'] = $e->getMessage();

            if (! $dryRun) {
                $this->persistRun($batchKey, $dryRun, $from, $to, $departmentId, $divisionId, $empId, $limit, 'failed', $stats);
            }

            return $stats;
        }

        $run = null;
        if (! $dryRun) {
            $run = $this->persistRun($batchKey, $dryRun, $from, $to, $departmentId, $divisionId, $empId, $limit, 'running', $stats);
        }

        try {
            $rows = $this->fetchSourceRows($connection, $from, $to, $empId, $limit);
            $stats['source_count'] = $rows->count();

            $empDepartments = $this->resolveEmployeeDepartments($rows->pluck('emp_id')->unique()->filter()->values()->all());
            $locations = $this->fetchLocations($connection, $rows->pluck('duty_location')->unique()->filter()->values()->all());
            $departmentIndex = $this->buildDepartmentIndex();
            $unitsByName = $this->buildScheduleUnitNameIndex();
            $divisionDepartmentIds = $divisionId !== null
                ? array_fill_keys($this->divisionService->departmentIdsForDivision($divisionId), true)
                : [];
            $skipLabels = array_map('strtoupper', config('schedule.schedulev2.skip_shift_labels', ['OC']));

            $monthKeysTouched = [];

            foreach ($rows as $row) {
                $sourceEmpId = (string) $row->emp_id;
                $dutyDate = CarbonImmutable::parse($row->duty_date)->startOfDay();
                $shiftLabel = trim((string) $row->shift_label);

                if ($shiftLabel === '' || in_array(strtoupper($shiftLabel), $skipLabels, true)) {
                    $this->bumpSkip($stats, 'skipped_oc_or_empty_label');

                    continue;
                }

                $homeDepartmentId = $empDepartments[$sourceEmpId] ?? null;
                $location = $this->locationForDuty($row->duty_location, $locations);
                $resolvedDepartmentId = $this->resolveTargetDepartmentId(
                    $location,
                    $homeDepartmentId,
                    $departmentIndex,
                    $unitsByName
                );

                if ($resolvedDepartmentId === null) {
                    $this->bumpSkip($stats, 'skipped_no_employee');
                    $stats['errors'][] = "No HRIS department for emp_id={$sourceEmpId} (source emp_sched_id={$row->id})";

                    continue;
                }

                // Include when home HRIS dept OR duty-location-resolved dept matches --department=
                // (floaters into the filtered dept are included even if HRIS home differs).
                if ($departmentId !== null) {
                    $homeMatches = $homeDepartmentId !== null && (int) $homeDepartmentId === $departmentId;
                    $resolvedMatches = (int) $resolvedDepartmentId === $departmentId;
                    if (! $homeMatches && ! $resolvedMatches) {
                        $this->bumpSkip($stats, 'skipped_department_filter');

                        continue;
                    }
                }

                if ($divisionId !== null) {
                    $homeInDivision = $homeDepartmentId !== null && isset($divisionDepartmentIds[(int) $homeDepartmentId]);
                    $resolvedInDivision = isset($divisionDepartmentIds[(int) $resolvedDepartmentId]);
                    $locationInDivision = $location !== null
                        && isset($location->division_id)
                        && (int) $location->division_id === $divisionId;
                    if (! $homeInDivision && ! $resolvedInDivision && ! $locationInDivision) {
                        $this->bumpSkip($stats, 'skipped_division_filter');

                        continue;
                    }
                }

                $shiftCode = $this->resolveShiftCode(
                    $shiftLabel,
                    (int) $resolvedDepartmentId,
                    $row,
                    $dryRun,
                    $stats
                );
                if ($shiftCode === null) {
                    $this->bumpSkip($stats, 'skipped_no_shift_code');

                    continue;
                }

                $unitId = $this->resolveUnitId(
                    (int) $resolvedDepartmentId,
                    $row->duty_location,
                    $location,
                    (string) ($row->location_type ?? ''),
                    $dryRun,
                    $stats,
                    $unitsByName
                );

                $year = (int) $dutyDate->year;
                $month = (int) $dutyDate->month;
                $monthKey = "{$resolvedDepartmentId}:{$year}:{$month}";
                $monthKeysTouched[$monthKey] = [
                    'department_id' => (int) $resolvedDepartmentId,
                    'year' => $year,
                    'month' => $month,
                ];

                $existing = ScheduleAssignment::query()
                    ->where('legacy_emp_sched_id', (int) $row->id)
                    ->first();

                if (! $existing) {
                    $existing = ScheduleAssignment::query()
                        ->whereHas('monthlySchedule', function ($query) use ($resolvedDepartmentId, $year, $month) {
                            $query->where('department_id', $resolvedDepartmentId)
                                ->where('year', $year)
                                ->where('month', $month);
                        })
                        ->where('employee_id', $sourceEmpId)
                        ->whereDate('schedule_date', $dutyDate->toDateString())
                        ->first();
                }

                if ($dryRun) {
                    if (! $existing) {
                        $stats['created']++;
                    } elseif ($this->assignmentDiffersFromSource(
                        $existing,
                        $sourceEmpId,
                        $dutyDate->toDateString(),
                        (int) $shiftCode->id,
                        $unitId,
                        (bool) ($row->temporary_floater ?? false),
                        $this->buildNotes($row),
                        (int) $row->id,
                        expectedDepartmentId: (int) $resolvedDepartmentId,
                        expectedYear: $year,
                        expectedMonth: $month,
                    )) {
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }

                    continue;
                }

                // Approved source rows always land under a locked month (no ScheduleLockService / DTR).
                $monthly = MonthlySchedule::query()->firstOrCreate(
                    [
                        'department_id' => (int) $resolvedDepartmentId,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'status' => MonthlySchedule::STATUS_LOCKED,
                        'generated_by' => 'system:schedulev2-sync',
                        'generated_at' => now(),
                        'locked_by' => 'system:schedulev2-sync',
                        'locked_at' => now(),
                        'approved_by' => 'system:schedulev2-sync',
                        'approved_at' => now(),
                    ]
                );

                $payload = [
                    'monthly_schedule_id' => $monthly->id,
                    'employee_id' => $sourceEmpId,
                    'schedule_date' => $dutyDate->toDateString(),
                    'shift_code_id' => $shiftCode->id,
                    'unit_id' => $unitId,
                    'is_temporary_floater' => (bool) ($row->temporary_floater ?? false),
                    'source' => 'schedulev2_sync',
                    'notes' => $this->buildNotes($row),
                    'legacy_emp_sched_id' => (int) $row->id,
                ];

                $assignment = $existing;

                if ($assignment) {
                    if ($this->assignmentNeedsUpdate($assignment, $payload)) {
                        $assignment->fill($payload);
                        $assignment->save();
                        $stats['updated']++;
                    } else {
                        $stats['unchanged']++;
                    }
                    $targetId = $assignment->id;
                } else {
                    $assignment = ScheduleAssignment::query()->create($payload);
                    $stats['created']++;
                    $targetId = $assignment->id;
                }

                Schedulev2LegacyMap::query()->updateOrCreate(
                    [
                        'source_table' => 'employee_schedules',
                        'source_key' => (string) $row->id,
                    ],
                    [
                        'target_table' => 'schedule_assignments',
                        'target_id' => $targetId,
                        'emp_id' => $sourceEmpId,
                        'checksum' => sha1(json_encode($payload)),
                        'sync_run_id' => $run?->id,
                    ]
                );
            }

            $stats['months_touched'] = count($monthKeysTouched);

            if (! $dryRun) {
                foreach ($monthKeysTouched as $monthKey => $meta) {
                    $monthly = MonthlySchedule::query()
                        ->where('department_id', $meta['department_id'])
                        ->where('year', $meta['year'])
                        ->where('month', $meta['month'])
                        ->first();

                    if (! $monthly) {
                        continue;
                    }

                    // Keep imported approved months locked WITHOUT invoking ScheduleLockService (no DTR).
                    if ($monthly->status !== MonthlySchedule::STATUS_LOCKED) {
                        $monthly->forceFill([
                            'status' => MonthlySchedule::STATUS_LOCKED,
                            'locked_by' => 'system:schedulev2-sync',
                            'locked_at' => now(),
                            'approved_by' => $monthly->approved_by ?: 'system:schedulev2-sync',
                            'approved_at' => $monthly->approved_at ?: now(),
                        ])->save();
                        $stats['locked_months']++;
                    }
                }
            } else {
                $stats['locked_months'] = count($monthKeysTouched);
            }
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
        }

        $stats['accounted'] = (int) $stats['created']
            + (int) $stats['updated']
            + (int) $stats['unchanged']
            + (int) $stats['skipped'];

        if ($run) {
            $run->update([
                'status' => $stats['errors'] === [] ? 'completed' : 'completed_with_errors',
                'stats' => $stats,
                'errors' => $stats['errors'],
                'finished_at' => now(),
            ]);
        }

        return $stats;
    }

    public function assertConnection(string $connection): void
    {
        try {
            DB::connection($connection)->getPdo();
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Cannot connect to NDOS (Nursing Division Online Scheduling) via connection "'.$connection.'". '
                .'Set DB_HOST_SCHEDULEV2 / DB_DATABASE_SCHEDULEV2 / DB_USERNAME_SCHEDULEV2 / DB_PASSWORD_SCHEDULEV2 '
                .'in .env (see .env.example). '.$e->getMessage(),
                previous: $e
            );
        }

        foreach (['employee_schedules', 'schedules', 'shifts'] as $table) {
            if (! Schema::connection($connection)->hasTable($table)) {
                throw new RuntimeException(
                    "NDOS connection works but table `{$table}` is missing. "
                    .'Confirm DB_DATABASE_SCHEDULEV2 points at the NDOS (schedulev2) app database.'
                );
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchSourceRows(
        string $connection,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $empId,
        ?int $limit
    ) {
        $hasTemporaryFloater = Schema::connection($connection)->hasColumn('employee_schedules', 'temporary_floater');

        // schedulev2 employee_schedules.status: P pending, S submitted, C checked, R recommended, A approved, D deleted.
        // Only pull approved (A). Drafts/pending/etc. are excluded at source so they do not inflate source_count.
        $approvedStatuses = config('schedule.schedulev2.approved_statuses', ['A']);
        $approvedStatuses = array_values(array_filter(array_map(
            static fn ($status) => strtoupper(trim((string) $status)),
            is_array($approvedStatuses) ? $approvedStatuses : ['A']
        )));
        if ($approvedStatuses === []) {
            $approvedStatuses = ['A'];
        }

        $query = DB::connection($connection)
            ->table('employee_schedules as es')
            ->join('schedules as s', 's.id', '=', 'es.schedule_id')
            ->join('shifts as sh', 'sh.id', '=', 's.shift_id')
            ->whereIn('es.status', $approvedStatuses)
            ->whereDate('s.date_start', '>=', $from->toDateString())
            ->whereDate('s.date_start', '<=', $to->toDateString())
            ->when($empId, fn ($q) => $q->where('es.emp_id', $empId))
            ->orderBy('s.date_start')
            ->orderBy('es.id')
            ->select([
                'es.id',
                'es.emp_id',
                'es.status',
                'es.duty_location',
                'es.clinic',
                'es.location_type',
                'es.created_by',
                'es.approved_by',
                DB::raw('DATE(s.date_start) as duty_date'),
                's.shift_id',
                'sh.shift_label',
                'sh.shift_desc',
                'sh.time_start',
                'sh.time_end',
                'sh.type as shift_type',
            ]);

        if ($hasTemporaryFloater) {
            $query->addSelect('es.temporary_floater');
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  list<string>  $empIds
     * @return array<string, int>
     */
    private function resolveEmployeeDepartments(array $empIds): array
    {
        if ($empIds === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('department_id')
            ->pluck('department_id', 'emp_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int|string>  $locationIds
     * @return array<int, object{id:int,name:string,division_id:?int,department_id:?int}>
     */
    private function fetchLocations(string $connection, array $locationIds): array
    {
        $ids = collect($locationIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        if ($ids === [] || ! Schema::connection($connection)->hasTable('locations')) {
            return [];
        }

        $hasDepartmentId = Schema::connection($connection)->hasColumn('locations', 'department_id');
        $columns = ['id', 'name', 'division_id'];
        if ($hasDepartmentId) {
            $columns[] = 'department_id';
        }

        return DB::connection($connection)
            ->table('locations')
            ->whereIn('id', $ids)
            ->get($columns)
            ->mapWithKeys(function ($row) use ($hasDepartmentId) {
                $id = (int) $row->id;

                return [
                    $id => (object) [
                        'id' => $id,
                        'name' => (string) ($row->name ?? ''),
                        'division_id' => isset($row->division_id) ? (int) $row->division_id : null,
                        'department_id' => ($hasDepartmentId && isset($row->department_id) && (int) $row->department_id > 0)
                            ? (int) $row->department_id
                            : null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array{
     *   by_name: array<string, int>,
     *   by_name_cno: array<string, int>,
     *   division_by_id: array<int, int>
     * }
     */
    private function buildDepartmentIndex(): array
    {
        $byName = [];
        $byNameCno = [];
        $divisionById = [];
        $cnoDivisionId = $this->divisionService->cnoDivisionId();

        foreach (Department::query()->get(['department_id', 'department', 'division_id']) as $department) {
            $departmentId = (int) $department->department_id;
            $divisionId = $department->division_id !== null ? (int) $department->division_id : null;
            if ($divisionId !== null) {
                $divisionById[$departmentId] = $divisionId;
            }

            $key = $this->normalizeNameKey((string) $department->department);
            if ($key === '') {
                continue;
            }
            // First wins; HRIS department names are expected unique enough for sync.
            $byName[$key] ??= $departmentId;
            if ($divisionId === $cnoDivisionId) {
                $byNameCno[$key] ??= $departmentId;
            }
        }

        return [
            'by_name' => $byName,
            'by_name_cno' => $byNameCno,
            'division_by_id' => $divisionById,
        ];
    }

    /**
     * @return array<string, array{department_id:int,unit_id:int}> uppercase unit name/code → mapping
     */
    private function buildScheduleUnitNameIndex(): array
    {
        $index = [];
        foreach (ScheduleUnit::query()->get(['id', 'department_id', 'name', 'code']) as $unit) {
            foreach ([(string) $unit->name, (string) $unit->code] as $label) {
                $key = $this->normalizeNameKey($label);
                if ($key === '') {
                    continue;
                }
                $index[$key] ??= [
                    'department_id' => (int) $unit->department_id,
                    'unit_id' => (int) $unit->id,
                ];
            }
        }

        return $index;
    }

    /**
     * @param  array<int, object>  $locations
     */
    private function locationForDuty(mixed $dutyLocation, array $locations): ?object
    {
        $locationId = (int) $dutyLocation;
        if ($locationId <= 0) {
            return null;
        }

        return $locations[$locationId] ?? null;
    }

    /**
     * Resolve the schedule department for placement (MonthlySchedule.department_id).
     *
     * Priority:
     * 1. schedulev2 locations.department_id when present
     * 2. When location.division_id is CNO, prefer name match among CNO HRIS departments
     * 3. location name exact match to HRIS tbl_department.department
     * 4. location name/code match to an existing ScheduleUnit → that unit's department_id
     * 5. employee HRIS home department_id
     *
     * @param  array{
     *   by_name: array<string, int>,
     *   by_name_cno: array<string, int>,
     *   division_by_id: array<int, int>
     * }  $departmentIndex
     * @param  array<string, array{department_id:int,unit_id:int}>  $unitsByName
     */
    private function resolveTargetDepartmentId(
        ?object $location,
        ?int $homeDepartmentId,
        array $departmentIndex,
        array $unitsByName
    ): ?int {
        $departmentsByName = $departmentIndex['by_name'] ?? [];
        $cnoDepartmentsByName = $departmentIndex['by_name_cno'] ?? [];
        $cnoDivisionId = $this->divisionService->cnoDivisionId();

        if ($location !== null) {
            if (! empty($location->department_id) && (int) $location->department_id > 0) {
                return (int) $location->department_id;
            }

            $nameKey = $this->normalizeNameKey((string) ($location->name ?? ''));
            $preferCno = isset($location->division_id)
                && (int) $location->division_id === $cnoDivisionId;

            if ($preferCno && $nameKey !== '' && isset($cnoDepartmentsByName[$nameKey])) {
                return (int) $cnoDepartmentsByName[$nameKey];
            }

            if ($nameKey !== '' && isset($departmentsByName[$nameKey])) {
                return (int) $departmentsByName[$nameKey];
            }

            if ($nameKey !== '' && isset($unitsByName[$nameKey])) {
                return (int) $unitsByName[$nameKey]['department_id'];
            }

            // Nursing floaters: if location is CNO-scoped but unnamed match failed, keep home
            // when home is also CNO so placement stays in Nursing Service.
            if (
                $preferCno
                && $homeDepartmentId !== null
                && isset($departmentIndex['division_by_id'][$homeDepartmentId])
                && (int) $departmentIndex['division_by_id'][$homeDepartmentId] === $cnoDivisionId
            ) {
                return $homeDepartmentId;
            }
        }

        return $homeDepartmentId !== null && $homeDepartmentId > 0 ? $homeDepartmentId : null;
    }

    private function normalizeNameKey(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?: ''));
    }

    private function resolveShiftCode(
        string $shiftLabel,
        int $departmentId,
        object $row,
        bool $dryRun,
        array &$stats
    ): ?ShiftCode {
        $code = Str::upper(Str::limit(preg_replace('/\s+/', '', $shiftLabel) ?: $shiftLabel, 20, ''));

        $existing = ShiftCode::query()
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->whereRaw('UPPER(code) = ?', [$code])
            ->orderByRaw('CASE WHEN department_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        if ($existing) {
            return $existing;
        }

        if (! config('schedule.schedulev2.create_missing_shift_codes', true)) {
            $stats['errors'][] = "Missing shift code for label \"{$shiftLabel}\" (dept {$departmentId})";

            return null;
        }

        if ($dryRun) {
            return new ShiftCode([
                'id' => 0,
                'code' => $code,
                'department_id' => $departmentId,
                'name' => (string) ($row->shift_desc ?: $shiftLabel),
            ]);
        }

        $isWork = ! in_array(strtoupper((string) ($row->shift_type ?? '')), ['O', 'L', 'H', 'OC'], true);

        return ShiftCode::query()->create([
            'code' => $code,
            'department_id' => $departmentId,
            'name' => (string) ($row->shift_desc ?: $shiftLabel),
            'start_time' => $row->time_start ?: null,
            'end_time' => $row->time_end ?: null,
            'end_day_offset' => 0,
            'work_hours' => null,
            'is_work_shift' => $isWork,
            'is_night_shift' => false,
            'is_leave_code' => strtoupper((string) ($row->shift_type ?? '')) === 'L',
            'is_active' => true,
            'description' => 'Imported from schedulev2 shift_label='.$shiftLabel,
        ]);
    }

    /**
     * Map duty location → ScheduleUnit under the resolved schedule department.
     * Prefers an existing unit in that department; falls back to a same-named unit
     * already indexed from any department, then creates under the resolved department.
     *
     * @param  array<string, array{department_id:int,unit_id:int}>  $unitsByName
     */
    private function resolveUnitId(
        int $departmentId,
        mixed $dutyLocation,
        ?object $location,
        string $locationType,
        bool $dryRun,
        array &$stats,
        array &$unitsByName
    ): ?int {
        $locationId = (int) $dutyLocation;
        if ($locationId <= 0) {
            return null;
        }

        $name = trim((string) ($location->name ?? ''));
        if ($name === '') {
            return null;
        }

        $code = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?: ('LOC'.$locationId), 40, ''));
        $nameKey = $this->normalizeNameKey($name);
        $codeKey = $this->normalizeNameKey($code);

        $existing = ScheduleUnit::query()
            ->where('department_id', $departmentId)
            ->where(function ($query) use ($code, $name, $locationId) {
                $query->where('code', $code)
                    ->orWhere('name', $name)
                    ->orWhere('description', 'like', '%schedulev2 location_id='.$locationId.'%');
            })
            ->first();

        if ($existing) {
            $this->rememberUnitMapping($unitsByName, $existing);

            return (int) $existing->id;
        }

        // Reuse a same-named unit already known (any dept) only when it belongs to the resolved dept.
        foreach ([$nameKey, $codeKey] as $key) {
            if ($key !== '' && isset($unitsByName[$key]) && (int) $unitsByName[$key]['department_id'] === $departmentId) {
                return (int) $unitsByName[$key]['unit_id'];
            }
        }

        if (! config('schedule.schedulev2.create_missing_units', true)) {
            return null;
        }

        if ($dryRun) {
            return null;
        }

        $unitType = match (strtolower($locationType)) {
            'ward' => 'ward',
            'opd', 'clinic' => 'clinic',
            'office' => 'office',
            'area' => 'area',
            'er' => 'ward',
            default => 'section',
        };

        try {
            $unit = ScheduleUnit::query()->create([
                'department_id' => $departmentId,
                'code' => $code,
                'name' => $name,
                'unit_type' => $unitType,
                'sort_order' => 0,
                'is_active' => true,
                'description' => 'Imported from schedulev2 location_id='.$locationId,
            ]);

            $this->rememberUnitMapping($unitsByName, $unit);

            return (int) $unit->id;
        } catch (Throwable $e) {
            $stats['errors'][] = "Could not create unit for location \"{$name}\": ".$e->getMessage();

            return null;
        }
    }

    /**
     * @param  array<string, array{department_id:int,unit_id:int}>  $unitsByName
     */
    private function rememberUnitMapping(array &$unitsByName, ScheduleUnit $unit): void
    {
        $payload = [
            'department_id' => (int) $unit->department_id,
            'unit_id' => (int) $unit->id,
        ];
        foreach ([(string) $unit->name, (string) $unit->code] as $label) {
            $key = $this->normalizeNameKey($label);
            if ($key === '') {
                continue;
            }
            $unitsByName[$key] = $payload;
        }
    }

    private function buildNotes(object $row): string
    {
        $parts = [
            'schedulev2 emp_sched_id='.$row->id,
            'status='.$row->status,
        ];
        if (! empty($row->approved_by)) {
            $parts[] = 'approved_by='.$row->approved_by;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assignmentNeedsUpdate(ScheduleAssignment $assignment, array $payload): bool
    {
        return $this->assignmentDiffersFromSource(
            $assignment,
            (string) $payload['employee_id'],
            (string) $payload['schedule_date'],
            (int) $payload['shift_code_id'],
            isset($payload['unit_id']) ? ($payload['unit_id'] !== null ? (int) $payload['unit_id'] : null) : null,
            (bool) ($payload['is_temporary_floater'] ?? false),
            (string) ($payload['notes'] ?? ''),
            (int) $payload['legacy_emp_sched_id'],
            isset($payload['monthly_schedule_id']) ? (int) $payload['monthly_schedule_id'] : null,
        );
    }

    private function assignmentDiffersFromSource(
        ScheduleAssignment $assignment,
        string $employeeId,
        string $scheduleDate,
        int $shiftCodeId,
        ?int $unitId,
        bool $isTemporaryFloater,
        string $notes,
        int $legacyEmpSchedId,
        ?int $monthlyScheduleId = null,
        ?int $expectedDepartmentId = null,
        ?int $expectedYear = null,
        ?int $expectedMonth = null,
    ): bool {
        $currentDate = $assignment->schedule_date
            ? CarbonImmutable::parse($assignment->schedule_date)->toDateString()
            : '';

        if ((string) $assignment->employee_id !== $employeeId) {
            return true;
        }
        if ($currentDate !== $scheduleDate) {
            return true;
        }
        // Dry-run may use placeholder shift id 0 for not-yet-created codes → treat as changed.
        if ($shiftCodeId <= 0 || (int) $assignment->shift_code_id !== $shiftCodeId) {
            return true;
        }
        if ((int) ($assignment->unit_id ?? 0) !== (int) ($unitId ?? 0)) {
            return true;
        }
        if ((bool) $assignment->is_temporary_floater !== $isTemporaryFloater) {
            return true;
        }
        if ((int) ($assignment->legacy_emp_sched_id ?? 0) !== $legacyEmpSchedId) {
            return true;
        }
        if ($monthlyScheduleId !== null && (int) $assignment->monthly_schedule_id !== $monthlyScheduleId) {
            return true;
        }
        if ($expectedDepartmentId !== null || $expectedYear !== null || $expectedMonth !== null) {
            $assignment->loadMissing('monthlySchedule');
            $monthly = $assignment->monthlySchedule;
            if (
                ! $monthly
                || ($expectedDepartmentId !== null && (int) $monthly->department_id !== $expectedDepartmentId)
                || ($expectedYear !== null && (int) $monthly->year !== $expectedYear)
                || ($expectedMonth !== null && (int) $monthly->month !== $expectedMonth)
            ) {
                return true;
            }
        }
        if (trim((string) ($assignment->notes ?? '')) !== trim($notes)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function bumpSkip(array &$stats, string $reason): void
    {
        $stats['skipped']++;
        $stats['skip_reasons'][$reason] = (int) ($stats['skip_reasons'][$reason] ?? 0) + 1;
    }

    private function persistRun(
        string $batchKey,
        bool $dryRun,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $departmentId,
        ?int $divisionId,
        ?string $empId,
        ?int $limit,
        string $status,
        array $stats
    ): Schedulev2SyncRun {
        return Schedulev2SyncRun::query()->create([
            'batch_key' => $batchKey,
            'dry_run' => $dryRun,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'department_id' => $departmentId,
            'division_id' => $divisionId,
            'emp_id' => $empId,
            'limit' => $limit,
            'status' => $status,
            'stats' => $stats,
            'errors' => $stats['errors'] ?? [],
            'started_at' => now(),
            'finished_at' => $status === 'failed' ? now() : null,
        ]);
    }
}
