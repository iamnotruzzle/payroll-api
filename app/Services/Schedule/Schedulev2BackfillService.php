<?php

namespace App\Services\Schedule;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\RotationGroup;
use App\Models\Schedule\RotationGroupMember;
use App\Models\Schedule\ScheduleDepartmentProfile;
use App\Models\Schedule\ScheduleFloaterPoolMember;
use App\Models\Schedule\ScheduleMonthlyFloater;
use App\Models\Schedule\ScheduleOnCallPoolMember;
use App\Models\Schedule\ScheduleSignatory;
use App\Models\Schedule\ScheduleUnit;
use App\Models\Schedule\ScheduleUserUnit;
use App\Models\Schedule\Schedulev2LegacyMap;
use App\Models\Schedule\Schedulev2SyncRun;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Destructive clear + reference backfill from schedulev2 into payroll_scheduler.
 *
 * Does NOT call ScheduleLockService / DTR. Assignment import (optional) reuses
 * Schedulev2SyncService (approved A only, months locked without DTR).
 */
class Schedulev2BackfillService
{
    /**
     * Schedule-owned tables cleared in child→parent order.
     * Intentionally excludes: cache/jobs/migrations, roles, employee_references,
     * schedule_print_settings, schedule_print_logos (local print config).
     *
     * @var list<string>
     */
    public const CLEAR_TABLES = [
        'schedule_swaps',
        'schedule_assignments',
        'schedule_monthly_floaters',
        'schedule_floater_pool_members',
        'schedule_on_call_pool_members',
        'schedulev2_legacy_maps',
        'schedulev2_sync_runs',
        'schedule_audit_logs',
        'monthly_schedules',
        'schedule_template_days',
        'schedule_templates',
        'staffing_requirements',
        'rotation_group_members',
        'rotation_groups',
        'schedule_user_units',
        'employee_schedule_settings',
        'schedule_units',
        'shift_codes',
        'schedule_signatories',
        'schedule_department_profiles',
    ];

    public function __construct(
        private readonly ScheduleDivisionService $divisionService,
        private readonly Schedulev2SyncService $syncService,
    ) {}

