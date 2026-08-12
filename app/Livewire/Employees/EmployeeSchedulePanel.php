<?php

namespace App\Livewire\Employees;

use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Livewire\Component;

class EmployeeSchedulePanel extends Component
{
    public string $empId;

    public ?int $default_shift_code_id = null;

    public bool $can_rotate_shift = false;

    public bool $uses_regular_weekday_schedule = true;

    public bool $is_active = true;

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('schedule.view') || auth()->user()?->can('schedule.manage'),
            403
        );
        $this->empId = $empId;
        $this->loadForm();
    }

    public function updatedUsesRegularWeekdaySchedule(bool $value): void
    {
        if ($value) {
            $this->can_rotate_shift = false;
            $this->default_shift_code_id = null;
        }
    }

    public function updatedCanRotateShift(bool $value): void
    {
        if ($value) {
            $this->uses_regular_weekday_schedule = false;
        }
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()?->can('schedule.manage'), 403);

        $data = $this->validate([
            'default_shift_code_id' => ['nullable', 'integer', 'exists:payroll_scheduler.shift_codes,id'],
            'can_rotate_shift' => ['required', 'boolean'],
            'uses_regular_weekday_schedule' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['uses_regular_weekday_schedule']) {
            $data['can_rotate_shift'] = false;
            $data['default_shift_code_id'] = null;
        }

        EmployeeScheduleSetting::updateOrCreate(
            ['employee_id' => $this->empId],
            $data + [
                'max_consecutive_duty_days' => 5,
                'max_night_shifts_per_month' => 7,
            ],
        );

        $this->loadForm();
        session()->flash('status', 'Schedule settings saved.');
    }

    public function render()
    {
        $from = CarbonImmutable::today()->subDays(14)->toDateString();
        $to = CarbonImmutable::today()->addDays(21)->toDateString();

        $assignments = ScheduleAssignment::query()
            ->with(['shiftCode', 'monthlySchedule'])
            ->where('employee_id', $this->empId)
            ->whereDate('schedule_date', '>=', $from)
            ->whereDate('schedule_date', '<=', $to)
            ->orderBy('schedule_date')
            ->limit(40)
            ->get();

        $setting = EmployeeScheduleSetting::query()
            ->with(['defaultShiftCode'])
            ->where('employee_id', $this->empId)
            ->first();

        return view('livewire.employees.employee-schedule-panel', [
            'assignments' => $assignments,
            'setting' => $setting,
            'from' => $from,
            'to' => $to,
            'shiftCodes' => ShiftCode::query()
                ->where('is_active', true)
                ->where('is_work_shift', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'canManage' => (bool) auth()->user()?->can('schedule.manage'),
        ]);
    }

    private function loadForm(): void
    {
        $setting = EmployeeScheduleSetting::query()
            ->where('employee_id', $this->empId)
            ->first();

        $this->default_shift_code_id = $setting?->default_shift_code_id;
        $this->can_rotate_shift = (bool) ($setting?->can_rotate_shift ?? false);
        $this->uses_regular_weekday_schedule = (bool) ($setting?->uses_regular_weekday_schedule ?? true);
        $this->is_active = (bool) ($setting?->is_active ?? true);
    }
}
