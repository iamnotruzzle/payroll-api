<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Hris\EmployeeLeave;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

class AttendanceReport extends Component
{
    #[Url(as: 'year')]
    public int $year = 0;

    #[Url(as: 'month')]
    public int $month = 0;

    public string $from = '';

    public string $to = '';

    #[Url(as: 'type', except: Employee::EMPLOYEE_TYPE_PLANTILLA)]
    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public int $filterYear = 0;

    public int $filterMonth = 0;

    public string $filterEmployeeType = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public string $filterSearch = '';

    public function mount(): void
    {
        $today = CarbonImmutable::today();

        if ($this->year === 0) {
            $this->year = $today->year;
        }

        if ($this->month === 0) {
            $this->month = $today->month;
        }

        $this->setPeriodFromMonth($this->year, $this->month);

        $this->filterYear = $this->year;
        $this->filterMonth = $this->month;
        $this->filterEmployeeType = $this->employeeTypeFilter;
        $this->filterSearch = $this->search;
    }

    public function applyFilters(): void
    {
        $this->validate([
            'filterYear' => ['required', 'integer', 'between:2000,2100'],
            'filterMonth' => ['required', 'integer', 'between:1,12'],
            'filterEmployeeType' => ['required', 'string'],
            'filterSearch' => ['nullable', 'string'],
        ], attributes: [
            'filterYear' => 'year',
            'filterMonth' => 'month',
        ]);

        $this->year = $this->filterYear;
        $this->month = $this->filterMonth;
        $this->setPeriodFromMonth($this->year, $this->month);
        $this->employeeTypeFilter = $this->filterEmployeeType;
        $this->search = trim($this->filterSearch);
    }

    public function render()
    {
        $this->validatePeriod();

        $employees = $this->employees();
        $employeeIds = $employees->pluck('emp_id')->all();
        $assignments = $this->reportAssignments($employeeIds);
        $settings = $this->regularWeekdaySettings($employeeIds);
        $holidays = $this->holidays();
        $leaves = $this->leaves($employeeIds, $this->from, $this->to);

        $dates = $this->dates();
        $rows = $this->attendanceRows($employees, $assignments, $settings, $holidays, $leaves, $dates);
        $periodCount = count($dates);
        $usesSchedule = ! $assignments->isEmpty() || ! $settings->isEmpty();

        $rows = $rows->values();

        return view('livewire.payroll.attendance-report', [
            'department' => auth()->user()?->employee?->department,
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
            'rows' => $rows,
            'dates' => $dates,
            'dateCount' => $periodCount,
            'usesSchedule' => $usesSchedule,
            'summary' => [
                'employees' => $rows->count(),
                'scheduled' => $rows->sum('scheduled'),
                'present' => $rows->sum('present'),
                'incomplete' => $rows->sum('incomplete'),
                'absent' => $rows->sum('absent'),
                'holiday' => $rows->sum('holiday'),
                'leave' => $rows->sum('leave'),
                'off' => $rows->sum('off'),
                'worked_minutes' => $rows->sum('worked_minutes'),
            ],
        ]);
    }

