<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleTemplate;
use App\Models\Schedule\ShiftCode;
use App\Services\Schedule\ScheduleApprovalService;
use App\Services\Schedule\ScheduleAssignmentService;
use App\Services\Schedule\ScheduleConflictValidator;
use App\Services\Schedule\ScheduleLockService;
use App\Services\Schedule\SchedulePatternFillService;
use App\Services\Schedule\ScheduleScopeService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class ScheduleDashboard extends Component
{
    public int $year;

    public int $month;

    public ?int $department_id = null;

    public ?int $schedule_template_id = null;

    public ?int $selectedScheduleId = null;

    public ?string $employee_filter = null;

    public ?string $shift_filter = null;

    public ?string $unit_filter = null;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public string $viewMode = 'table';

    public bool $showConflicts = true;

    public array $conflicts = [];

    public bool $showPatternPanel = true;

    public ?int $pattern_fill_template_id = null;

    public string $pattern_fill_date_from = '';

    public string $pattern_fill_date_to = '';

    public string $pattern_fill_scope = 'selected';

    /** @var list<string> */
    public array $selectedEmployeeIds = [];

    /** @var list<array<string, mixed>> */
    public array $patternPreviewChanges = [];

    /** @var array{total?: int, changed?: int, unchanged?: int, employees?: int} */
    public array $patternPreviewSummary = [];

    public function mount(MonthlySchedule $schedule): void
    {
        $departmentId = auth()->user()?->employee?->department_id;
        abort_unless($departmentId && (int) $schedule->department_id === (int) $departmentId, 403);

        $this->department_id = (int) $schedule->department_id;
        $this->selectedScheduleId = (int) $schedule->id;
        $this->year = (int) $schedule->year;
        $this->month = (int) $schedule->month;
        $this->schedule_template_id = $schedule->schedule_template_id ? (int) $schedule->schedule_template_id : null;
        $this->syncPatternFillDates();
    }

    public function updatedYear(): void
    {
        $this->syncPatternFillDates();
    }

    public function updatedMonth(): void
    {
        $this->syncPatternFillDates();
    }

    public function updatedSelectedScheduleId(): void
    {
        $schedule = $this->currentSchedule();
        if ($schedule) {
            $this->year = (int) $schedule->year;
            $this->month = (int) $schedule->month;
            $this->syncPatternFillDates();
        }
        $this->clearPatternPreview();
        $this->selectedEmployeeIds = [];
    }

    public function render(ScheduleScopeService $scopeService)
    {
        $schedule = MonthlySchedule::with('assignments.shiftCode', 'assignments.employee.department.division', 'assignments.unit')
            ->where('department_id', $this->department_id)
            ->find($this->selectedScheduleId);

        $profile = $scopeService->profileForDepartment($this->department_id);
        $handledUnitIds = $scopeService->handledUnitIds(auth()->user()?->emp_id, $this->department_id);
        $unitOptions = $scopeService->unitsForDepartment($this->department_id);
        $isCno = $scopeService->isCnoDepartment($this->department_id);

        return view('livewire.schedule.schedule-dashboard', [
            'department' => auth()->user()?->employee?->department,
            'profile' => $profile,
            'isCno' => $isCno,
            'modeLabel' => $scopeService->modeLabelForDepartment($this->department_id),
            'unitNoun' => $scopeService->unitNoun($this->department_id),
            'unitNounPlural' => $scopeService->unitNoun($this->department_id, true),
            'unitOptions' => $unitOptions,
            'handledUnitIds' => $handledUnitIds,
            'templates' => ScheduleTemplate::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
                })
                ->orderBy('name')
                ->get(),
            'rowPatternOptions' => ScheduleTemplate::with('days')
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
                })
                ->orderBy('name')
                ->get(),
            'schedule' => $schedule,
            'employeeOptions' => $this->employeeOptions($schedule),
            'shiftOptions' => $this->shiftOptions($schedule),
            'shiftCodeOptions' => $this->shiftCodeOptions(),
            'calendar' => $this->calendar($schedule),
            'tableDays' => $this->tableDays($schedule),
            'scheduleTable' => $this->scheduleTable($schedule),
            'dailyShiftSummary' => $this->dailyShiftSummary($schedule),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
        ]);
    }

    public function generate(): void
    {
        throw ValidationException::withMessages([
            'generate' => 'Draft generation is only available from the Schedules list. CNO departments must import from NDOS.',
        ]);
    }

    public function validateSchedule(ScheduleConflictValidator $validator): void
    {
        $schedule = MonthlySchedule::where('department_id', $this->department_id)->findOrFail($this->selectedScheduleId);
        $this->conflicts = $validator->validate($schedule);
    }

    public function review(ScheduleApprovalService $service): void
    {
        $service->review(MonthlySchedule::where('department_id', $this->department_id)->findOrFail($this->selectedScheduleId), auth()->user()?->emp_id ?? 'web');
        session()->flash('status', 'Schedule marked for approval.');
    }

    public function approve(ScheduleApprovalService $service): void
    {
        $service->approve(MonthlySchedule::where('department_id', $this->department_id)->findOrFail($this->selectedScheduleId), auth()->user()?->emp_id ?? 'web');
        session()->flash('status', 'Schedule approved.');
    }

    public function lock(ScheduleLockService $service): void
    {
        $service->lock(MonthlySchedule::where('department_id', $this->department_id)->findOrFail($this->selectedScheduleId), auth()->user()?->emp_id ?? 'web');
        session()->flash('status', 'Schedule locked.');
    }

    public function updateAssignmentShift(int $assignmentId, int $shiftCodeId, ScheduleAssignmentService $service): void
    {
        $assignment = ScheduleAssignment::with('monthlySchedule')
            ->whereHas('monthlySchedule', fn ($query) => $query->where('department_id', $this->department_id))
            ->findOrFail($assignmentId);

        $this->assertAssignmentInScope($assignment);

        if ($assignment->monthlySchedule->isLocked()) {
            session()->flash('status', 'Locked schedules cannot be changed.');

            return;
        }

        $shiftCode = ShiftCode::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
            })
            ->findOrFail($shiftCodeId);

        $service->update($assignment, ['shift_code_id' => $shiftCode->id], auth()->user()?->emp_id ?? 'web');
        $this->selectedScheduleId = $assignment->monthly_schedule_id;
        $this->conflicts = [];
        session()->flash('status', 'Schedule shift updated.');
    }

    public function updateAssignmentUnit(int $assignmentId, mixed $unitId, ScheduleAssignmentService $service, ScheduleScopeService $scopeService): void
    {
        $profile = $scopeService->profileForDepartment($this->department_id);
        if (! $profile->uses_units) {
            return;
        }

        $assignment = ScheduleAssignment::with('monthlySchedule')
            ->whereHas('monthlySchedule', fn ($query) => $query->where('department_id', $this->department_id))
            ->findOrFail($assignmentId);

        $this->assertAssignmentInScope($assignment);

        if ($assignment->monthlySchedule->isLocked()) {
            session()->flash('status', 'Locked schedules cannot be changed.');

            return;
        }

        $normalizedUnitId = $unitId === '' || $unitId === null ? null : (int) $unitId;
        if ($normalizedUnitId !== null) {
            $allowed = $scopeService->unitsForDepartment($this->department_id)->pluck('id')->all();
            abort_unless(in_array($normalizedUnitId, $allowed, true), 404);

            $handled = $scopeService->handledUnitIds(auth()->user()?->emp_id, $this->department_id);
            if ($handled !== null) {
                abort_unless(in_array($normalizedUnitId, $handled, true), 403);
            }
        }

        $service->update($assignment, ['unit_id' => $normalizedUnitId], auth()->user()?->emp_id ?? 'web');
        $this->selectedScheduleId = $assignment->monthly_schedule_id;
        session()->flash('status', 'Assignment unit updated.');
    }

    public function toggleTemporaryFloater(int $assignmentId, ScheduleAssignmentService $service, ScheduleScopeService $scopeService): void
    {
        $profile = $scopeService->profileForDepartment($this->department_id);
        if (! $profile->uses_floaters) {
            return;
        }

        $assignment = ScheduleAssignment::with('monthlySchedule')
            ->whereHas('monthlySchedule', fn ($query) => $query->where('department_id', $this->department_id))
            ->findOrFail($assignmentId);

        $this->assertAssignmentInScope($assignment);

        if ($assignment->monthlySchedule->isLocked()) {
            session()->flash('status', 'Locked schedules cannot be changed.');

            return;
        }

        $service->update(
            $assignment,
            ['is_temporary_floater' => ! $assignment->is_temporary_floater],
            auth()->user()?->emp_id ?? 'web'
        );
        $this->selectedScheduleId = $assignment->monthly_schedule_id;
        session()->flash('status', $assignment->fresh()->is_temporary_floater ? 'Marked as temporary floater.' : 'Temporary floater cleared.');
    }

    public function toggleConflicts(): void
    {
        $this->showConflicts = ! $this->showConflicts;
    }

    public function toggleEmployeeSelection(string $employeeId): void
    {
        if (in_array($employeeId, $this->selectedEmployeeIds, true)) {
            $this->selectedEmployeeIds = array_values(array_filter(
                $this->selectedEmployeeIds,
                fn (string $id) => $id !== $employeeId
            ));
        } else {
            $this->selectedEmployeeIds[] = $employeeId;
        }
    }

    public function selectVisibleEmployees(): void
    {
        $schedule = $this->currentSchedule();
        if (! $schedule) {
            return;
        }

        $this->selectedEmployeeIds = collect($this->scheduleTable($schedule))
            ->pluck('employee_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function clearEmployeeSelection(): void
    {
        $this->selectedEmployeeIds = [];
    }

    public function clearPatternPreview(): void
    {
        $this->patternPreviewChanges = [];
        $this->patternPreviewSummary = [];
    }

    public function previewPatternFill(SchedulePatternFillService $service): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        try {
            [$schedule, $template, $employeeIds] = $this->patternFillContext();
            $result = $service->preview(
                $schedule,
                $template,
                $employeeIds,
                $this->pattern_fill_date_from ?: null,
                $this->pattern_fill_date_to ?: null,
            );
            $this->patternPreviewChanges = array_values(array_filter(
                $result['changes'],
                fn (array $change) => $change['will_change']
            ));
            $this->patternPreviewSummary = $result['summary'];
            session()->flash(
                'status',
                sprintf(
                    'Pattern preview: %d change(s) across %d employee(s) (%d already matched).',
                    $result['summary']['changed'],
                    $result['summary']['employees'],
                    $result['summary']['unchanged']
                )
            );
        } catch (RuntimeException $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    public function applyPatternFill(SchedulePatternFillService $service): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        try {
            [$schedule, $template, $employeeIds] = $this->patternFillContext();
            $result = $service->apply(
                $schedule,
                $template,
                $employeeIds,
                $this->pattern_fill_date_from ?: null,
                $this->pattern_fill_date_to ?: null,
                auth()->user()?->emp_id ?? 'web',
            );
            $this->selectedScheduleId = $schedule->id;
            $this->conflicts = [];
            $this->clearPatternPreview();
            session()->flash(
                'status',
                sprintf('Pattern applied: %d assignment(s) updated (%d unchanged).', $result['applied'], $result['unchanged'])
            );
        } catch (RuntimeException $e) {
            session()->flash('status', $e->getMessage());
        }
    }

    /**
     * Quick row action: load employee into pattern panel and preview.
     */
    public function applyEmployeePattern(string $employeeId, mixed $templateId, SchedulePatternFillService $service): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        if (! $templateId) {
            return;
        }

        $this->pattern_fill_template_id = (int) $templateId;
        $this->pattern_fill_scope = 'selected';
        $this->selectedEmployeeIds = [$employeeId];
        $this->showPatternPanel = true;
        $this->syncPatternFillDates();
        $this->previewPatternFill($service);
    }

    /**
     * @return array{0: MonthlySchedule, 1: ScheduleTemplate, 2: list<string>|null}
     */
    private function patternFillContext(): array
    {
        $schedule = $this->currentSchedule();
        if (! $schedule) {
            throw new RuntimeException('Select or generate a schedule first.');
        }
        if ($schedule->isLocked()) {
            throw new RuntimeException('Locked schedules cannot be changed.');
        }

        $data = $this->validate([
            'pattern_fill_template_id' => ['required', 'integer'],
            'pattern_fill_date_from' => ['nullable', 'date'],
            'pattern_fill_date_to' => ['nullable', 'date'],
            'pattern_fill_scope' => ['required', Rule::in(['selected', 'filtered', 'all'])],
        ]);

        $template = ScheduleTemplate::with('days.shiftCode')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
            })
            ->findOrFail((int) $data['pattern_fill_template_id']);

        $employeeIds = match ($data['pattern_fill_scope']) {
            'selected' => $this->selectedEmployeeIds === []
                ? throw new RuntimeException('Select one or more employees (checkboxes) or change scope to Filtered / All.')
                : $this->selectedEmployeeIds,
            'filtered' => collect($this->scheduleTable($schedule))->pluck('employee_id')->map(fn ($id) => (string) $id)->unique()->values()->all(),
            default => null,
        };

        return [$schedule, $template, $employeeIds];
    }

    private function syncPatternFillDates(): void
    {
        if ($this->year < 2020 || $this->month < 1 || $this->month > 12) {
            return;
        }

        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $this->pattern_fill_date_from = $start->toDateString();
        $this->pattern_fill_date_to = $start->endOfMonth()->toDateString();
    }

    private function calendar(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $initialLengths = $this->employeeInitialLengths($schedule);

        return $this->filteredAssignments($schedule)
            ->when($this->employee_filter, fn ($assignments) => $assignments->where('employee_id', $this->employee_filter))
            ->when($this->shift_filter, fn ($assignments) => $assignments->where('shift_code_id', (int) $this->shift_filter))
            ->groupBy(fn ($assignment) => $assignment->schedule_date->toDateString())
            ->map(fn ($assignments) => $assignments->take(8)->map(fn ($assignment) => [
                'employee_id' => $assignment->employee_id,
                'employee_name' => $this->formatEmployeeName($assignment->employee, $initialLengths[$assignment->employee_id] ?? 1),
                'code' => $assignment->shiftCode?->code,
                'night' => (bool) $assignment->shiftCode?->is_night_shift,
            ])->values()->all())
            ->all();
    }

    private function employeeOptions(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $initialLengths = $this->employeeInitialLengths($schedule);

        return $this->filteredAssignments($schedule)
            ->unique('employee_id')
            ->sortBy(fn ($assignment) => $this->formatEmployeeName($assignment->employee, $initialLengths[$assignment->employee_id] ?? 1))
            ->map(fn ($assignment) => [
                'id' => $assignment->employee_id,
                'name' => $this->formatEmployeeName($assignment->employee, $initialLengths[$assignment->employee_id] ?? 1),
            ])
            ->values()
            ->all();
    }

    private function shiftOptions(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        return $this->filteredAssignments($schedule)
            ->filter(fn ($assignment) => $assignment->shiftCode)
            ->unique('shift_code_id')
            ->sortBy(fn ($assignment) => $assignment->shiftCode?->code)
            ->map(fn ($assignment) => [
                'id' => $assignment->shift_code_id,
                'code' => $assignment->shiftCode?->code,
                'name' => $assignment->shiftCode?->name,
            ])
            ->values()
            ->all();
    }

    private function shiftCodeOptions(): array
    {
        return ShiftCode::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
            })
            ->orderBy('code')
            ->get()
            ->map(fn (ShiftCode $shiftCode) => [
                'id' => $shiftCode->id,
                'code' => $shiftCode->code,
                'name' => $shiftCode->name,
            ])
            ->all();
    }

    private function tableDays(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $date = CarbonImmutable::create($schedule->year, $schedule->month, 1);
        $endDate = $date->endOfMonth();
        $holidays = PayrollHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$date->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn (PayrollHoliday $holiday) => $holiday->holiday_date->toDateString());
        $days = [];

        while ($date <= $endDate) {
            $holiday = $holidays->get($date->toDateString());
            $days[] = [
                'key' => $date->toDateString(),
                'day' => $date->format('j'),
                'weekday' => $date->format('D'),
                'week_key' => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
                'ends_week' => $date->isSunday() || $date->isSameDay($endDate),
                'holiday_label' => $holiday?->label_code,
                'holiday_name' => $holiday?->name,
            ];

            $date = $date->addDay();
        }

        return $days;
    }

    private function scheduleTable(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $initialLengths = $this->employeeInitialLengths($schedule);

        return $this->filteredAssignments($schedule)
            ->when($this->employee_filter, fn ($assignments) => $assignments->where('employee_id', $this->employee_filter))
            ->when($this->shift_filter, fn ($assignments) => $assignments->where('shift_code_id', (int) $this->shift_filter))
            ->groupBy('employee_id')
            ->map(function ($assignments) use ($initialLengths) {
                $firstAssignment = $assignments->first();

                return [
                    'employee_id' => $firstAssignment->employee_id,
                    'employee_name' => $this->formatEmployeeName($firstAssignment->employee, $initialLengths[$firstAssignment->employee_id] ?? 1),
                    'weekly_hours' => $this->weeklyHours($assignments),
                    'assignments' => $assignments
                        ->keyBy(fn ($assignment) => $assignment->schedule_date->toDateString())
                        ->map(fn ($assignment) => [
                            'id' => $assignment->id,
                            'shift_code_id' => $assignment->shift_code_id,
                            'unit_id' => $assignment->unit_id,
                            'unit_code' => $assignment->unit?->code,
                            'is_temporary_floater' => (bool) $assignment->is_temporary_floater,
                            'code' => $assignment->shiftCode?->code,
                            'night' => (bool) $assignment->shiftCode?->is_night_shift,
                        ])
                        ->all(),
                ];
            })
            ->sortBy('employee_name')
            ->values()
            ->all();
    }

    private function dailyShiftSummary(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        return $this->filteredAssignments($schedule)
            ->when($this->employee_filter, fn ($assignments) => $assignments->where('employee_id', $this->employee_filter))
            ->when($this->shift_filter, fn ($assignments) => $assignments->where('shift_code_id', (int) $this->shift_filter))
            ->groupBy(fn ($assignment) => $assignment->schedule_date->toDateString())
            ->map(function ($assignments, string $date) {
                return [
                    'date' => $date,
                    'day' => CarbonImmutable::parse($date)->format('j'),
                    'weekday' => CarbonImmutable::parse($date)->format('D'),
                    'total' => $assignments->count(),
                    'shifts' => $assignments
                        ->groupBy(fn ($assignment) => $assignment->shiftCode?->code ?? '-')
                        ->map(fn ($shiftAssignments, string $code) => [
                            'code' => $code,
                            'count' => $shiftAssignments->count(),
                        ])
                        ->sortBy('code')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function weeklyHours($assignments): array
    {
        return $assignments
            ->groupBy(fn ($assignment) => $assignment->schedule_date->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString())
            ->map(fn ($weekAssignments) => $weekAssignments
                ->filter(fn ($assignment) => (bool) $assignment->shiftCode?->is_work_shift)
                ->sum(fn ($assignment) => (float) ($assignment->shiftCode?->work_hours ?? 0)))
            ->all();
    }

    private function employeeInitialLengths(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $employees = $this->filteredAssignments($schedule)
            ->unique('employee_id')
            ->mapWithKeys(fn ($assignment) => [$assignment->employee_id => $assignment->employee])
            ->filter();

        $duplicateKeys = $employees
            ->groupBy(fn ($employee) => $this->employeeInitialCollisionKey($employee))
            ->filter(fn ($group) => $group->count() > 1)
            ->keys()
            ->all();

        return $employees
            ->mapWithKeys(fn ($employee, $employeeId) => [
                $employeeId => in_array($this->employeeInitialCollisionKey($employee), $duplicateKeys, true) ? 2 : 1,
            ])
            ->all();
    }

    private function employeeInitialCollisionKey($employee): string
    {
        return strtolower(implode('|', [
            trim((string) $employee?->lastname),
            mb_substr(trim((string) $employee?->firstname), 0, 1),
            $this->middleInitials($employee?->middlename),
        ]));
    }

    private function filteredAssignments(MonthlySchedule $schedule)
    {
        $handledUnitIds = app(ScheduleScopeService::class)
            ->handledUnitIds(auth()->user()?->emp_id, $this->department_id);

        return $schedule->assignments
            ->filter(fn ($assignment) => $this->employeeMatchesType($assignment->employee))
            ->when($handledUnitIds !== null, function ($assignments) use ($handledUnitIds) {
                return $assignments->filter(function ($assignment) use ($handledUnitIds) {
                    if ($assignment->unit_id === null) {
                        return true;
                    }

                    return in_array((int) $assignment->unit_id, $handledUnitIds, true);
                });
            })
            ->when($this->unit_filter !== null && $this->unit_filter !== '', function ($assignments) {
                return $assignments->where('unit_id', (int) $this->unit_filter);
            });
    }

    private function assertAssignmentInScope(ScheduleAssignment $assignment): void
    {
        $handledUnitIds = app(ScheduleScopeService::class)
            ->handledUnitIds(auth()->user()?->emp_id, $this->department_id);

        if ($handledUnitIds === null || $assignment->unit_id === null) {
            return;
        }

        abort_unless(
            in_array((int) $assignment->unit_id, $handledUnitIds, true),
            403
        );
    }

    private function employeeMatchesType(?Employee $employee): bool
    {
        if (! $employee) {
            return false;
        }

        if ($this->employeeTypeFilter === Employee::EMPLOYEE_TYPE_ALL) {
            return true;
        }

        $isExternalDivision = strtolower(trim((string) $employee->department?->division?->division)) === Employee::EXTERNAL_DIVISION_NAME;

        if ($this->employeeTypeFilter === Employee::EMPLOYEE_TYPE_EXTERNAL) {
            return $isExternalDivision;
        }

        if ($isExternalDivision) {
            return false;
        }

        return match ($this->employeeTypeFilter) {
            Employee::EMPLOYEE_TYPE_CASUAL => (int) $employee->empstat_id === Employee::EMPSTAT_CASUAL,
            Employee::EMPLOYEE_TYPE_PART_TIME => (int) $employee->empstat_id === Employee::EMPSTAT_PART_TIME,
            Employee::EMPLOYEE_TYPE_CONTRACTUAL => (int) $employee->empstat_id === Employee::EMPSTAT_CONTRACTUAL,
            Employee::EMPLOYEE_TYPE_TEMPORARY => (int) $employee->empstat_id === Employee::EMPSTAT_TEMPORARY,
            Employee::EMPLOYEE_TYPE_VISITING_CONSULTANT => (int) $employee->empstat_id === Employee::EMPSTAT_VISITING_CONSULTANT,
            Employee::EMPLOYEE_TYPE_COS => (int) $employee->empstat_id === Employee::EMPSTAT_CONTRACT_OF_SERVICE,
            Employee::EMPLOYEE_TYPE_PROBATIONARY => (int) $employee->empstat_id === Employee::EMPSTAT_PROBATIONARY,
            Employee::EMPLOYEE_TYPE_INTERN => (int) $employee->empstat_id === Employee::EMPSTAT_INTERN,
            default => (int) $employee->empstat_id === Employee::EMPSTAT_PERMANENT,
        };
    }

    private function formatEmployeeName($employee, int $firstNameInitialLength = 1): string
    {
        if (! $employee) {
            return 'Unknown employee';
        }

        $firstInitial = mb_substr(trim((string) $employee->firstname), 0, max(1, $firstNameInitialLength));
        $initials = $firstInitial ? $firstInitial.'.' : '';
        $initials .= $this->middleInitials($employee->middlename);

        return implode(' ', array_filter([
            $employee->lastname.',',
            $initials,
        ]));
    }

    private function middleInitials(?string $middleName): string
    {
        return collect(explode(' ', trim((string) $middleName)))
            ->filter()
            ->map(fn ($name) => mb_substr($name, 0, 1).'.')
            ->implode('');
    }

    private function currentSchedule(): ?MonthlySchedule
    {
        if ($this->selectedScheduleId) {
            return MonthlySchedule::where('department_id', $this->department_id)->find($this->selectedScheduleId);
        }

        return MonthlySchedule::where('year', $this->year)
            ->where('month', $this->month)
            ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
            ->latest('id')
            ->first();
    }
}
