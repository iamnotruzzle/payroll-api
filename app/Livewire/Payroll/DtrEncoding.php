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
use App\Models\Payroll\PayrollMraReport;
use App\Models\Payroll\PayrollTimeTemplate;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Services\Payroll\SchedulerDtrSyncService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DtrEncoding extends Component
{
    public string $from;

    public string $to;

    public string $monthFilter;

    public string $yearFilter;

    public ?string $selectedEmpId = null;

    public ?string $selectedBatchLabel = null;

    public ?int $selectedBatchTemplateId = null;

    public array $selectedRows = [];

    public array $rows = [];

    public bool $isLocked = false;

    public bool $isLabelComplete = true;

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public function mount(): void
    {
        $today = CarbonImmutable::today()->subMonth();
        $this->monthFilter = (string) $today->month;
        $this->yearFilter = (string) $today->year;
        $this->syncPeriodFromSelection();
        $this->loadState();
    }

    public function render()
    {
        $employees = $this->employees();

        return view('livewire.payroll.dtr-encoding', [
            'department' => auth()->user()?->employee?->department,
            'employees' => $employees,
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => $this->yearOptions(),
            'employeeNavigation' => $this->employeeNavigation($employees),
            'dates' => $this->dates(),
            'templates' => PayrollTimeTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'labelOptions' => PayrollDtrLabelOption::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updatedSelectedEmpId(): void
    {
        $this->loadState();
    }

    public function updatedEmployeeTypeFilter(): void
    {
        $this->selectedEmpId = null;
        $this->loadState();
    }

    public function updatedMonthFilter(): void
    {
        $this->loadState();
    }

    public function updatedYearFilter(): void
    {
        $this->loadState();
    }

    public function loadState(): void
    {
        $this->validatePeriod();
        $this->isLocked = PayrollMraReport::query()
            ->where('department_id', $this->departmentId())
            ->whereDate('period_start', $this->from)
            ->whereDate('period_end', $this->to)
            ->exists();

        if (blank($this->selectedEmpId)) {
            $this->rows = [];
            $this->isLabelComplete = true;

            return;
        }

        if (! $this->employees()->contains('emp_id', $this->selectedEmpId)) {
            $this->selectedEmpId = null;
            $this->rows = [];

            return;
        }

        app(SchedulerDtrSyncService::class)->syncEmployeesPeriod(
            [$this->selectedEmpId],
            $this->from,
            $this->to,
        );

        $dtrs = EmployeeDtr::query()
            ->where('emp_id', $this->selectedEmpId)
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn ($dtr) => $dtr->dtr_date->toDateString());
        $labels = PayrollDtrLabel::query()
            ->where('emp_id', $this->selectedEmpId)
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn ($label) => $label->dtr_date->toDateString());
        $adjustments = PayrollDtrAdjustment::query()
            ->where('emp_id', $this->selectedEmpId)
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->get()
            ->groupBy(fn ($adjustment) => $adjustment->dtr_date->toDateString());
        $schedules = PayrollDtrScheduleEncoding::query()
            ->where('emp_id', $this->selectedEmpId)
            ->whereBetween('dtr_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn ($encoding) => $encoding->dtr_date->toDateString());
        $holidays = PayrollHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$this->from, $this->to])
            ->get()
            ->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
        $assignments = ScheduleAssignment::query()
            ->with(['monthlySchedule', 'shiftCode'])
            ->where('employee_id', $this->selectedEmpId)
            ->whereBetween('schedule_date', [$this->from, $this->to])
            ->whereHas('monthlySchedule', function ($query) {
                $query->whereIn('status', [MonthlySchedule::STATUS_APPROVED, MonthlySchedule::STATUS_LOCKED]);
            })
            ->get()
            ->keyBy(fn ($assignment) => $assignment->schedule_date->toDateString());
        $leaveDates = $this->leaveDates($this->selectedEmpId);
        $templates = PayrollTimeTemplate::query()->where('is_active', true)->get()->keyBy('id');

        $rows = [];
        foreach ($this->dates() as $date) {
            $dayAdjustments = $adjustments->get($date, collect())->keyBy('adjustment_type');
            $schedule = $schedules->get($date);
            $templateId = $schedule?->payroll_time_template_id;
            $row = $this->buildRow(
                $date,
                $dtrs->get($date),
                $leaveDates[$date] ?? null,
                $labels->get($date)?->label,
                $labels->get($date)?->remarks,
                $holidays->get($date),
                $this->holidayAppliesToAssignment($assignments->get($date)),
                $templateId,
                (int) ($dayAdjustments->get('TARDINESS')?->minutes ?? 0),
                (int) ($dayAdjustments->get('UNDERTIME')?->minutes ?? 0),
            );
            $row['is_scheduler_synced'] = str_starts_with((string) $schedule?->encoded_by, 'system:scheduler')
                || str_starts_with((string) $schedule?->encoded_by, 'system:schedule-lock');

            if ($templateId && isset($templates[$templateId])) {
                $row = $this->computeRow($row, $templates[$templateId]);
            }

            $rows[$date] = $row;
        }

        $this->rows = $rows;
        $this->selectedRows = [];
        $this->isLabelComplete = ! collect($this->rows)->contains(fn ($row) => $row['needs_label']);
    }

    public function save(): void
    {
        $this->validatePeriod();

        if ($this->isLocked) {
            session()->flash('status', 'This month is locked because the MRA has already been generated.');

            return;
        }

        if (blank($this->selectedEmpId)) {
            session()->flash('status', 'Select an employee first.');

            return;
        }

        $encodedBy = auth()->user()?->emp_id ?? 'web';
        $labelOnly = collect($this->rows)->contains(fn ($row) => $row['needs_label']);

        DB::connection('payroll')->transaction(function () use ($encodedBy, $labelOnly) {
            foreach ($this->rows as $row) {
                $existingLabel = PayrollDtrLabel::query()
                    ->where('emp_id', $this->selectedEmpId)
                    ->whereDate('dtr_date', $row['date'])
                    ->first();

                $labelValue = ($row['is_holiday'] || $row['has_label_without_dtr'] || $row['has_leave']) ? ($row['label'] ?: null) : null;
                if (blank($labelValue)) {
                    $existingLabel?->delete();
                } else {
                    PayrollDtrLabel::updateOrCreate(
                        ['emp_id' => $this->selectedEmpId, 'dtr_date' => $row['date']],
                        ['label' => $labelValue, 'remarks' => $row['leave_name'] ?? null],
                    );
                }
            }

            if ($labelOnly) {
                return;
            }

            foreach ($this->rows as $row) {
                if ($row['is_scheduler_synced']) {
                    $templateId = $row['template_id'];
                } else {
                    $templateId = $row['can_encode_schedule'] ? ($row['template_id'] ?: null) : null;
                }

                $existingSchedule = PayrollDtrScheduleEncoding::query()
                    ->where('emp_id', $this->selectedEmpId)
                    ->whereDate('dtr_date', $row['date'])
                    ->first();

                if ($row['is_scheduler_synced']) {
                    // Scheduler-synced rows are the source of truth for schedule assignment.
                } elseif (! $templateId) {
                    $existingSchedule?->delete();
                } else {
                    PayrollDtrScheduleEncoding::updateOrCreate(
                        ['emp_id' => $this->selectedEmpId, 'dtr_date' => $row['date']],
                        ['payroll_time_template_id' => (int) $templateId, 'encoded_by' => $encodedBy],
                    );
                }

                foreach (['TARDINESS' => 'tardiness_minutes', 'UNDERTIME' => 'undertime_minutes'] as $type => $field) {
                    $minutes = $row['can_encode_schedule'] ? max(0, (int) ($row[$field] ?? 0)) : 0;
                    $existingAdjustment = PayrollDtrAdjustment::query()
                        ->where('emp_id', $this->selectedEmpId)
                        ->whereDate('dtr_date', $row['date'])
                        ->where('adjustment_type', $type)
                        ->first();

                    if ($minutes <= 0) {
                        $existingAdjustment?->delete();

                        continue;
                    }

                    PayrollDtrAdjustment::updateOrCreate(
                        ['emp_id' => $this->selectedEmpId, 'dtr_date' => $row['date'], 'adjustment_type' => $type],
                        ['minutes' => $minutes, 'remarks' => null, 'encoded_by' => $encodedBy],
                    );
                }
            }
        });

        $this->loadState();
        session()->flash('status', $labelOnly
            ? 'Office DTR labels saved. Complete all required labels before encoding schedules.'
            : 'Office DTR schedules and computed adjustments saved.');
    }

    public function applyBatchLabel(): void
    {
        if (blank($this->selectedBatchLabel)) {
            return;
        }

        foreach ($this->rows as $date => $row) {
            if (($this->selectedRows[$date] ?? false) && $row['can_edit_label']) {
                $this->rows[$date]['label'] = $this->selectedBatchLabel;
                $this->rows[$date]['has_label_without_dtr'] = true;
                $this->rows[$date]['needs_label'] = false;
            }
        }

        $this->isLabelComplete = ! collect($this->rows)->contains(fn ($row) => $row['needs_label']);
    }

    public function applyBatchTemplate(): void
    {
        if (! $this->selectedBatchTemplateId) {
            return;
        }

        $template = PayrollTimeTemplate::query()->find($this->selectedBatchTemplateId);
        if (! $template) {
            return;
        }

        foreach ($this->rows as $date => $row) {
            if (($this->selectedRows[$date] ?? false) && $row['can_encode_schedule']) {
                $this->rows[$date]['template_id'] = $template->id;
                $this->rows[$date] = $this->computeRow($this->rows[$date], $template);
            }
        }
    }

    public function updatedRows($value, string $key): void
    {
        if (! str_ends_with($key, '.template_id')) {
            return;
        }

        $date = substr($key, 0, -12);
        $templateId = $this->rows[$date]['template_id'] ?? null;
        if (! $templateId) {
            $this->rows[$date]['tardiness_minutes'] = 0;
            $this->rows[$date]['undertime_minutes'] = 0;
            $this->rows[$date]['worked_hours'] = $this->actualWorkedHours($this->rows[$date]['actual_time_in'], $this->rows[$date]['actual_time_out']);

            return;
        }

        $template = PayrollTimeTemplate::query()->find($templateId);
        if ($template) {
            $this->rows[$date] = $this->computeRow($this->rows[$date], $template);
        }
    }

    public function selectAll(): void
    {
        foreach ($this->rows as $date => $row) {
            $this->selectedRows[$date] = $this->isLabelComplete ? $row['can_encode_schedule'] : $row['can_edit_label'];
        }
    }

    public function clearSelection(): void
    {
        $this->selectedRows = [];
    }

    public function previousEmployee(): void
    {
        $employeeIds = $this->employees()->pluck('emp_id')->values();

        if ($employeeIds->isEmpty()) {
            return;
        }

        $currentIndex = $employeeIds->search($this->selectedEmpId);
        $targetIndex = $currentIndex === false ? $employeeIds->count() - 1 : max(0, $currentIndex - 1);
        $this->selectedEmpId = $employeeIds->get($targetIndex);
        $this->loadState();
    }

    public function nextEmployee(): void
    {
        $employeeIds = $this->employees()->pluck('emp_id')->values();

        if ($employeeIds->isEmpty()) {
            return;
        }

        $currentIndex = $employeeIds->search($this->selectedEmpId);
        $targetIndex = $currentIndex === false ? 0 : min($employeeIds->count() - 1, $currentIndex + 1);
        $this->selectedEmpId = $employeeIds->get($targetIndex);
        $this->loadState();
    }

    private function buildRow(string $date, ?EmployeeDtr $dtr, ?array $leave, ?string $label, ?string $labelRemarks, ?PayrollHoliday $holiday, bool $holidayApplies, ?int $templateId, int $tardinessMinutes, int $undertimeMinutes): array
    {
        $dateValue = CarbonImmutable::parse($date);
        $actualTimeIn = $this->timeIn($dtr);
        $actualTimeOut = $this->timeOut($dtr);
        $isWeekend = $dateValue->isWeekend();
        $isHoliday = $holiday !== null;
        $hasLeave = $leave !== null;
        $hasDtr = $actualTimeIn !== null || $actualTimeOut !== null || $dtr !== null;
        $hasCompleteDtr = $actualTimeIn !== null && $actualTimeOut !== null && $actualTimeOut->greaterThan($actualTimeIn);
        $leaveLabel = $leave['label'] ?? null;
        $leaveName = $leave['name'] ?? $labelRemarks;
        $displayLabel = $isHoliday ? ($label ?: $holiday?->label_code) : ($isWeekend ? 'WEEKEND' : ($label ?: $leaveLabel));
        $isBlankWorkday = ! $isWeekend && ! $isHoliday && ! $hasDtr && ! $hasLeave;
        $hasLabelWithoutDtr = ! $isWeekend && ! $isHoliday && ! $hasDtr && filled($displayLabel);

        return [
            'date' => $date,
            'day' => $dateValue->format('l'),
            'actual_time_in' => $actualTimeIn?->toDateTimeString(),
            'actual_time_out' => $actualTimeOut?->toDateTimeString(),
            'time_in_display' => $this->formatTime($actualTimeIn, $dateValue),
            'time_out_display' => $this->formatTime($actualTimeOut, $dateValue),
            'worked_hours' => $this->actualWorkedHours($actualTimeIn?->toDateTimeString(), $actualTimeOut?->toDateTimeString()),
            'has_leave' => $hasLeave,
            'leave_name' => $leaveName,
            'is_weekend' => $isWeekend,
            'is_holiday' => $isHoliday,
            'holiday_applies' => $isHoliday && $holidayApplies,
            'holiday_name' => $holiday?->name,
            'holiday_scope' => $holiday?->holiday_scope ?: 'FULL_DAY',
            'label' => $displayLabel,
            'template_id' => $templateId,
            'is_scheduler_synced' => false,
            'tardiness_minutes' => $tardinessMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'can_edit_label' => $isBlankWorkday && ! $hasLabelWithoutDtr,
            'can_encode_schedule' => ! $hasLeave && $hasCompleteDtr && ! $hasLabelWithoutDtr,
            'has_label_without_dtr' => $hasLabelWithoutDtr,
            'needs_label' => $isBlankWorkday && blank($displayLabel),
        ];
    }

    private function computeRow(array $row, PayrollTimeTemplate $template): array
    {
        if (! $row['can_encode_schedule']) {
            $row['tardiness_minutes'] = 0;
            $row['undertime_minutes'] = 0;

            return $row;
        }

        $scheduleStart = CarbonImmutable::parse($row['date'].' '.$template->start_time);
        $endDayOffset = (int) $template->end_day_offset;
        if ($endDayOffset === 0 && $template->end_time <= $template->start_time) {
            $endDayOffset = 1;
        }

        $scheduleEnd = CarbonImmutable::parse($row['date'].' '.$template->end_time)->addDays($endDayOffset);
        if ($scheduleEnd->lessThanOrEqualTo($scheduleStart)) {
            $scheduleEnd = $scheduleEnd->addDay();
        }

        $window = $this->requiredWindow($row, $scheduleStart, $scheduleEnd);
        if ($window === null) {
            $row['tardiness_minutes'] = 0;
            $row['undertime_minutes'] = 0;
        } else {
            [$requiredStart, $requiredEnd] = $window;
            $actualIn = CarbonImmutable::parse($row['actual_time_in']);
            $actualOut = CarbonImmutable::parse($row['actual_time_out']);
            $row['tardiness_minutes'] = $actualIn->greaterThan($requiredStart)
                ? (int) ceil($requiredStart->diffInMinutes($actualIn->lessThan($requiredEnd) ? $actualIn : $requiredEnd))
                : 0;
            $row['undertime_minutes'] = $actualOut->lessThan($requiredEnd)
                ? (int) ceil(($actualOut->greaterThan($requiredStart) ? $actualOut : $requiredStart)->diffInMinutes($requiredEnd))
                : 0;
        }

        $row['worked_hours'] = round(max(0, (float) $template->work_hours - (($row['tardiness_minutes'] + $row['undertime_minutes']) / 60)), 2);

        return $row;
    }

    private function requiredWindow(array $row, CarbonImmutable $scheduleStart, CarbonImmutable $scheduleEnd): ?array
    {
        if (! $row['is_holiday'] || ! $row['holiday_applies']) {
            return [$scheduleStart, $scheduleEnd];
        }

        $midpoint = $scheduleStart->addSeconds((int) floor($scheduleStart->diffInSeconds($scheduleEnd) / 2));

        return match ($row['holiday_scope']) {
            'FIRST_HALF' => [$midpoint, $scheduleEnd],
            'SECOND_HALF' => [$scheduleStart, $midpoint],
            default => null,
        };
    }

    private function employees()
    {
        return Employee::query()
            ->with('position')
            ->where('department_id', $this->departmentId())
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'position_id', 'department_id']);
    }

    private function employeeNavigation($employees): array
    {
        $employeeIds = $employees->pluck('emp_id')->values();
        $currentIndex = $employeeIds->search($this->selectedEmpId);

        return [
            'has_previous' => $employeeIds->isNotEmpty() && ($currentIndex === false || $currentIndex > 0),
            'has_next' => $employeeIds->isNotEmpty() && ($currentIndex === false || $currentIndex < $employeeIds->count() - 1),
        ];
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

    private function dates(): array
    {
        $this->validatePeriod();

        return collect(CarbonPeriod::create($this->from, $this->to))
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }

    private function leaveDates(string $employeeId): array
    {
        $dates = [];
        $query = EmployeeLeave::query()
            ->with('leaveType')
            ->where('emp_id', $employeeId)
            ->where('status', 0)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $this->to)
            ->whereDate('end_date', '>=', $this->from);

        foreach ($query->get() as $leave) {
            $leaveLabel = $this->leaveLabelFor($leave);
            for ($date = CarbonImmutable::parse($leave->start_date); $date->lessThanOrEqualTo(CarbonImmutable::parse($leave->end_date)); $date = $date->addDay()) {
                if ($date->betweenIncluded(CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to))) {
                    $dates[$date->toDateString()] = $leaveLabel;
                }
            }
        }

        return $dates;
    }

    private function leaveLabelFor(EmployeeLeave $leave): array
    {
        $leaveName = $leave->leave_type_name
            ?: $leave->leaveType?->leave_name
            ?: 'Leave';

        return [
            'label' => ((float) $leave->days_wopay > 0 && (float) $leave->days_wpay <= 0)
                ? 'LEAVE_WITHOUT_PAY'
                : 'LEAVE_WITH_PAY',
            'name' => $leaveName,
        ];
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

    private function actualWorkedHours(?string $actualTimeIn, ?string $actualTimeOut): float
    {
        if (! $actualTimeIn || ! $actualTimeOut) {
            return 0;
        }

        $start = CarbonImmutable::parse($actualTimeIn);
        $end = CarbonImmutable::parse($actualTimeOut);

        return $end->greaterThan($start) ? round($start->diffInMinutes($end) / 60, 2) : 0;
    }

    private function formatTime(?CarbonImmutable $value, CarbonImmutable $date): string
    {
        if (! $value) {
            return '-';
        }

        return $value->isSameDay($date) ? $value->format('h:i A') : $value->format('M d, h:i A');
    }

    public function formatTemplate(PayrollTimeTemplate $template): string
    {
        $offset = $template->end_day_offset > 0 ? ' +'.$template->end_day_offset.'d' : '';

        return $template->name.' ('.substr((string) $template->start_time, 0, 5).' - '.substr((string) $template->end_time, 0, 5).$offset.')';
    }

    public function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0 ? trim($hours.'h '.($remaining > 0 ? $remaining.'m' : '')) : $remaining.'m';
    }

    private function validatePeriod(): void
    {
        $this->validate([
            'monthFilter' => ['required', 'integer', 'between:1,12'],
            'yearFilter' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $this->syncPeriodFromSelection();

        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        if (CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) > 31) {
            throw ValidationException::withMessages(['to' => 'Use a date range of 32 days or less.']);
        }
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }

    private function syncPeriodFromSelection(): void
    {
        $period = CarbonImmutable::create((int) $this->yearFilter, (int) $this->monthFilter, 1);
        $this->from = $period->startOfMonth()->toDateString();
        $this->to = $period->endOfMonth()->toDateString();
    }

    private function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => CarbonImmutable::create(2000, $month, 1)->format('F')])
            ->all();
    }

    private function yearOptions(): array
    {
        $currentYear = CarbonImmutable::today()->year;

        return range($currentYear - 5, $currentYear + 1);
    }
}
