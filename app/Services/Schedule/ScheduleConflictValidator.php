<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\StaffingRequirement;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ScheduleConflictValidator
{
    public function validate(MonthlySchedule $schedule): array
    {
        $assignments = $schedule->assignments()
            ->with(['shiftCode', 'employee'])
            ->orderBy('schedule_date')
            ->get();

        return [
            ...$this->validateStaffing($schedule, $assignments),
            ...$this->validateTurnarounds($assignments),
            ...$this->validateConsecutiveDutyDays($assignments),
            ...$this->validateNightShiftCaps($assignments),
            ...$this->validateWorkHours($schedule, $assignments),
        ];
    }

    private function validateStaffing(MonthlySchedule $schedule, Collection $assignments): array
    {
        $conflicts = [];
        $requirements = StaffingRequirement::query()
            ->where('is_active', true)
            ->where(function ($query) use ($schedule) {
                $query->whereNull('department_id')->orWhere('department_id', $schedule->department_id);
            })
            ->where(function ($query) use ($schedule) {
                $query->whereNull('rotation_group_id')->orWhere('rotation_group_id', $schedule->rotation_group_id);
            })
            ->get();
        $monthStart = CarbonImmutable::create($schedule->year, $schedule->month, 1);
        $monthEnd = $monthStart->endOfMonth();

        foreach ($requirements as $requirement) {
            $date = $monthStart;

            while ($date <= $monthEnd) {
                if ($requirement->effective_from && $date->lt(CarbonImmutable::parse($requirement->effective_from))) {
                    $date = $date->addDay();
                    continue;
                }

                if ($requirement->effective_to && $date->gt(CarbonImmutable::parse($requirement->effective_to))) {
                    $date = $date->addDay();
                    continue;
                }

                $dayOfWeek = $date->dayOfWeek;
                if ($requirement->day_of_week !== null && $requirement->day_of_week !== $dayOfWeek) {
                    $date = $date->addDay();
                    continue;
                }

                $rows = $assignments->filter(
                    fn(ScheduleAssignment $assignment) => $assignment->shift_code_id === $requirement->shift_code_id
                        && $assignment->schedule_date->toDateString() === $date->toDateString()
                );

                if ($rows->count() < $requirement->minimum_staff) {
                    $conflicts[] = [
                        'type' => 'minimum_staffing',
                        'date' => $date->toDateString(),
                        'shift_code_id' => $requirement->shift_code_id,
                        'message' => "Minimum staffing not met for {$date->toDateString()}.",
                    ];
                }

                if ($requirement->maximum_staff !== null && $rows->count() > $requirement->maximum_staff) {
                    $conflicts[] = [
                        'type' => 'maximum_staffing',
                        'date' => $date->toDateString(),
                        'shift_code_id' => $requirement->shift_code_id,
                        'message' => "Maximum staffing exceeded for {$date->toDateString()}.",
                    ];
                }

                $date = $date->addDay();
            }
        }

        return $conflicts;
    }

    private function validateConsecutiveDutyDays(Collection $assignments): array
    {
        $conflicts = [];

        $settings = EmployeeScheduleSetting::query()
            ->whereIn('employee_id', $assignments->pluck('employee_id')->unique())
            ->get()
            ->keyBy('employee_id');

        foreach ($assignments->groupBy('employee_id') as $employeeId => $rows) {
            $employeeName = $this->employeeName($rows, $employeeId);

            $limit = $settings->get($employeeId)?->max_consecutive_duty_days ?? 5;
            $streak = 0;
            $streakStart = null;
            $lastDutyDate = null;

            foreach ($rows->sortBy('schedule_date') as $assignment) {
                if ($assignment->shiftCode?->is_work_shift) {
                    $streak++;
                    $streakStart ??= $assignment->schedule_date;
                    $lastDutyDate = $assignment->schedule_date;
                    continue;
                }

                if ($streak > $limit) {
                    $conflicts[] = $this->consecutiveDutyConflict(
                        $employeeName,
                        $employeeId,
                        $limit,
                        $streak,
                        $streakStart,
                        $lastDutyDate
                    );
                }

                $streak = 0;
                $streakStart = null;
                $lastDutyDate = null;
            }

            if ($streak > $limit) {
                $conflicts[] = $this->consecutiveDutyConflict(
                    $employeeName,
                    $employeeId,
                    $limit,
                    $streak,
                    $streakStart,
                    $lastDutyDate
                );
            }
        }

        return $conflicts;
    }

    private function validateTurnarounds(Collection $assignments): array
    {
        $conflicts = [];

        foreach ($assignments->groupBy('employee_id') as $employeeId => $rows) {
            $employeeName = $this->employeeName($rows, $employeeId);
            $previous = null;

            foreach ($rows->sortBy('schedule_date') as $assignment) {
                if (
                    $previous
                    && $previous->shiftCode?->is_work_shift
                    && $assignment->shiftCode?->is_work_shift
                    && ! $this->hasSafeTurnaround($previous, $assignment)
                ) {
                    $conflicts[] = [
                        'type' => 'quick_turnaround',
                        'employee_id' => $employeeId,
                        'date' => $assignment->schedule_date->toDateString(),
                        'message' => "{$employeeName} has quick turnaround from {$previous->schedule_date->toDateString()} {$previous->shiftCode?->code} to {$assignment->schedule_date->toDateString()} {$assignment->shiftCode?->code} (minimum 8 hours rest).",
                    ];
                }

                $previous = $assignment;
            }
        }

        return $conflicts;
    }

    private function consecutiveDutyConflict(string $employeeId, int $limit, int $streak, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return [
            'type' => 'consecutive_duty_days',
            'employee_id' => $employeeId,
            'date' => $endDate->toDateString(),
            'message' => "{$employeeId} has {$streak} consecutive duty days from {$startDate->toDateString()} to {$endDate->toDateString()} (limit {$limit}).",
        ];
    }

    private function validateNightShiftCaps(Collection $assignments): array
    {
        $conflicts = [];

        $settings = EmployeeScheduleSetting::query()
            ->whereIn('employee_id', $assignments->pluck('employee_id')->unique())
            ->get()
            ->keyBy('employee_id');

        foreach ($assignments->groupBy('employee_id') as $employeeId => $rows) {
            $employeeName = $this->employeeName($rows, $employeeId);

            $limit = $settings->get($employeeId)?->max_night_shifts_per_month ?? 7;
            $nights = $rows->filter(fn($assignment) => (bool) $assignment->shiftCode?->is_night_shift)->count();

            if ($nights > $limit) {
                $conflicts[] = [
                    'type' => 'night_shift_cap',
                    'employee_id' => $employeeId,
                    'message' => "{$employeeName} has {$nights} night shifts this month (limit {$limit}).",
                ];
            }
        }

        return $conflicts;
    }

    private function validateWorkHours(MonthlySchedule $schedule, Collection $assignments): array
    {
        $conflicts = [];

        $scheduleMonthStart = CarbonImmutable::create($schedule->year, $schedule->month, 1);
        $scheduleMonthEnd = $scheduleMonthStart->endOfMonth();
        $monthStart = $scheduleMonthStart->startOfWeek(CarbonInterface::MONDAY);
        $monthEnd = $scheduleMonthEnd->endOfWeek(CarbonInterface::SUNDAY);

        $workAssignments = $assignments->filter(
            fn($assignment) => (bool) $assignment->shiftCode?->is_work_shift
        );

        foreach ($workAssignments->groupBy('employee_id') as $employeeId => $rows) {
            $employeeName = $this->employeeName($rows, $employeeId);

            $weeklyHours = [];
            $weekStart = $monthStart;

            while ($weekStart <= $monthEnd) {
                $weekEnd = $weekStart->endOfWeek(CarbonInterface::SUNDAY);

                $hours = $rows
                    ->filter(fn($assignment) => $assignment->schedule_date->betweenIncluded($weekStart, $weekEnd))
                    ->sum(fn($assignment) => (float) ($assignment->shiftCode?->work_hours ?? 0));

                $isFullWeekInsideScheduleMonth =
                    $weekStart->gte($scheduleMonthStart)
                    && $weekEnd->lte($scheduleMonthEnd);

                if ($isFullWeekInsideScheduleMonth) {
                    $weeklyHours[$weekStart->toDateString()] = $hours;
                }

                $hasTwelveHourShift = $rows
                    ->filter(fn($assignment) => $assignment->schedule_date->betweenIncluded($weekStart, $weekEnd))
                    ->contains(fn($assignment) => (float) ($assignment->shiftCode?->work_hours ?? 0) >= 12);

                $isAllowedTwelveHourWeek =
                    $hasTwelveHourShift
                    && $hours >= 36
                    && $hours <= 48;

                if ($isFullWeekInsideScheduleMonth && $hours > 0 && $hours < 40 && ! $isAllowedTwelveHourWeek) {
                    $conflicts[] = [
                        'type' => 'minimum_weekly_hours',
                        'employee_id' => $employeeId,
                        'week_start' => $weekStart->toDateString(),
                        'message' => "{$employeeName} has only {$hours} scheduled work hour(s) for the week of {$weekStart->toDateString()} (minimum 40).",
                    ];
                }

                if ($isFullWeekInsideScheduleMonth && $hours > 48 && ! $isAllowedTwelveHourWeek) {
                    $conflicts[] = [
                        'type' => 'maximum_weekly_hours',
                        'employee_id' => $employeeId,
                        'week_start' => $weekStart->toDateString(),
                        'message' => "{$employeeName} has {$hours} scheduled work hour(s) for the week of {$weekStart->toDateString()} (maximum 48).",
                    ];
                }

                $weekStart = $weekStart->addWeek();
            }

            $weekKeys = array_keys($weeklyHours);

            for ($i = 0; $i < count($weekKeys) - 1; $i++) {
                $hours = $weeklyHours[$weekKeys[$i]] + $weeklyHours[$weekKeys[$i + 1]];

                if ($hours > 0 && $hours < 80) {
                    $conflicts[] = [
                        'type' => 'minimum_biweekly_hours',
                        'employee_id' => $employeeId,
                        'period_start' => $weekKeys[$i],
                        'message' => "{$employeeName} has only {$hours} scheduled work hour(s) for the two-week period starting {$weekKeys[$i]} (minimum 80).",
                    ];
                }

                if ($hours > 96) {
                    $conflicts[] = [
                        'type' => 'maximum_biweekly_hours',
                        'employee_id' => $employeeId,
                        'period_start' => $weekKeys[$i],
                        'message' => "{$employeeName} has {$hours} scheduled work hour(s) for the two-week period starting {$weekKeys[$i]} (maximum 96).",
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function employeeName(Collection $rows, string $employeeId): string
    {
        $employee = $rows->first()?->employee;

        if (! $employee) {
            return $employeeId;
        }

        return sprintf(
            '%s, %s (%s)',
            $employee->lastname,
            $employee->firstname,
            $employeeId
        );
    }

    private function hasSafeTurnaround(ScheduleAssignment $previous, ScheduleAssignment $next): bool
    {
        $previousShift = $previous->shiftCode;
        $nextShift = $next->shiftCode;

        if (! $previousShift?->start_time || ! $previousShift->end_time || ! $nextShift?->start_time) {
            return true;
        }

        $previousEnd = CarbonImmutable::parse($previous->schedule_date->toDateString() . ' ' . $previousShift->end_time)
            ->addDays((int) $previousShift->end_day_offset);
        if ((int) $previousShift->end_day_offset === 0 && $previousShift->end_time <= $previousShift->start_time) {
            $previousEnd = $previousEnd->addDay();
        }

        $nextStart = CarbonImmutable::parse($next->schedule_date->toDateString() . ' ' . $nextShift->start_time);

        return $previousEnd->diffInMinutes($nextStart, false) >= 480;
    }
}