    private function attendanceRows($employees, $assignments, $settings, $holidays, $leaves, array $dates)
    {
        $assignmentDates = $assignments->pluck('schedule_date')->map(fn ($date) => $date->toDateString());
        $dtrDates = $assignmentDates->merge($dates)->unique()->values()->all();

        $dtrs = EmployeeDtr::query()
            ->whereIn('emp_id', $employees->pluck('emp_id')->all())
            ->whereIn('dtr_date', $dtrDates)
            ->get()
            ->keyBy(fn (EmployeeDtr $dtr) => $this->key($dtr->emp_id, $dtr->dtr_date->toDateString()));
        $assignmentsByEmployee = $assignments
            ->groupBy('employee_id')
            ->map(fn ($employeeAssignments) => $employeeAssignments->keyBy(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString()));
        $useCalendarFallback = $assignments->isEmpty() && $settings->isEmpty();

        return $employees->map(function (Employee $employee) use ($assignmentsByEmployee, $settings, $holidays, $dates, $dtrs, $leaves, $useCalendarFallback) {
            $present = 0;
            $incomplete = 0;
            $workedMinutes = 0;
            $scheduled = 0;
            $holiday = 0;
            $leave = 0;
            $off = 0;
            $absent = 0;
            $days = [];
            $employeeAssignments = $assignmentsByEmployee->get($employee->emp_id, collect());
            $usesRegularWeekday = $settings->has($employee->emp_id);

            foreach ($dates as $date) {
                $dateValue = CarbonImmutable::parse($date);
                $dtr = $dtrs->get($this->key($employee->emp_id, $date));
                $leaveEntry = $leaves->get($this->key($employee->emp_id, $date));
                $assignment = $employeeAssignments->get($date);
                $cell = null;

                if ($assignment) {
                    if (! $assignment->shiftCode?->is_work_shift) {
                        if ($dtr && $this->timeIn($dtr) && $this->timeOut($dtr)) {
                            $present++;
                            $workedMinutes += $this->workedMinutes($dtr);

                            $cell = $this->dayCell('Present', 'Present');
                        } elseif ($leaveEntry || $assignment->shiftCode?->is_leave_code) {
                            $leave++;

                            $cell = $this->dayCell('Leave', $leaveEntry['name'] ?? 'Leave');
                        } else {
                            $off++;

                            $cell = $this->dayCell('Off', $assignment->shiftCode?->name ?? 'Off');
                        }
                    } else {
                        $scheduled++;

                        if (! $dtr) {
                            if ($leaveEntry) {
                                $leave++;

                                $cell = $this->dayCell('Leave', $leaveEntry['name']);
                            } else {
                                $absent++;

                                $cell = $this->dayCell('Absent', 'Absent');
                            }
                        } elseif ($this->timeIn($dtr) && $this->timeOut($dtr)) {
                            $present++;
                            $workedMinutes += $this->workedMinutes($dtr);

                            $cell = $this->dayCell('Present', $this->hoursLabel($dtr));
                        } else {
                            $incomplete++;
                            $workedMinutes += $this->workedMinutes($dtr);

                            $cell = $this->dayCell('Incomplete', 'Incomplete');
                        }
                    }
                } elseif ($holidays->has($date) && ($usesRegularWeekday || $useCalendarFallback)) {
                    $holiday++;

                    $cell = $this->dayCell('Holiday', $holidays->get($date)->name);
                } elseif (($usesRegularWeekday || $useCalendarFallback) && $dateValue->isWeekday()) {
                    $scheduled++;

                    if (! $dtr) {
                        if ($leaveEntry) {
                            $leave++;

                            $cell = $this->dayCell('Leave', $leaveEntry['name']);
                        } else {
                            $absent++;

                            $cell = $this->dayCell('Absent', 'Absent');
                        }
                    } elseif ($this->timeIn($dtr) && $this->timeOut($dtr)) {
                        $present++;
                        $workedMinutes += $this->workedMinutes($dtr);

                        $cell = $this->dayCell('Present', $this->hoursLabel($dtr));
                    } else {
                        $incomplete++;
                        $workedMinutes += $this->workedMinutes($dtr);

                        $cell = $this->dayCell('Incomplete', 'Incomplete');
                    }
                } elseif ($leaveEntry) {
                    $leave++;

                    $cell = $this->dayCell('Leave', $leaveEntry['name']);
                } elseif ($dateValue->isWeekend()) {
                    $cell = $this->dayCell('Weekend', $dateValue->format('l'));
                } elseif ($dtr) {
                    if ($this->timeIn($dtr) && $this->timeOut($dtr)) {
                        $present++;
                        $workedMinutes += $this->workedMinutes($dtr);

                        $cell = $this->dayCell('Present', $this->hoursLabel($dtr));
                    } else {
                        $incomplete++;
                        $workedMinutes += $this->workedMinutes($dtr);

                        $cell = $this->dayCell('Incomplete', 'Incomplete');
                    }
                } else {
                    $cell = $this->dayCell('Blank', '-');
                }

                $days[$date] = $cell;
            }

            return [
                'employee' => $employee,
                'scheduled' => $scheduled,
                'present' => $present,
                'incomplete' => $incomplete,
                'absent' => $absent,
                'holiday' => $holiday,
                'leave' => $leave,
                'off' => $off,
                'worked_minutes' => $workedMinutes,
                'days' => $days,
            ];
        });
    }

    private function dayCell(string $status, string $label): array
    {
        return [
            'status' => $status,
            'label' => $label,
        ];
    }

    private function hoursLabel(?EmployeeDtr $dtr): string
    {
        $minutes = $this->workedMinutes($dtr);

        return $minutes > 0 ? number_format($minutes / 60, 2).'h' : 'Present';
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

    private function reportAssignments(array $employeeIds)
    {
        return ScheduleAssignment::query()
            ->with(['shiftCode'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('schedule_date', [$this->from, $this->to])
            ->get();
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

    private function holidays()
    {
        return PayrollHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn (PayrollHoliday $holiday) => $holiday->holiday_date->toDateString());
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

    private function validatePeriod(): void
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        if (CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) > 62) {
            throw ValidationException::withMessages([
                'to' => 'Use a date range of 63 days or less.',
            ]);
        }
    }

    private function setPeriodFromMonth(int $year, int $month): void
    {
        $period = CarbonImmutable::create($year, $month, 1);

        $this->from = $period->startOfMonth()->toDateString();
        $this->to = $period->endOfMonth()->toDateString();
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => CarbonImmutable::create(2026, $month, 1)->format('F')])
            ->all();
    }

    private function yearOptions(): array
    {
        $currentYear = CarbonImmutable::today()->year;

        return range($currentYear - 5, $currentYear + 1);
    }

    private function dates(): array
    {
        return collect(CarbonPeriod::create($this->from, $this->to))
            ->map(fn ($date) => $date->toDateString())
            ->all();
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

    private function key(string $employeeId, string $date): string
    {
        return $employeeId.'|'.$date;
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}
