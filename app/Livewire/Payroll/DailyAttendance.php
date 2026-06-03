<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Hris\EmployeeLeave;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

class DailyAttendance extends Component
{
    #[Url(as: 'date', except: '')]
    public string $date = '';

    #[Url(as: 'type', except: Employee::EMPLOYEE_TYPE_PLANTILLA)]
    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = CarbonImmutable::today()->toDateString();
        }
    }

    public function render()
    {
        $employees = $this->employees();
        $employeeIds = $employees->pluck('emp_id')->all();
        $assignments = $this->dailyAssignments($employeeIds);
        $holiday = $this->holidayFor($this->date);
        $settings = $this->regularWeekdaySettings($employeeIds);
        $leaves = $this->leaves($employeeIds, $this->date, $this->date);
        $useCalendarFallback = $assignments->isEmpty() && $settings->isEmpty();

        $dtrDates = $assignments->pluck('schedule_date')
            ->map(fn ($date) => $date->toDateString())
            ->push($this->date)
            ->unique()
            ->all();
        $dtrs = EmployeeDtr::query()
            ->whereIn('emp_id', $employeeIds)
            ->whereIn('dtr_date', $dtrDates)
            ->get()
            ->keyBy(fn (EmployeeDtr $dtr) => $this->key($dtr->emp_id, $dtr->dtr_date->toDateString()));

        $rows = $assignments->map(function (ScheduleAssignment $assignment) use ($dtrs, $leaves) {
            $date = $assignment->schedule_date->toDateString();
            $dtr = $dtrs->get($this->key($assignment->employee_id, $date));
            $leave = $leaves->get($this->key($assignment->employee_id, $date));
            $status = $this->statusForAssignment($assignment, $dtr, $leave);

            return [
                'employee' => $assignment->employee,
                'dtr' => $dtr,
                'shift' => $assignment->shiftCode,
                'duty_date' => $date,
                'span' => $leave && ! $dtr ? $date.' - '.$leave['name'] : $this->shiftSpanLabel($assignment),
                'status' => $status,
                'first_in' => $this->timeIn($dtr),
                'last_out' => $this->timeOut($dtr),
                'worked_minutes' => $this->workedMinutes($dtr),
            ];
        });

        $assignedEmployeeIds = $assignments->pluck('employee_id')->unique();
        $regularRows = $employees
            ->reject(fn (Employee $employee) => $assignedEmployeeIds->contains($employee->emp_id))
            ->filter(fn (Employee $employee) => $useCalendarFallback || $this->shouldShowRegularEmployee($employee, $settings, $holiday))
            ->map(function (Employee $employee) use ($dtrs, $holiday, $leaves, $useCalendarFallback) {
                $dtr = $dtrs->get($this->key($employee->emp_id, $this->date));
                $leave = $leaves->get($this->key($employee->emp_id, $this->date));
                $status = match (true) {
                    $leave && ! $dtr => 'Leave',
                    $holiday && ! $dtr && ! $useCalendarFallback => 'Holiday',
                    default => $this->status($dtr),
                };

                return [
                    'employee' => $employee,
                    'dtr' => $dtr,
                    'shift' => null,
                    'duty_date' => $this->date,
                    'span' => $leave && ! $dtr
                        ? CarbonImmutable::parse($this->date)->format('M d').' - '.$leave['name']
                        : ($holiday && ! $useCalendarFallback
                        ? CarbonImmutable::parse($this->date)->format('M d').' - '.$holiday->name
                        : CarbonImmutable::parse($this->date)->format('M d').($useCalendarFallback ? '' : ' Regular Mon-Fri')),
                    'status' => $status,
                    'first_in' => $this->timeIn($dtr),
                    'last_out' => $this->timeOut($dtr),
                    'worked_minutes' => $this->workedMinutes($dtr),
                ];
            });

        $rows = $rows->concat($regularRows);

        $rows = $rows->sortBy([
            fn (array $row) => $row['employee']?->lastname ?? '',
            fn (array $row) => $row['duty_date'],
        ])->values();

        return view('livewire.payroll.daily-attendance', [
            'department' => auth()->user()?->employee?->department,
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'rows' => $rows,
            'summary' => [
                'employees' => $rows->pluck('employee.emp_id')->unique()->count(),
                'present' => $rows->where('status', 'Present')->count(),
                'incomplete' => $rows->where('status', 'Incomplete')->count(),
                'absent' => $rows->where('status', 'Absent')->count(),
                'holiday' => $rows->where('status', 'Holiday')->count(),
                'leave' => $rows->where('status', 'Leave')->count(),
                'off' => $rows->where('status', 'Off')->count(),
            ],
        ]);
    }

    private function employees()
    {
        $search = trim($this->search);

        return Employee::query()
            ->with('position')
            ->where('department_id', $this->departmentId())
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                        ->orWhere('middlename', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'position_id', 'department_id']);
    }

    private function status(?EmployeeDtr $dtr, ?array $leave = null): string
    {
        if (! $dtr) {
            return $leave ? 'Leave' : 'Absent';
        }

        return $this->timeIn($dtr) && $this->timeOut($dtr) ? 'Present' : 'Incomplete';
    }

    private function timeIn(?EmployeeDtr $dtr): ?CarbonImmutable
    {
        if (! $dtr) {
            return null;
        }

        $time = $dtr->timein_am ?: $dtr->timein_pm;

        return $time ? CarbonImmutable::parse($dtr->dtr_date->toDateString().' '.$time) : null;
    }

    private function timeOut(?EmployeeDtr $dtr): ?CarbonImmutable
    {
        if (! $dtr) {
            return null;
        }

        if ($dtr->timeout_nextday) {
            return CarbonImmutable::parse($dtr->timeout_nextday);
        }

        $time = $dtr->timeout_pm ?: $dtr->timeout_am;
        if (! $time) {
            return null;
        }

        $output = CarbonImmutable::parse($dtr->dtr_date->toDateString().' '.$time);
        $input = $this->timeIn($dtr);

        return $input && $output->lessThan($input) ? $output->addDay() : $output;
    }

    private function workedMinutes(?EmployeeDtr $dtr): int
    {
        $start = $this->timeIn($dtr);
        $end = $this->timeOut($dtr);

        return $start && $end && $end->greaterThan($start)
            ? (int) $start->diffInMinutes($end)
            : 0;
    }

    public function formatTime(?CarbonImmutable $value, string $dutyDate): string
    {
        if (! $value) {
            return '-';
        }

        $date = CarbonImmutable::parse($dutyDate);

        return $value->isSameDay($date) ? $value->format('h:i A') : $value->format('M d, h:i A');
    }

    public function key(string $employeeId, string $date): string
    {
        return $employeeId.'|'.$date;
    }

    private function dailyAssignments(array $employeeIds)
    {
        $date = CarbonImmutable::parse($this->date);

        return ScheduleAssignment::query()
            ->with(['employee.position', 'shiftCode'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('schedule_date', [$date->subDays(3)->toDateString(), $date->toDateString()])
            ->get()
            ->filter(fn (ScheduleAssignment $assignment) => $this->assignmentTouchesDate($assignment, $date))
            ->values();
    }

    private function statusForAssignment(ScheduleAssignment $assignment, ?EmployeeDtr $dtr, ?array $leave): string
    {
        if ($dtr) {
            return $this->status($dtr, $leave);
        }

        if ($leave || $assignment->shiftCode?->is_leave_code) {
            return 'Leave';
        }

        if (! $assignment->shiftCode?->is_work_shift) {
            return 'Off';
        }

        return 'Absent';
    }

    private function shouldShowRegularEmployee(Employee $employee, $settings, ?PayrollHoliday $holiday): bool
    {
        $setting = $settings->get($employee->emp_id);

        if (! $setting?->uses_regular_weekday_schedule) {
            return false;
        }

        $date = CarbonImmutable::parse($this->date);

        return $holiday || $date->isWeekday();
    }

    private function regularWeekdaySettings(array $employeeIds)
    {
        return EmployeeScheduleSetting::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->where('uses_regular_weekday_schedule', true)
            ->get()
            ->keyBy('employee_id');
    }

    private function holidayFor(string $date): ?PayrollHoliday
    {
        return PayrollHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', $date)
            ->first();
    }

    private function leaves(array $employeeIds, string $from, string $to)
    {
        $dates = collect();

        EmployeeLeave::query()
            ->with('leaveType')
            ->whereIn('emp_id', $employeeIds)
            ->where('status', 0)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get()
            ->each(function (EmployeeLeave $leave) use ($from, $to, $dates) {
                $start = CarbonImmutable::parse($leave->start_date);
                $fromDate = CarbonImmutable::parse($from);
                if ($start->lessThan($fromDate)) {
                    $start = $fromDate;
                }

                $end = CarbonImmutable::parse($leave->end_date);
                $toDate = CarbonImmutable::parse($to);
                if ($end->greaterThan($toDate)) {
                    $end = $toDate;
                }
                $name = $leave->leave_type_name ?: $leave->leaveType?->leave_name ?: 'Leave';

                for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                    $dates->put($this->key($leave->emp_id, $date->toDateString()), [
                        'name' => $name,
                        'paid' => (float) $leave->days_wpay > 0,
                    ]);
                }
            });

        return $dates;
    }

    private function assignmentTouchesDate(ScheduleAssignment $assignment, CarbonImmutable $date): bool
    {
        [$start, $end] = $this->assignmentSpan($assignment);

        return $date->betweenIncluded($start->startOfDay(), $end->endOfDay());
    }

    private function assignmentSpan(ScheduleAssignment $assignment): array
    {
        $shift = $assignment->shiftCode;
        if (! $shift?->is_work_shift || ! $shift->start_time || ! $shift->end_time) {
            $date = CarbonImmutable::parse($assignment->schedule_date);

            return [$date->startOfDay(), $date->endOfDay()];
        }

        $start = CarbonImmutable::parse($assignment->schedule_date->toDateString().' '.($shift?->start_time ?: '00:00:00'));
        $end = CarbonImmutable::parse($assignment->schedule_date->toDateString().' '.($shift?->end_time ?: '23:59:59'))
            ->addDays((int) ($shift?->end_day_offset ?? 0));

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    private function shiftSpanLabel(ScheduleAssignment $assignment): string
    {
        if (! $assignment->shiftCode?->is_work_shift || ! $assignment->shiftCode?->start_time || ! $assignment->shiftCode?->end_time) {
            return CarbonImmutable::parse($assignment->schedule_date)->format('M d').' - '.($assignment->shiftCode?->name ?? 'Off Duty');
        }

        [$start, $end] = $this->assignmentSpan($assignment);

        return $start->isSameDay($end)
            ? $start->format('M d, h:i A').' - '.$end->format('h:i A')
            : $start->format('M d, h:i A').' - '.$end->format('M d, h:i A');
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
