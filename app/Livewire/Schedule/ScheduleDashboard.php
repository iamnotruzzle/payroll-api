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
use App\Services\Schedule\ScheduleDraftGenerationService;
use App\Services\Schedule\ScheduleLockService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ScheduleDashboard extends Component
{
    public int $year;

    public int $month;

    public ?int $department_id = null;

    public ?int $schedule_template_id = null;

    public ?int $selectedScheduleId = null;

    public ?string $employee_filter = null;

    public ?string $shift_filter = null;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public string $viewMode = 'table';

    public bool $showConflicts = true;

    public array $conflicts = [];

    public function mount(): void
    {
        $nextMonth = now()->addMonth();
        $this->year = (int) $nextMonth->format('Y');
        $this->month = (int) $nextMonth->format('n');
        $this->department_id = auth()->user()?->employee?->department_id;
    }

    public function render()
    {
        $schedule = $this->selectedScheduleId
            ? MonthlySchedule::with('assignments.shiftCode', 'assignments.employee.department.division')
                ->where('department_id', $this->department_id)
                ->find($this->selectedScheduleId)
            : MonthlySchedule::with('assignments.shiftCode', 'assignments.employee.department.division')
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
                ->latest('id')
                ->first();

        return view('livewire.schedule.schedule-dashboard', [
            'department' => auth()->user()?->employee?->department,
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
            'schedules' => MonthlySchedule::where('department_id', $this->department_id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->limit(12)
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

    public function generate(ScheduleDraftGenerationService $service): void
    {
        $data = $this->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'schedule_template_id' => ['nullable', 'integer'],
            'employeeTypeFilter' => ['required', Rule::in(array_keys(Employee::employeeTypeOptions()))],
        ]);

        $result = $service->generate($data['year'], $data['month'], $this->department_id, $data['schedule_template_id'], auth()->user()?->emp_id ?? 'web', $data['employeeTypeFilter']);
        $this->selectedScheduleId = $result['schedule']->id;
        $this->conflicts = $result['conflicts'];
        session()->flash('status', 'Draft schedule generated.');
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

    public function toggleConflicts(): void
    {
        $this->showConflicts = ! $this->showConflicts;
    }

    public function applyEmployeePattern(string $employeeId, mixed $templateId): void
    {
        if (! $templateId) {
            return;
        }

        $service = app(ScheduleAssignmentService::class);
        $schedule = $this->currentSchedule();
        if (! $schedule || $schedule->isLocked()) {
            session()->flash('status', 'Locked schedules cannot be changed.');

            return;
        }

        $template = ScheduleTemplate::with('days.shiftCode')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('department_id')->orWhere('department_id', $this->department_id);
            })
            ->findOrFail((int) $templateId);

        $patternDays = $template->days->values();
        if ($patternDays->isEmpty()) {
            session()->flash('status', 'Selected pattern has no days.');

            return;
        }

        DB::connection('payroll_scheduler')->transaction(function () use ($employeeId, $patternDays, $schedule, $service): void {
            $assignments = ScheduleAssignment::with('monthlySchedule')
                ->where('monthly_schedule_id', $schedule->id)
                ->where('employee_id', $employeeId)
                ->orderBy('schedule_date')
                ->get();

            foreach ($assignments as $assignment) {
                $dayIndex = $patternDays->count() === 7
                    ? ((int) $assignment->schedule_date->isoWeekday()) - 1
                    : ((int) $assignment->schedule_date->format('j')) - 1;
                $patternDay = $patternDays[$dayIndex % $patternDays->count()];

                if ((int) $assignment->shift_code_id === (int) $patternDay->shift_code_id) {
                    continue;
                }

                $service->update($assignment, ['shift_code_id' => $patternDay->shift_code_id], auth()->user()?->emp_id ?? 'web');
            }
        });

        $this->selectedScheduleId = $schedule->id;
        $this->conflicts = [];
        session()->flash('status', 'Employee shift pattern applied.');
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
        return $schedule->assignments
            ->filter(fn ($assignment) => $this->employeeMatchesType($assignment->employee));
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