    /**
     * @return list<string>
     */
    public function clearTables(): array
    {
        return self::CLEAR_TABLES;
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   force: bool,
     *   with_assignments: bool,
     *   batch_key: string,
     *   connection_ok: bool,
     *   tables_to_clear: list<string>,
     *   cleared: array<string, int>,
     *   created: array<string, int>,
     *   source_counts: array<string, int>,
     *   skipped: array<string, int>,
     *   notes: list<string>,
     *   errors: list<string>,
     *   assignment_sync?: array<string, mixed>,
     *   message?: string
     * }
     */
    public function backfill(
        bool $dryRun = true,
        bool $force = false,
        bool $withAssignments = false,
        ?CarbonImmutable $assignmentFrom = null,
        ?CarbonImmutable $assignmentTo = null,
        ?int $divisionId = null,
        ?string $batchKey = null,
    ): array {
        $batchKey = $batchKey ?: ('sv2-bf-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)));
        $connection = (string) config('schedule.schedulev2.connection', 'schedulev2');
        $scheduler = 'payroll_scheduler';

        $stats = [
            'dry_run' => $dryRun,
            'force' => $force,
            'with_assignments' => $withAssignments,
            'batch_key' => $batchKey,
            'connection_ok' => false,
            'tables_to_clear' => $this->existingClearTables($scheduler),
            'cleared' => [],
            'created' => [
                'shift_codes' => 0,
                'schedule_units' => 0,
                'rotation_groups' => 0,
                'rotation_group_members' => 0,
                'schedule_templates' => 0,
                'schedule_template_days' => 0,
                'employee_schedule_settings' => 0,
                'schedule_user_units' => 0,
                'schedule_floater_pool_members' => 0,
                'schedule_monthly_floaters' => 0,
                'schedule_on_call_pool_members' => 0,
                'schedule_signatories' => 0,
                'schedule_department_profiles' => 0,
                'schedulev2_legacy_maps' => 0,
            ],
            'source_counts' => [],
            'skipped' => [
                'units_no_department' => 0,
                'units_row_error' => 0,
                'groups_no_department' => 0,
                'groups_row_error' => 0,
                'members_no_group' => 0,
                'members_row_error' => 0,
                'settings_no_unit' => 0,
                'settings_row_error' => 0,
                'handled_no_unit' => 0,
                'handled_row_error' => 0,
                'floaters_no_unit' => 0,
                'floaters_row_error' => 0,
                'on_call_no_unit' => 0,
                'on_call_row_error' => 0,
                'signatories_no_name' => 0,
                'signatories_row_error' => 0,
                'profiles_row_error' => 0,
                'shifts_row_error' => 0,
            ],
            'notes' => [
                'NDOS has no patterns/templates tables — local schedule_templates stay empty after clear.',
                'Does not clear employee_references, print settings/logos, roles, cache, jobs, or migrations.',
                'Never calls ScheduleLockService / DTR.',
                'Per-row errors are recorded and skipped; later phases still run.',
            ],
            'errors' => [],
        ];

        if (! $dryRun && ! $force) {
            $stats['errors'][] = 'Apply requires --force together with --apply (destructive clear).';
            $stats['message'] = $stats['errors'][0];

            return $stats;
        }

        try {
            $this->syncService->assertConnection($connection);
            $stats['connection_ok'] = true;
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            $stats['message'] = $e->getMessage();

            return $stats;
        }

        $stats['source_counts'] = $this->sourceCounts($connection);

        if ($dryRun) {
            foreach ($stats['tables_to_clear'] as $table) {
                $stats['cleared'][$table] = Schema::connection($scheduler)->hasTable($table)
                    ? (int) DB::connection($scheduler)->table($table)->count()
                    : 0;
            }
            $stats['created'] = $this->estimateCreated($stats['source_counts']);
            $stats['notes'][] = 'Dry-run only — no tables truncated and no rows written.';

            if ($withAssignments) {
                $from = $assignmentFrom ?? CarbonImmutable::today()->subMonthsNoOverflow(
                    (int) config('schedule.schedulev2.default_months_back', 1)
                )->startOfMonth();
                $to = $assignmentTo ?? CarbonImmutable::today()->addMonthsNoOverflow(
                    (int) config('schedule.schedulev2.default_months_ahead', 1)
                )->endOfMonth();
                $stats['assignment_sync'] = $this->syncService->sync(
                    from: $from,
                    to: $to,
                    dryRun: true,
                    divisionId: $divisionId,
                    batchKey: $batchKey.'-assign',
                );
            }

            return $stats;
        }

        $run = null;

        try {
            // TRUNCATE commits implicitly on MySQL — clear outside a wrapping transaction.
            $stats['cleared'] = $this->clearScheduleTables($scheduler);

            // Sync run row must be created AFTER truncate (schedulev2_sync_runs is cleared).
            $run = Schedulev2SyncRun::query()->create([
                'batch_key' => $batchKey,
                'dry_run' => false,
                'from_date' => $assignmentFrom?->toDateString(),
                'to_date' => $assignmentTo?->toDateString(),
                'department_id' => null,
                'division_id' => $divisionId,
                'emp_id' => null,
                'limit' => null,
                'status' => 'running',
                'stats' => ['phase' => 'backfill'],
                'errors' => [],
                'started_at' => now(),
            ]);

            // No wrapping transaction: one bad row must not roll back prior phases
            // (TRUNCATE already committed). Each importer catches per-row errors.
            $context = $this->buildMappingContext($connection);

            $this->backfillShiftCodes($connection, $run, $stats);
            $this->backfillUnits($connection, $context, $run, $stats);
            $this->backfillGroups($connection, $context, $run, $stats);
            $this->backfillGroupMembers($connection, $context, $run, $stats);
            $this->backfillEmployeeSettings($connection, $context, $run, $stats);
            $this->backfillHandledUnits($connection, $context, $run, $stats);
            $this->backfillFloaterPools($connection, $context, $run, $stats);
            $this->backfillMonthlyFloaters($connection, $context, $run, $stats);
            $this->backfillOnCalls($connection, $context, $run, $stats);
            $this->backfillSignatories($connection, $context, $run, $stats);
            $this->provisionDepartmentProfiles($stats);

            if ($withAssignments) {
                $from = $assignmentFrom ?? CarbonImmutable::today()->subMonthsNoOverflow(
                    (int) config('schedule.schedulev2.default_months_back', 1)
                )->startOfMonth();
                $to = $assignmentTo ?? CarbonImmutable::today()->addMonthsNoOverflow(
                    (int) config('schedule.schedulev2.default_months_ahead', 1)
                )->endOfMonth();

                $stats['assignment_sync'] = $this->syncService->sync(
                    from: $from,
                    to: $to,
                    dryRun: false,
                    divisionId: $divisionId,
                    batchKey: $batchKey.'-assign',
                );

                if (! empty($stats['assignment_sync']['errors'])) {
                    $stats['errors'] = array_merge(
                        $stats['errors'],
                        array_slice($stats['assignment_sync']['errors'], 0, 50)
                    );
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            $stats['message'] = $e->getMessage();
        }

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

    /**
     * @return list<string>
     */
    private function existingClearTables(string $scheduler): array
    {
        return array_values(array_filter(
            self::CLEAR_TABLES,
            fn (string $table) => Schema::connection($scheduler)->hasTable($table)
        ));
    }

    /**
     * @return array<string, int>
     */
    private function clearScheduleTables(string $scheduler): array
    {
        $cleared = [];
        $tables = $this->existingClearTables($scheduler);

        DB::connection($scheduler)->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $count = (int) DB::connection($scheduler)->table($table)->count();
                DB::connection($scheduler)->table($table)->truncate();
                $cleared[$table] = $count;
            }
        } finally {
            DB::connection($scheduler)->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $cleared;
    }

    /**
     * @return array<string, int>
     */
    private function sourceCounts(string $connection): array
    {
        $tables = [
            'shifts',
            'locations',
            'clinics',
            'groups',
            'employee_locations',
            'handled_locations',
            'employee_floaters',
            'on_calls',
            'second_on_calls',
            'signatories',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = Schema::connection($connection)->hasTable($table)
                ? (int) DB::connection($connection)->table($table)->count()
                : 0;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $source
     * @return array<string, int>
     */
    private function estimateCreated(array $source): array
    {
        return [
            'shift_codes' => $source['shifts'] ?? 0,
            'schedule_units' => ($source['locations'] ?? 0) + ($source['clinics'] ?? 0),
            'rotation_groups' => $source['groups'] ?? 0,
            'rotation_group_members' => $source['employee_locations'] ?? 0,
            'schedule_templates' => 0,
            'schedule_template_days' => 0,
            'employee_schedule_settings' => $source['employee_locations'] ?? 0,
            'schedule_user_units' => $source['handled_locations'] ?? 0,
            'schedule_floater_pool_members' => $source['employee_locations'] ?? 0,
            'schedule_monthly_floaters' => $source['employee_floaters'] ?? 0,
            'schedule_on_call_pool_members' => ($source['on_calls'] ?? 0) + ($source['second_on_calls'] ?? 0),
            'schedule_signatories' => $source['signatories'] ?? 0,
            'schedule_department_profiles' => count($this->divisionService->departmentIdsForDivision(
                $this->divisionService->cnoDivisionId()
            )),
            'schedulev2_legacy_maps' => ($source['shifts'] ?? 0)
                + ($source['locations'] ?? 0)
                + ($source['groups'] ?? 0),
        ];
    }

    /**
     * @return array{
     *   department_index: array{by_name: array<string, int>, by_name_cno: array<string, int>, division_by_id: array<int, int>},
     *   location_dept: array<int, int>,
     *   location_unit: array<int, int>,
     *   clinic_unit: array<int, int>,
     *   group_map: array<int, int>,
     *   shift_map: array<int, int>
     * }
     */
    private function buildMappingContext(string $connection): array
    {
        return [
            'department_index' => $this->buildDepartmentIndex(),
            'location_dept' => [],
            'location_unit' => [],
            'clinic_unit' => [],
            'group_map' => [],
            'shift_map' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function backfillShiftCodes(string $connection, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('shifts')) {
            return;
        }

        $rows = DB::connection($connection)->table('shifts')->orderBy('id')->get();
        foreach ($rows as $row) {
            try {
                $label = trim((string) ($row->shift_label ?? ''));
                if ($label === '') {
                    continue;
                }

                $code = Str::upper(Str::limit(preg_replace('/\s+/', '', $label) ?: $label, 20, ''));
                $type = strtoupper((string) ($row->type ?? ''));
                $start = $row->time_start ?: null;
                $end = $row->time_end ?: null;
                $isLeave = $type === 'L';
                $isWork = ! in_array($type, ['O', 'L', 'H', 'OC'], true);
                $isNight = $this->looksNightShift($start, $end, $code, $type);
                $endDayOffset = ($start && $end && (string) $end < (string) $start) ? 1 : 0;

                $existing = ShiftCode::query()
                    ->whereNull('department_id')
                    ->whereRaw('UPPER(code) = ?', [$code])
                    ->first();

                if ($existing) {
                    $shift = $existing;
                } else {
                    $shift = ShiftCode::query()->create([
                        'department_id' => null,
                        'code' => $code,
                        'name' => (string) ($row->shift_desc ?: $label),
                        'start_time' => $start,
                        'end_time' => $end,
                        'end_day_offset' => $endDayOffset,
                        'work_hours' => $this->estimateWorkHours($start, $end, $endDayOffset),
                        'is_work_shift' => $isWork,
                        'is_night_shift' => $isNight,
                        'is_leave_code' => $isLeave,
                        'is_active' => true,
                        'description' => 'Backfilled from schedulev2 shifts.id='.$row->id,
                    ]);
                    $stats['created']['shift_codes']++;
                }

                $this->rememberMap('shifts', (string) $row->id, 'shift_codes', (int) $shift->id, null, $run, $stats);
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'shifts_row_error', 'shifts.id='.$row->id, $e);
            }
        }
    }

    /**
     * @param  array{
     *   department_index: array{by_name: array<string, int>, by_name_cno: array<string, int>, division_by_id: array<int, int>},
     *   location_dept: array<int, int>,
     *   location_unit: array<int, int>,
     *   clinic_unit: array<int, int>,
     *   group_map: array<int, int>,
     *   shift_map: array<int, int>
     * }  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillUnits(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('locations')) {
            return;
        }

        $locations = DB::connection($connection)->table('locations')->orderBy('id')->get();
        foreach ($locations as $row) {
            try {
                $locationId = (int) $row->id;
                $name = trim((string) ($row->name ?? ''));
                if ($name === '') {
                    $stats['skipped']['units_no_department']++;

                    continue;
                }

                $location = (object) [
                    'id' => $locationId,
                    'name' => $name,
                    'division_id' => isset($row->division_id) ? (int) $row->division_id : null,
                    'department_id' => isset($row->department_id) ? (int) $row->department_id : null,
                ];

                $departmentId = $this->resolveTargetDepartmentId(
                    $location,
                    null,
                    $context['department_index'],
                    []
                );

                if ($departmentId === null) {
                    // Fall back: if location is CNO-scoped, place under first CNO department.
                    if (
                        isset($row->division_id)
                        && (int) $row->division_id === $this->divisionService->cnoDivisionId()
                    ) {
                        $departmentId = $this->divisionService->departmentIdsForDivision(
                            $this->divisionService->cnoDivisionId()
                        )[0] ?? null;
                    }
                }

                if ($departmentId === null) {
                    $departmentId = $this->resolveFallbackDepartmentId($context['department_index'], $name);
                }

                if ($departmentId === null) {
                    $stats['skipped']['units_no_department']++;
                    $this->pushError($stats, "No department for location_id={$locationId} ({$name})");

                    continue;
                }

                $code = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?: ('LOC'.$locationId), 40, ''));
                // Ensure unique code within department.
                $baseCode = $code;
                $suffix = 1;
                while (
                    ScheduleUnit::query()
                        ->where('department_id', $departmentId)
                        ->where('code', $code)
                        ->exists()
                ) {
                    $code = Str::upper(Str::limit($baseCode.'-'.$suffix, 40, ''));
                    $suffix++;
                }
                $unitType = $this->unitTypeFromLocationType(null, (bool) ($row->has_multiple_locations ?? false));

                $unit = ScheduleUnit::query()->updateOrCreate(
                    [
                        'department_id' => $departmentId,
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'unit_type' => $unitType,
                        'sort_order' => $locationId,
                        'is_active' => strtoupper((string) ($row->status ?? 'A')) === 'A',
                        'description' => 'Backfilled from schedulev2 location_id='.$locationId,
                    ]
                );

                $context['location_dept'][$locationId] = $departmentId;
                $context['location_unit'][$locationId] = (int) $unit->id;
                if ($unit->wasRecentlyCreated) {
                    $stats['created']['schedule_units']++;
                }
                $this->rememberMap('locations', (string) $locationId, 'schedule_units', (int) $unit->id, null, $run, $stats);
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'units_row_error', 'locations.id='.($row->id ?? '?'), $e);
            }
        }

        if (! Schema::connection($connection)->hasTable('clinics')) {
            return;
        }

        // Place clinics under OPD location department when present, else first CNO dept.
        $opdLocation = DB::connection($connection)->table('locations')
            ->where('name', 'like', '%Outpatient%')
            ->orderBy('id')
            ->first();
        $opdDept = $opdLocation && isset($context['location_dept'][(int) $opdLocation->id])
            ? $context['location_dept'][(int) $opdLocation->id]
            : ($this->resolveFallbackDepartmentId($context['department_index'], 'Outpatient')
                ?? $this->divisionService->departmentIdsForDivision($this->divisionService->cnoDivisionId())[0] ?? null);

        if ($opdDept === null) {
            return;
        }

        foreach (DB::connection($connection)->table('clinics')->orderBy('id')->get() as $clinic) {
            try {
                $clinicId = (int) $clinic->id;
                $name = trim((string) ($clinic->name ?? ''));
                if ($name === '') {
                    continue;
                }

                $codeRaw = trim((string) ($clinic->code ?? ''));
                $code = Str::upper(Str::limit($codeRaw !== '' ? $codeRaw : ('CLINIC-'.$clinicId), 40, ''));

                // Avoid unique collisions within department.
                $exists = ScheduleUnit::query()
                    ->where('department_id', $opdDept)
                    ->where(function ($q) use ($code, $name) {
                        $q->where('code', $code)->orWhere('name', $name);
                    })
                    ->exists();
                if ($exists) {
                    $code = Str::upper(Str::limit('CLINIC-'.$clinicId.'-'.$code, 40, ''));
                }

                $unit = ScheduleUnit::query()->updateOrCreate(
                    [
                        'department_id' => $opdDept,
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'unit_type' => 'clinic',
                        'sort_order' => $clinicId,
                        'is_active' => strtoupper((string) ($clinic->status ?? 'A')) === 'A',
                        'description' => 'Backfilled from schedulev2 clinic_id='.$clinicId,
                    ]
                );

                $context['clinic_unit'][$clinicId] = (int) $unit->id;
                if ($unit->wasRecentlyCreated) {
                    $stats['created']['schedule_units']++;
                }
                $this->rememberMap('clinics', (string) $clinicId, 'schedule_units', (int) $unit->id, null, $run, $stats);
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'units_row_error', 'clinics.id='.($clinic->id ?? '?'), $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillGroups(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('groups')) {
            return;
        }

        foreach (DB::connection($connection)->table('groups')->orderBy('id')->get() as $row) {
            try {
                $groupId = (int) $row->id;
                $locationId = (int) ($row->location_id ?? 0);
                $departmentId = $context['location_dept'][$locationId] ?? null;
                if ($departmentId === null) {
                    $stats['skipped']['groups_no_department']++;

                    continue;
                }

                $groupName = trim((string) ($row->group_name ?? ''));
                if ($groupName === '') {
                    $groupName = 'Group '.$groupId;
                }

                $locationName = ScheduleUnit::query()->find($context['location_unit'][$locationId] ?? 0)?->name
                    ?? ('Location '.$locationId);
                // Include legacy groups.id so same location + group_name never collide
                // on rotation_groups_department_name_unique (e.g. two "Group1" under OB - Complex).
                $uniqueName = Str::limit($locationName.' — '.$groupName.' (#'.$groupId.')', 240, '');

                // Stable upsert: prefer existing legacy map, else department+name (includes #id).
                $mapped = Schedulev2LegacyMap::query()
                    ->where('source_table', 'groups')
                    ->where('source_key', (string) $groupId)
                    ->first();

                $group = null;
                if ($mapped && (int) $mapped->target_id > 0) {
                    $group = RotationGroup::query()->find((int) $mapped->target_id);
                }

                if ($group === null) {
                    $group = RotationGroup::query()->updateOrCreate(
                        [
                            'department_id' => $departmentId,
                            'name' => $uniqueName,
                        ],
                        [
                            'description' => 'Backfilled from schedulev2 groups.id='.$groupId
                                .' (location_id='.$locationId.', group_name='.$groupName.')',
                            'is_active' => strtoupper((string) ($row->status ?? 'A')) === 'A',
                        ]
                    );
                    if ($group->wasRecentlyCreated) {
                        $stats['created']['rotation_groups']++;
                    }
                } else {
                    $group->update([
                        'department_id' => $departmentId,
                        'name' => $uniqueName,
                        'description' => 'Backfilled from schedulev2 groups.id='.$groupId
                            .' (location_id='.$locationId.', group_name='.$groupName.')',
                        'is_active' => strtoupper((string) ($row->status ?? 'A')) === 'A',
                    ]);
                }

                $context['group_map'][$groupId] = (int) $group->id;
                $this->rememberMap('groups', (string) $groupId, 'rotation_groups', (int) $group->id, null, $run, $stats);
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'groups_row_error', 'groups.id='.($row->id ?? '?'), $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillGroupMembers(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('employee_locations')) {
            return;
        }

        $rows = DB::connection($connection)
            ->table('employee_locations')
            ->whereNotNull('group_id')
            ->where('group_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('UPPER(status) = ?', ['A']);
            })
            ->orderBy('group_id')
            ->orderBy('set_order')
            ->orderBy('id')
            ->get();

        $seen = [];
        foreach ($rows as $row) {
            try {
                $groupId = (int) $row->group_id;
                $rotationGroupId = $context['group_map'][$groupId] ?? null;
                $empId = trim((string) ($row->emp_id ?? ''));
                if ($rotationGroupId === null || $empId === '') {
                    $stats['skipped']['members_no_group']++;

                    continue;
                }

                $key = $rotationGroupId.'|'.$empId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $member = RotationGroupMember::query()->firstOrCreate(
                    [
                        'rotation_group_id' => $rotationGroupId,
                        'employee_id' => $empId,
                    ],
                    [
                        'rotation_order' => (int) ($row->set_order ?? 0),
                    ]
                );

                if ($member->wasRecentlyCreated) {
                    $stats['created']['rotation_group_members']++;
                }
                $this->rememberMap(
                    'employee_locations',
                    'group-member:'.$row->id,
                    'rotation_group_members',
                    0,
                    $empId,
                    $run,
                    $stats,
                    skipTargetId: true
                );
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'members_row_error', 'employee_locations.id='.($row->id ?? '?'), $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillEmployeeSettings(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('employee_locations')) {
            return;
        }

        $rows = DB::connection($connection)
            ->table('employee_locations')
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('UPPER(status) = ?', ['A']);
            })
            ->orderBy('emp_id')
            ->orderBy('set_order')
            ->orderBy('id')
            ->get();

        $byEmp = [];
        foreach ($rows as $row) {
            $empId = trim((string) ($row->emp_id ?? ''));
            if ($empId === '' || isset($byEmp[$empId])) {
                continue;
            }
            $byEmp[$empId] = $row;
        }

        foreach ($byEmp as $empId => $row) {
            try {
                $locationId = (int) ($row->location_id ?? 0);
                $clinicId = (int) ($row->clinic_id ?? 0);
                $unitId = null;
                if ($clinicId > 0 && isset($context['clinic_unit'][$clinicId])) {
                    $unitId = $context['clinic_unit'][$clinicId];
                } elseif ($locationId > 0 && isset($context['location_unit'][$locationId])) {
                    $unitId = $context['location_unit'][$locationId];
                }

                if ($unitId === null) {
                    $stats['skipped']['settings_no_unit']++;
                }

                $setting = EmployeeScheduleSetting::query()->updateOrCreate(
                    ['employee_id' => $empId],
                    [
                        'default_shift_code_id' => null,
                        'default_unit_id' => $unitId,
                        'can_rotate_shift' => ! empty($row->group_id),
                        'uses_regular_weekday_schedule' => false,
                        'max_consecutive_duty_days' => 5,
                        'max_night_shifts_per_month' => 7,
                        'is_active' => true,
                    ]
                );

                if ($setting->wasRecentlyCreated) {
                    $stats['created']['employee_schedule_settings']++;
                }

                if ((int) ($row->is_floater ?? 0) === 1 && $unitId !== null) {
                    $departmentId = $context['location_dept'][$locationId] ?? null;
                    if ($departmentId !== null) {
                        $pool = ScheduleFloaterPoolMember::query()->firstOrCreate(
                            [
                                'department_id' => $departmentId,
                                'emp_id' => $empId,
                            ],
                            [
                                'unit_id' => $unitId,
                                'sort_order' => (int) ($row->set_order ?? 0),
                                'is_active' => true,
                                'notes' => 'Backfilled from schedulev2 employee_locations.is_floater',
                            ]
                        );
                        if ($pool->wasRecentlyCreated) {
                            $stats['created']['schedule_floater_pool_members']++;
                        }
                    }
                }

                $this->rememberMap(
                    'employee_locations',
                    'setting:'.$row->id,
                    'employee_schedule_settings',
                    0,
                    $empId,
                    $run,
                    $stats,
                    skipTargetId: true
                );
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'settings_row_error', 'employee_locations emp_id='.$empId, $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillHandledUnits(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('handled_locations')) {
            return;
        }

        foreach (DB::connection($connection)->table('handled_locations')->orderBy('id')->get() as $row) {
            try {
                $empId = trim((string) ($row->emp_id ?? ''));
                if ($empId === '') {
                    continue;
                }

                $locations = $row->locations;
                if (is_string($locations)) {
                    $locations = json_decode($locations, true) ?: [];
                }
                if (! is_array($locations)) {
                    continue;
                }

                foreach ($locations as $loc) {
                    if (! is_array($loc)) {
                        continue;
                    }
                    $locationId = (int) ($loc['location_id'] ?? 0);
                    $unitId = $context['location_unit'][$locationId] ?? null;
                    if ($unitId === null) {
                        $stats['skipped']['handled_no_unit']++;

                        continue;
                    }

                    $created = ScheduleUserUnit::query()->firstOrCreate([
                        'emp_id' => $empId,
                        'schedule_unit_id' => $unitId,
                    ]);
                    if ($created->wasRecentlyCreated) {
                        $stats['created']['schedule_user_units']++;
                    }
                }

                $this->rememberMap(
                    'handled_locations',
                    (string) $row->id,
                    'schedule_user_units',
                    0,
                    $empId,
                    $run,
                    $stats,
                    skipTargetId: true
                );
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'handled_row_error', 'handled_locations.id='.($row->id ?? '?'), $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillFloaterPools(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        // Pool members also filled from employee_locations.is_floater in settings step.
        // No additional source table beyond that for standing pools.
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillMonthlyFloaters(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('employee_floaters')) {
            return;
        }

        foreach (DB::connection($connection)->table('employee_floaters')->orderBy('id')->get() as $row) {
            try {
                $locationId = (int) ($row->location_id ?? 0);
                $clinicId = (int) ($row->clinic_id ?? 0);
                $unitId = null;
                $departmentId = null;

                if ($clinicId > 0 && isset($context['clinic_unit'][$clinicId])) {
                    $unitId = $context['clinic_unit'][$clinicId];
                    $departmentId = (int) (ScheduleUnit::query()->find($unitId)?->department_id ?? 0) ?: null;
                } elseif ($locationId > 0) {
                    $unitId = $context['location_unit'][$locationId] ?? null;
                    $departmentId = $context['location_dept'][$locationId] ?? null;
                }

                if ($departmentId === null || $unitId === null) {
                    $stats['skipped']['floaters_no_unit']++;

                    continue;
                }

                $employees = $row->employees;
                if (is_string($employees)) {
                    $employees = json_decode($employees, true) ?: [];
                }
                if (! is_array($employees)) {
                    continue;
                }

                $year = (int) ($row->year ?? 0);
                $month = (int) ($row->month ?? 0);
                if ($year < 2000 || $month < 1 || $month > 12) {
                    continue;
                }

                foreach ($employees as $emp) {
                    if (! is_array($emp)) {
                        continue;
                    }
                    $empId = trim((string) ($emp['emp_id'] ?? ''));
                    if ($empId === '') {
                        continue;
                    }

                    $floater = ScheduleMonthlyFloater::query()->firstOrCreate(
                        [
                            'department_id' => $departmentId,
                            'year' => $year,
                            'month' => $month,
                            'emp_id' => $empId,
                            'unit_id' => $unitId,
                        ],
                        [
                            'notes' => 'Backfilled from schedulev2 employee_floaters.id='.$row->id,
                        ]
                    );
                    if ($floater->wasRecentlyCreated) {
                        $stats['created']['schedule_monthly_floaters']++;
                    }
                }

                $this->rememberMap(
                    'employee_floaters',
                    (string) $row->id,
                    'schedule_monthly_floaters',
                    0,
                    null,
                    $run,
                    $stats,
                    skipTargetId: true
                );
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'floaters_row_error', 'employee_floaters.id='.($row->id ?? '?'), $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillOnCalls(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (Schema::connection($connection)->hasTable('on_calls')) {
            foreach (DB::connection($connection)->table('on_calls')->orderBy('id')->get() as $row) {
                $this->importOnCallPoolRow($row, false, $context, $run, $stats, 'on_calls');
            }
        }

        if (Schema::connection($connection)->hasTable('second_on_calls')) {
            foreach (DB::connection($connection)->table('second_on_calls')->orderBy('id')->get() as $row) {
                $this->importOnCallPoolRow($row, true, $context, $run, $stats, 'second_on_calls');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function importOnCallPoolRow(
        object $row,
        bool $isSecond,
        array &$context,
        Schedulev2SyncRun $run,
        array &$stats,
        string $sourceTable
    ): void {
        try {
            $locationId = (int) ($row->location_id ?? 0);
            $clinicId = (int) ($row->clinic_id ?? 0);
            $unitId = null;
            $departmentId = null;

            if ($clinicId > 0 && isset($context['clinic_unit'][$clinicId])) {
                $unitId = $context['clinic_unit'][$clinicId];
                $departmentId = (int) (ScheduleUnit::query()->find($unitId)?->department_id ?? 0) ?: null;
            } elseif ($locationId > 0) {
                $unitId = $context['location_unit'][$locationId] ?? null;
                $departmentId = $context['location_dept'][$locationId] ?? null;
            }

            if ($departmentId === null) {
                $stats['skipped']['on_call_no_unit']++;

                return;
            }

            $employees = $row->employees;
            if (is_string($employees)) {
                $employees = json_decode($employees, true) ?: [];
            }
            if (! is_array($employees) || $employees === []) {
                return;
            }

            foreach ($employees as $emp) {
                if (! is_array($emp)) {
                    continue;
                }
                $empId = trim((string) ($emp['emp_id'] ?? ''));
                if ($empId === '') {
                    continue;
                }
                if (isset($emp['status']) && strtoupper((string) $emp['status']) !== 'A') {
                    continue;
                }

                $member = ScheduleOnCallPoolMember::query()->firstOrCreate(
                    [
                        'department_id' => $departmentId,
                        'emp_id' => $empId,
                        'is_second' => $isSecond,
                        'unit_id' => $unitId,
                    ],
                    [
                        'sort_order' => (int) ($emp['set_order'] ?? 0),
                        'is_active' => true,
                        'notes' => 'Backfilled from schedulev2 '.$sourceTable.'.id='.$row->id,
                    ]
                );
                if ($member->wasRecentlyCreated) {
                    $stats['created']['schedule_on_call_pool_members']++;
                }
            }

            $this->rememberMap(
                $sourceTable,
                (string) $row->id,
                'schedule_on_call_pool_members',
                0,
                null,
                $run,
                $stats,
                skipTargetId: true
            );
        } catch (Throwable $e) {
            $this->recordRowError($stats, 'on_call_row_error', $sourceTable.'.id='.($row->id ?? '?'), $e);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $stats
     */
    private function backfillSignatories(string $connection, array &$context, Schedulev2SyncRun $run, array &$stats): void
    {
        if (! Schema::connection($connection)->hasTable('signatories')) {
            return;
        }

        $nameByEmp = Employee::query()
            ->get(['emp_id', 'lastname', 'firstname', 'middlename'])
            ->mapWithKeys(function ($emp) {
                $parts = array_filter([
                    trim((string) ($emp->lastname ?? '')),
                    trim((string) (($emp->firstname ?? '').' '.($emp->middlename ?? ''))),
                ]);

                return [(string) $emp->emp_id => implode(', ', $parts)];
            })
            ->all();

        foreach (DB::connection($connection)->table('signatories')->orderBy('id')->get() as $row) {
            try {
                $locationId = (int) ($row->location_id ?? 0);
                $departmentId = $context['location_dept'][$locationId] ?? null;
                if ($departmentId === null) {
                    continue;
                }

                $employees = $row->employees;
                if (is_string($employees)) {
                    $employees = json_decode($employees, true) ?: [];
                }
                if (! is_array($employees)) {
                    continue;
                }

                foreach ($employees as $emp) {
                    if (! is_array($emp)) {
                        continue;
                    }
                    if (isset($emp['status']) && strtoupper((string) $emp['status']) !== 'A') {
                        continue;
                    }

                    $empId = trim((string) ($emp['emp_id'] ?? ''));
                    $personName = $nameByEmp[$empId] ?? '';
                    if ($personName === '') {
                        $stats['skipped']['signatories_no_name']++;
                        $personName = $empId !== '' ? 'emp_id '.$empId : 'Unknown';
                    }

                    $purpose = Str::limit((string) ($emp['role'] ?? 'signatory'), 80, '');
                    $designation = Str::limit((string) ($emp['position'] ?? ''), 255, '');

                    ScheduleSignatory::query()->create([
                        'department_id' => $departmentId,
                        'purpose' => $purpose !== '' ? $purpose : 'signatory',
                        'person_name' => $personName,
                        'designation' => $designation !== '' ? $designation : null,
                        'display_order' => (int) ($emp['order'] ?? 0),
                        'is_active' => true,
                    ]);
                    $stats['created']['schedule_signatories']++;
                }

                $this->rememberMap(
                    'signatories',
                    (string) $row->id,
                    'schedule_signatories',
                    0,
                    null,
                    $run,
                    $stats,
                    skipTargetId: true
                );
            } catch (Throwable $e) {
                $this->recordRowError($stats, 'signatories_row_error', 'signatories.id='.($row->id ?? '?'), $e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function provisionDepartmentProfiles(array &$stats): void
    {
        $cnoDivisionId = $this->divisionService->cnoDivisionId();
        $defaults = $this->divisionService->cnoProfileDefaults();

        foreach ($this->divisionService->departmentsForDivision($cnoDivisionId) as $department) {
            try {
                $profile = ScheduleDepartmentProfile::query()->firstOrCreate(
                    ['department_id' => (int) $department->department_id],
                    [
                        'uses_units' => $defaults['uses_units'],
                        'uses_floaters' => $defaults['uses_floaters'],
                        'uses_on_call' => $defaults['uses_on_call'],
                        'uses_swaps' => $defaults['uses_swaps'],
                        'uses_census' => $defaults['uses_census'],
                    ]
                );
                if ($profile->wasRecentlyCreated) {
                    $stats['created']['schedule_department_profiles']++;
                }
            } catch (Throwable $e) {
                $this->recordRowError(
                    $stats,
                    'profiles_row_error',
                    'department_id='.$department->department_id,
                    $e
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function rememberMap(
        string $sourceTable,
        string $sourceKey,
        string $targetTable,
        int $targetId,
        ?string $empId,
        Schedulev2SyncRun $run,
        array &$stats,
        bool $skipTargetId = false
    ): void {
        if ($skipTargetId) {
            // Still record lineage with target_id 0 for bulk/json sources.
            $targetId = max(0, $targetId);
        }

        Schedulev2LegacyMap::query()->updateOrCreate(
            [
                'source_table' => $sourceTable,
                'source_key' => $sourceKey,
            ],
            [
                'target_table' => $targetTable,
                'target_id' => $targetId,
                'emp_id' => $empId,
                'checksum' => null,
                'sync_run_id' => $run->id,
            ]
        );
        $stats['created']['schedulev2_legacy_maps']++;
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

            // Loose match: strip punctuation / compare compact keys, then contains.
            $fuzzyId = $this->fuzzyMatchDepartmentId(
                $nameKey,
                $preferCno ? $cnoDepartmentsByName : $departmentsByName
            );
            if ($fuzzyId === null && $preferCno) {
                $fuzzyId = $this->fuzzyMatchDepartmentId($nameKey, $departmentsByName);
            }
            if ($fuzzyId !== null) {
                return $fuzzyId;
            }

            if ($nameKey !== '' && isset($unitsByName[$nameKey])) {
                return (int) $unitsByName[$nameKey]['department_id'];
            }

            if (
                $preferCno
                && $homeDepartmentId !== null
                && isset($departmentIndex['division_by_id'][$homeDepartmentId])
                && (int) $departmentIndex['division_by_id'][$homeDepartmentId] === $cnoDivisionId
            ) {
                return $homeDepartmentId;
            }

            // CNO location without exact HRIS name match → first CNO department (Nursing Service home).
            if ($preferCno) {
                $cnoDepts = $this->divisionService->departmentIdsForDivision($cnoDivisionId);
                if ($cnoDepts !== []) {
                    return $cnoDepts[0];
                }
            }
        }

        return $homeDepartmentId !== null && $homeDepartmentId > 0 ? $homeDepartmentId : null;
    }

    /**
     * Configured fallback or first CNO department for unmatched NDOS locations
     * (buildings/wings that are not HRIS department names, e.g. CONRADO E.ESTRELLA).
     *
     * @param  array{by_name: array<string, int>, by_name_cno: array<string, int>, division_by_id: array<int, int>}  $departmentIndex
     */
    private function resolveFallbackDepartmentId(array $departmentIndex, string $locationName = ''): ?int
    {
        $configured = config('schedule.schedulev2.backfill_fallback_department_id');
        if ($configured !== null && (int) $configured > 0) {
            return (int) $configured;
        }

        if (! (bool) config('schedule.schedulev2.backfill_unmatched_locations_to_cno', true)) {
            return null;
        }

        // Prefer fuzzy CNO name match once more before dumping into first CNO dept.
        $nameKey = $this->normalizeNameKey($locationName);
        if ($nameKey !== '') {
            $fuzzy = $this->fuzzyMatchDepartmentId($nameKey, $departmentIndex['by_name_cno'] ?? []);
            if ($fuzzy !== null) {
                return $fuzzy;
            }
            $fuzzy = $this->fuzzyMatchDepartmentId($nameKey, $departmentIndex['by_name'] ?? []);
            if ($fuzzy !== null) {
                return $fuzzy;
            }
        }

        $cnoDepts = $this->divisionService->departmentIdsForDivision($this->divisionService->cnoDivisionId());

        return $cnoDepts[0] ?? null;
    }

    /**
     * @param  array<string, int>  $departmentsByName
     */
    private function fuzzyMatchDepartmentId(string $nameKey, array $departmentsByName): ?int
    {
        if ($nameKey === '' || $departmentsByName === []) {
            return null;
        }

        $compact = $this->compactNameKey($nameKey);
        foreach ($departmentsByName as $deptKey => $departmentId) {
            if ($this->compactNameKey((string) $deptKey) === $compact) {
                return (int) $departmentId;
            }
        }

        // Substring contains either way (min length guard avoids junk matches).
        if (strlen($compact) < 6) {
            return null;
        }

        foreach ($departmentsByName as $deptKey => $departmentId) {
            $deptCompact = $this->compactNameKey((string) $deptKey);
            if ($deptCompact === '') {
                continue;
            }
            if (str_contains($deptCompact, $compact) || str_contains($compact, $deptCompact)) {
                return (int) $departmentId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function recordRowError(array &$stats, string $skipKey, string $context, Throwable $e): void
    {
        $stats['skipped'][$skipKey] = (int) ($stats['skipped'][$skipKey] ?? 0) + 1;
        $this->pushError($stats, $context.': '.$e->getMessage());
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function pushError(array &$stats, string $message): void
    {
        if (count($stats['errors']) >= 100) {
            return;
        }
        $stats['errors'][] = $message;
    }

    private function normalizeNameKey(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?: ''));
    }

    private function compactNameKey(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/', '', $value) ?: '');
    }

    private function unitTypeFromLocationType(?string $locationType, bool $hasMultiple = false): string
    {
        $type = strtolower((string) $locationType);

        return match ($type) {
            'ward' => 'ward',
            'opd', 'clinic' => 'clinic',
            'office' => 'office',
            'area' => 'area',
            'er' => 'ward',
            default => $hasMultiple ? 'area' : 'section',
        };
    }

    private function looksNightShift(?string $start, ?string $end, string $code, string $type): bool
    {
        if (in_array($code, ['N', 'PM'], true) || str_contains(strtoupper($code), 'NIGHT')) {
            return true;
        }
        if ($start && $end && (string) $end < (string) $start) {
            return true;
        }

        return false;
    }

    private function estimateWorkHours(?string $start, ?string $end, int $endDayOffset): ?float
    {
        if (! $start || ! $end) {
            return null;
        }

        try {
            $from = CarbonImmutable::parse('2000-01-01 '.$start);
            $to = CarbonImmutable::parse('2000-01-01 '.$end)->addDays($endDayOffset);
            $hours = $from->diffInMinutes($to) / 60;

            return $hours > 0 ? round($hours, 2) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
