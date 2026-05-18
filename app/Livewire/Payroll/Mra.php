<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Hris\EmployeeLeave;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollDtrScheduleEncoding;
use App\Models\Payroll\PayrollHoliday;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Models\Payroll\PayrollMraReport;
use App\Models\Payroll\PayrollTimeTemplate;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Services\Payroll\SchedulerDtrSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Mra extends Component
{
    public string $from;

    public string $to;

    public ?string $remarks = null;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public function mount(): void
    {
        $today = CarbonImmutable::today()->subMonth();
        $this->from = $today->startOfMonth()->toDateString();
        $this->to = $today->endOfMonth()->toDateString();
    }

    public function render()
    {
        $report = $this->report();

        return view('livewire.payroll.mra', [
            'department' => auth()->user()?->employee?->department,
            'report' => $report,
            'previewRows' => $this->previewRows(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'reports' => PayrollMraReport::query()
                ->where('department_id', $this->departmentId())
                ->orderByDesc('generated_at')
                ->limit(10)
                ->get(),
            'adjustments' => $report
                ? PayrollLeaveCreditAdjustment::query()->where('mra_report_id', $report->id)->orderBy('employee_name')->get()
                : collect(),
        ]);
    }

    public function finalize(): void
    {
        $this->validatePeriod();

        $departmentId = $this->departmentId();
        $generatedBy = auth()->user()?->emp_id ?? auth()->user()?->username ?? 'web';

        DB::connection('payroll')->transaction(function () use ($departmentId, $generatedBy) {
            $report = PayrollMraReport::updateOrCreate(
                ['department_id' => $departmentId, 'period_start' => $this->from, 'period_end' => $this->to],
                ['status' => 'Finalized', 'generated_by' => $generatedBy, 'generated_at' => now(), 'remarks' => $this->remarks],
            );

            $previous = PayrollLeaveCreditAdjustment::query()->where('mra_report_id', $report->id)->get();
            if ($previous->isNotEmpty()) {
                $employees = Employee::query()->whereIn('emp_id', $previous->pluck('emp_id'))->get()->keyBy('emp_id');
                foreach ($previous as $adjustment) {
                    $employee = $employees->get($adjustment->emp_id);
                    if ($employee && strcasecmp((string) $adjustment->leave_type, 'VL') === 0) {
                        $employee->vacation_leave_credits += (float) $adjustment->adjustment_days;
                        $employee->save();
                    }
                }

                PayrollLeaveCreditAdjustment::query()->where('mra_report_id', $report->id)->delete();
            }

            foreach ($this->previewRows() as $row) {
                if ($row['undertime_minutes'] <= 0) {
                    continue;
                }

                $employee = Employee::query()->find($row['emp_id']);
                if (! $employee) {
                    continue;
                }

                $beginning = (float) $employee->vacation_leave_credits;
                $ending = max(0, $beginning - $row['day_equivalent']);
                $employee->vacation_leave_credits = $ending;
                $employee->save();

                PayrollLeaveCreditAdjustment::create([
                    'mra_report_id' => $report->id,
                    'emp_id' => $employee->emp_id,
                    'employee_name' => $row['employee_name'],
                    'leave_type' => 'VL',
                    'beginning_balance' => $beginning,
                    'adjustment_days' => $row['day_equivalent'],
                    'ending_balance' => $ending,
                    'undertime_tardy_minutes' => $row['undertime_minutes'],
                    'remarks' => 'MRA '.$this->from.' to '.$this->to.' undertime/tardiness equivalent.',
                    'created_at' => now(),
                ]);
            }
        });

        session()->flash('status', 'MRA generated. DTR labels are locked and tardiness/undertime was deducted from VL credits.');
    }

    private function previewRows()
    {
        $this->validatePeriod();

        if (! $this->departmentId()) {
            return collect();
        }

        $employees = Employee::query()
            ->with('position')
            ->where('department_id', $this->departmentId())
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        app(SchedulerDtrSyncService::class)->syncDepartmentPeriod(
            $this->departmentId(),
            $this->from,
            $this->to,
        );

        $empIds = $employees->pluck('emp_id')->all();
        $dtrs = EmployeeDtr::query()->whereIn('emp_id', $empIds)->whereBetween('dtr_date', [$this->from, $this->to])->get()->groupBy('emp_id');
        $leaves = EmployeeLeave::query()
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $this->to)
            ->whereDate('end_date', '>=', $this->from)
            ->get()
            ->groupBy('emp_id');
        $labels = PayrollDtrLabel::query()->whereIn('emp_id', $empIds)->whereBetween('dtr_date', [$this->from, $this->to])->get()->groupBy('emp_id');
        $adjustments = PayrollDtrAdjustment::query()->whereIn('emp_id', $empIds)->whereBetween('dtr_date', [$this->from, $this->to])->get()->groupBy('emp_id');
        $schedules = PayrollDtrScheduleEncoding::query()->whereIn('emp_id', $empIds)->whereBetween('dtr_date', [$this->from, $this->to])->get()->groupBy('emp_id');
        $templates = PayrollTimeTemplate::query()->where('is_active', true)->get()->keyBy('id');
        $labelOptions = PayrollDtrLabelOption::query()->get()->keyBy('code');
        $holidays = PayrollHoliday::query()->where('is_active', true)->whereBetween('holiday_date', [$this->from, $this->to])->get();
        $holidaysByDate = $holidays->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
        $assignments = ScheduleAssignment::query()
            ->with('shiftCode')
            ->whereIn('employee_id', $empIds)
            ->whereBetween('schedule_date', [$this->from, $this->to])
            ->whereHas('monthlySchedule', function ($query) {
                $query->whereIn('status', [MonthlySchedule::STATUS_APPROVED, MonthlySchedule::STATUS_LOCKED]);
            })
            ->get()
            ->groupBy('employee_id');

        return $employees->map(function (Employee $employee) use ($dtrs, $leaves, $labels, $adjustments, $schedules, $templates, $labelOptions, $holidays, $holidaysByDate, $assignments) {
            return $this->buildMraRow(
                $employee,
                $dtrs->get($employee->emp_id, collect()),
                $leaves->get($employee->emp_id, collect()),
                $this->withHolidayLabels($employee->emp_id, $labels->get($employee->emp_id, collect()), $holidays),
                $adjustments->get($employee->emp_id, collect()),
                $schedules->get($employee->emp_id, collect()),
                $templates,
                $labelOptions,
                $holidaysByDate,
                $assignments->get($employee->emp_id, collect()),
            );
        });
    }

    private function buildMraRow(Employee $employee, $dtrs, $leaves, $labels, $adjustments, $schedules, $templates, $labelOptions, $holidaysByDate, $assignments): array
    {
        $details = [];
        $sickLeaveDays = 0.0;
        $vacationLeaveDays = 0.0;
        $undertimeMinutes = 0;

        foreach ($leaves as $leave) {
            $leaveName = $leave->leave_type_name;
            $leaveDates = $this->leaveDatesFor($leave);
            $leaveDays = count($leaveDates) ?: max(1, (float) $leave->days_wpay + (float) $leave->days_wopay);

            if (str_contains(strtolower($leaveName), 'sick')) {
                $sickLeaveDays += $leaveDays;
            } elseif (str_contains(strtolower($leaveName), 'vacation') || str_contains(strtolower($leaveName), 'forced')) {
                $vacationLeaveDays += $leaveDays;
            }

            foreach ($leaveDates as $date) {
                $details[] = ['date' => $date->format('M d'), 'remarks' => strtoupper($leaveName), 'minutes' => 0];
            }
        }

        foreach ($adjustments->where('minutes', '>', 0)->sortBy('dtr_date') as $adjustment) {
            if (! in_array($adjustment->adjustment_type, ['TARDINESS', 'UNDERTIME'], true)) {
                continue;
            }

            $undertimeMinutes += (int) $adjustment->minutes;
            $remarks = $adjustment->adjustment_type.($adjustment->remarks ? ' - '.$adjustment->remarks : '');
            $details[] = ['date' => $adjustment->dtr_date->format('M d'), 'remarks' => $remarks, 'minutes' => (int) $adjustment->minutes];
        }

        foreach ($labels->sortBy('dtr_date') as $label) {
            if ($this->isWeekendLabel($label->label)) {
                continue;
            }

            $details[] = [
                'date' => $label->dtr_date->format('M d'),
                'remarks' => strtoupper($labelOptions->get($label->label)?->name ?? str_replace('_', ' ', $label->label)),
                'minutes' => 0,
            ];
        }

        $adjustmentsByDate = $adjustments->groupBy(fn ($adjustment) => $adjustment->dtr_date->toDateString());
        $schedulesByDate = $schedules->whereNotNull('payroll_time_template_id')->keyBy(fn ($schedule) => $schedule->dtr_date->toDateString());
        $assignmentsByDate = $assignments->keyBy(fn ($assignment) => $assignment->schedule_date->toDateString());
        $physicallyReportedHours = 0.0;

        foreach ($dtrs as $dtr) {
            $date = $dtr->dtr_date->toDateString();
            $schedule = $schedulesByDate->get($date);
            $template = $schedule?->payroll_time_template_id ? $templates->get($schedule->payroll_time_template_id) : null;
            $physicallyReportedHours += $template && $this->hasCompletedDtrSpan($dtr)
                ? $this->templateWorkedHours((float) $template->work_hours, $adjustmentsByDate->get($date, collect()))
                : $this->workedHours($dtr);
        }

        foreach ($labels as $label) {
            $option = $labelOptions->get($label->label);
            if ($option?->counts_as_mra_hours && $this->labelCountsAsMraHours($label, $holidaysByDate, $assignmentsByDate)) {
                $physicallyReportedHours += $this->labelDayValue($label, $holidaysByDate) * 8;
            }
        }

        return [
            'emp_id' => $employee->emp_id,
            'employee_name' => trim($employee->lastname.', '.$employee->firstname),
            'position' => $employee->position?->position_title,
            'sick_leave_days' => round($sickLeaveDays, 2),
            'vacation_leave_days' => round($vacationLeaveDays, 2),
            'undertime_minutes' => $undertimeMinutes,
            'day_equivalent' => round($undertimeMinutes / 480, 3),
            'physically_reported_hours' => round($physicallyReportedHours, 2),
            'vl_balance' => (float) $employee->vacation_leave_credits,
            'ending_balance' => max(0, (float) $employee->vacation_leave_credits - round($undertimeMinutes / 480, 3)),
            'details' => collect($details)->sortBy('date')->values()->all(),
        ];
    }

    private function withHolidayLabels(string $employeeId, $labels, $holidays)
    {
        $output = $labels->values();
        $existingDates = $output->pluck('dtr_date')->map(fn ($date) => $date->toDateString())->all();

        foreach ($holidays as $holiday) {
            if (blank($holiday->label_code) || in_array($holiday->holiday_date->toDateString(), $existingDates, true)) {
                continue;
            }

            $output->push(new PayrollDtrLabel([
                'emp_id' => $employeeId,
                'dtr_date' => $holiday->holiday_date,
                'label' => $holiday->label_code,
            ]));
        }

        return $output;
    }

    private function leaveDatesFor(EmployeeLeave $leave): array
    {
        $dates = [];
        $from = CarbonImmutable::parse($this->from);
        $to = CarbonImmutable::parse($this->to);

        for ($date = CarbonImmutable::parse($leave->start_date); $date->lessThanOrEqualTo(CarbonImmutable::parse($leave->end_date)); $date = $date->addDay()) {
            if ($date->betweenIncluded($from, $to)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    private function labelDayValue(PayrollDtrLabel $label, $holidaysByDate): float
    {
        $holiday = $holidaysByDate->get($label->dtr_date->toDateString());
        if (! $holiday || strcasecmp((string) $holiday->label_code, (string) $label->label) !== 0) {
            return 1.0;
        }

        return in_array($holiday->holiday_scope, ['FIRST_HALF', 'SECOND_HALF'], true) ? 0.5 : 1.0;
    }

    private function labelCountsAsMraHours(PayrollDtrLabel $label, $holidaysByDate, $assignmentsByDate): bool
    {
        $holiday = $holidaysByDate->get($label->dtr_date->toDateString());
        if (! $holiday || strcasecmp((string) $holiday->label_code, (string) $label->label) !== 0) {
            return true;
        }

        return $this->holidayAppliesToAssignment($assignmentsByDate->get($label->dtr_date->toDateString()));
    }

    private function holidayAppliesToAssignment(?ScheduleAssignment $assignment): bool
    {
        if (! $assignment || $assignment->source === 'regular_weekday') {
            return true;
        }

        $shift = $assignment->shiftCode;

        return $shift?->code === 'R'
            || (
                $shift?->is_work_shift
                && substr((string) $shift->start_time, 0, 5) === '08:00'
                && substr((string) $shift->end_time, 0, 5) === '17:00'
                && (int) $shift->end_day_offset === 0
            );
    }

    private function workedHours(EmployeeDtr $dtr): float
    {
        $start = $this->timeIn($dtr);
        $end = $this->timeOut($dtr);

        return $start && $end && $end->greaterThan($start) ? round($start->diffInMinutes($end) / 60, 4) : 0.0;
    }

    private function templateWorkedHours(float $templateHours, $adjustments): float
    {
        $deductionHours = $adjustments
            ->whereIn('adjustment_type', ['TARDINESS', 'UNDERTIME'])
            ->sum('minutes') / 60;

        return round(max(0, $templateHours - $deductionHours), 4);
    }

    private function hasCompletedDtrSpan(EmployeeDtr $dtr): bool
    {
        $start = $this->timeIn($dtr);
        $end = $this->timeOut($dtr);

        return $start && $end && $end->greaterThan($start);
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

    public function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0 ? trim($hours.'h '.($remaining > 0 ? $remaining.'m' : '')) : $remaining.'m';
    }

    private function isWeekendLabel(?string $label): bool
    {
        return in_array(strtoupper((string) $label), ['WEEKEND', 'SATURDAY', 'SUNDAY'], true);
    }

    private function report(): ?PayrollMraReport
    {
        $this->validatePeriod();

        return PayrollMraReport::query()
            ->where('department_id', $this->departmentId())
            ->whereDate('period_start', $this->from)
            ->whereDate('period_end', $this->to)
            ->first();
    }

    private function validatePeriod(): void
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }
}