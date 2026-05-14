<?php

namespace App\Livewire\Schedule;

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
    public string $viewMode = 'table';
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
            ? MonthlySchedule::with('assignments.shiftCode', 'assignments.employee')
                ->where('department_id', $this->department_id)
                ->find($this->selectedScheduleId)
            : MonthlySchedule::with('assignments.shiftCode', 'assignments.employee')
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
        ]);
    }

    public function generate(ScheduleDraftGenerationService $service): void
    {
        $data = $this->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'schedule_template_id' => ['nullable', 'integer'],
        ]);

        $result = $service->generate($data['year'], $data['month'], $this->department_id, $data['schedule_template_id'], auth()->user()?->emp_id ?? 'web');
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

    private function calendar(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $initialLengths = $this->employeeInitialLengths($schedule);

        return $schedule->assignments
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

        return $schedule->assignments
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

        return $schedule->assignments
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
        $days = [];

        while ($date <= $endDate) {
            $days[] = [
                'key' => $date->toDateString(),
                'day' => $date->format('j'),
                'weekday' => $date->format('D'),
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

        return $schedule->assignments
            ->when($this->employee_filter, fn ($assignments) => $assignments->where('employee_id', $this->employee_filter))
            ->when($this->shift_filter, fn ($assignments) => $assignments->where('shift_code_id', (int) $this->shift_filter))
            ->groupBy('employee_id')
            ->map(function ($assignments) {
                $firstAssignment = $assignments->first();

                return [
                    'employee_id' => $firstAssignment->employee_id,
                    'employee_name' => $this->formatEmployeeName($firstAssignment->employee, $initialLengths[$firstAssignment->employee_id] ?? 1),
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

    private function employeeInitialLengths(?MonthlySchedule $schedule): array
    {
        if (! $schedule) {
            return [];
        }

        $employees = $schedule->assignments
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
}
