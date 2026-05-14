<?php

namespace App\Livewire\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\ShiftCode;
use Livewire\Component;

class Employees extends Component
{
    public array $settings = [];
    public array $dirty = [];

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function render()
    {
        $departmentId = $this->departmentId();
        $employees = Employee::query()
            ->with('position')
            ->where('department_id', $departmentId)
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'department_id', 'position_id', 'step']);

        return view('livewire.schedule.employees', [
            'department' => auth()->user()?->employee?->department,
            'employees' => $employees,
            'shiftCodes' => ShiftCode::where('is_active', true)->where('is_work_shift', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function updatedSettings(mixed $value, string $key): void
    {
        [$employeeId, $field] = explode('.', $key, 2);

        if ($field === 'uses_regular_weekday_schedule') {
            $this->settings[$employeeId]['can_rotate_shift'] = !$value;
        }

        if ($field === 'can_rotate_shift') {
            $this->settings[$employeeId]['uses_regular_weekday_schedule'] = !$value;
        }
    }

    public function updatedSettingsUsesRegularWeekdaySchedule(string $employeeId): void
    {
        $usesRegular = $this->settings[$employeeId]['uses_regular_weekday_schedule'] ?? false;
        $this->settings[$employeeId]['can_rotate_shift'] = !$usesRegular;
    }

    public function updatedSettingsCanRotateShift(string $employeeId): void
    {
        $canRotate = $this->settings[$employeeId]['can_rotate_shift'] ?? false;
        $this->settings[$employeeId]['uses_regular_weekday_schedule'] = !$canRotate;
    }

    public function save(string $employeeId): void
    {
        $employee = Employee::query()
            ->where('department_id', $this->departmentId())
            ->where('emp_id', $employeeId)
            ->firstOrFail();

        $data = validator($this->settings[$employeeId] ?? [], [
            'default_shift_code_id' => ['nullable', 'integer', 'exists:payroll_scheduler.shift_codes,id'],
            'can_rotate_shift' => ['required', 'boolean'],
            'uses_regular_weekday_schedule' => ['required', 'boolean'],
        ])->validate();

        if ($data['uses_regular_weekday_schedule']) {
            $data['can_rotate_shift'] = false;
            $data['default_shift_code_id'] = null;
        }

        EmployeeScheduleSetting::updateOrCreate(
            ['employee_id' => $employee->emp_id],
            $data + [
                'max_consecutive_duty_days' => 5,
                'max_night_shifts_per_month' => 7,
                'is_active' => true,
            ],
        );

        $this->settings[$employeeId] = [
            'default_shift_code_id' => $data['default_shift_code_id'] ?? null,
            'can_rotate_shift' => (bool) $data['can_rotate_shift'],
            'uses_regular_weekday_schedule' => (bool) $data['uses_regular_weekday_schedule'],
        ];

        session()->flash('status', 'Employee schedule settings saved.');
    }

    public function markDirty(string $employeeId): void
    {
        $this->dirty[$employeeId] = true;
    }

    public function saveAll(): void
    {
        $ids = empty($this->dirty) ? array_keys($this->settings) : array_keys($this->dirty);

        foreach ($ids as $employeeId) {
            $this->save($employeeId);
        }

        $this->dirty = [];
        session()->flash('status', count($ids) . ' record(s) saved.');
    }

    private function loadSettings(): void
    {
        $employeeIds = Employee::query()
            ->where('department_id', $this->departmentId())
            ->where('is_active', 'Y')
            ->pluck('emp_id');

        $existing = EmployeeScheduleSetting::query()
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        foreach ($employeeIds as $employeeId) {
            $setting = $existing->get($employeeId);

            $this->settings[$employeeId] = [
                'default_shift_code_id' => $setting?->default_shift_code_id,
                'can_rotate_shift' => (bool) ($setting?->can_rotate_shift ?? false),
                'uses_regular_weekday_schedule' => (bool) ($setting?->uses_regular_weekday_schedule ?? true),
            ];
        }
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }

    public function employeeName(Employee $employee): string
    {
        $middleInitial = $employee->middlename ? mb_substr($employee->middlename, 0, 1).'.' : null;

        return implode(' ', array_filter([
            $employee->lastname.',',
            $employee->firstname,
            $middleInitial,
        ]));
    }

    public function salaryGradeStep(Employee $employee): string
    {
        $salaryGrade = $employee->position?->salary_grade;
        $step = $employee->step;

        if (! $salaryGrade && ! $step) {
            return '-';
        }

        return 'SG '.($salaryGrade ?? '-').' / Step '.($step ?? '-');
    }
}