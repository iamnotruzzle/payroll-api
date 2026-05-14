<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollDtrScheduleEncoding;
use App\Models\Payroll\PayrollTimeTemplate;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SchedulerDtrSyncService
{
    public function syncDepartmentPeriod(int $departmentId, string $from, string $to, ?string $performedBy = null): void
    {
        $assignments = ScheduleAssignment::query()
            ->with(['shiftCode', 'monthlySchedule'])
            ->whereBetween('schedule_date', [$from, $to])
            ->whereHas('monthlySchedule', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId)
                    ->whereIn('status', [MonthlySchedule::STATUS_APPROVED, MonthlySchedule::STATUS_LOCKED]);
            })
            ->get();

        $this->syncAssignments($assignments, $performedBy);
    }

    public function syncEmployeesPeriod(array $employeeIds, string $from, string $to, ?string $performedBy = null): void
    {
        if (empty($employeeIds)) {
            return;
        }

        $assignments = ScheduleAssignment::query()
            ->with(['shiftCode', 'monthlySchedule'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('schedule_date', [$from, $to])
            ->whereHas('monthlySchedule', function ($query) {
                $query->whereIn('status', [MonthlySchedule::STATUS_APPROVED, MonthlySchedule::STATUS_LOCKED]);
            })
            ->get();

        $this->syncAssignments($assignments, $performedBy);
    }

    public function syncAssignments(Collection $assignments, ?string $performedBy = null): void
    {
        if ($assignments->isEmpty()) {
            return;
        }

        $templates = PayrollTimeTemplate::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (PayrollTimeTemplate $template) => $this->timeKey(
                $template->start_time,
                $template->end_time,
                (int) $template->end_day_offset,
            ));

        foreach ($assignments as $assignment) {
            $shift = $assignment->shiftCode;
            $date = CarbonImmutable::parse($assignment->schedule_date)->toDateString();

            PayrollDtrScheduleEncoding::query()
                ->where('emp_id', $assignment->employee_id)
                ->whereDate('dtr_date', $date)
                ->delete();

            if (! $shift?->is_work_shift || ! $shift->start_time || ! $shift->end_time) {
                continue;
            }

            $template = $this->templateForShift($shift, $templates);

            PayrollDtrScheduleEncoding::create([
                'emp_id' => $assignment->employee_id,
                'dtr_date' => $date,
                'payroll_time_template_id' => $template->id,
                'encoded_by' => $performedBy ?? 'system:scheduler-sync',
            ]);
        }
    }

    private function templateForShift(ShiftCode $shift, Collection $templates): PayrollTimeTemplate
    {
        $key = $this->timeKey($shift->start_time, $shift->end_time, (int) $shift->end_day_offset);
        $existing = $templates->get($key);
        if ($existing) {
            return $existing;
        }

        $template = PayrollTimeTemplate::create([
            'name' => 'Scheduler '.$shift->code.' - '.$shift->name,
            'start_time' => $this->normalizeTime($shift->start_time),
            'end_time' => $this->normalizeTime($shift->end_time),
            'end_day_offset' => (int) $shift->end_day_offset,
            'work_hours' => $shift->work_hours ?? $this->computeWorkHours($shift),
            'is_active' => true,
        ]);

        $templates->put($key, $template);

        return $template;
    }

    private function computeWorkHours(ShiftCode $shift): float
    {
        $start = strtotime('2000-01-01 '.$this->normalizeTime($shift->start_time));
        $end = strtotime('2000-01-01 '.$this->normalizeTime($shift->end_time).' +'.((int) $shift->end_day_offset).' day');

        if ((int) $shift->end_day_offset === 0 && $end < $start) {
            $end = strtotime('2000-01-02 '.$this->normalizeTime($shift->end_time));
        }

        if ((int) $shift->end_day_offset === 0 && $this->normalizeTime($shift->start_time) === '08:00:00' && $this->normalizeTime($shift->end_time) === '17:00:00') {
            return 8.0;
        }

        return round(max(0, ($end - $start) / 3600), 2);
    }

    private function timeKey(?string $start, ?string $end, int $offset): string
    {
        return implode('|', [$this->normalizeTime($start), $this->normalizeTime($end), $offset]);
    }

    private function normalizeTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 8) : null;
    }
}
