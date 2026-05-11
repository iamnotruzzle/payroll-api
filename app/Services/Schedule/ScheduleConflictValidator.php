<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\StaffingRequirement;
use Illuminate\Support\Collection;

class ScheduleConflictValidator
{
    public function validate(MonthlySchedule $schedule): array
    {
        $assignments = $schedule->assignments()->with('shiftCode')->orderBy('schedule_date')->get();

        return [
            ...$this->validateStaffing($schedule, $assignments),
            ...$this->validateConsecutiveDutyDays($assignments),
            ...$this->validateNightShiftCaps($assignments),
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
            ->get();

        foreach ($requirements as $requirement) {
            $dates = $assignments
                ->filter(fn (ScheduleAssignment $assignment) => $assignment->shift_code_id === $requirement->shift_code_id)
                ->groupBy(fn (ScheduleAssignment $assignment) => $assignment->schedule_date->toDateString());

            foreach ($dates as $date => $rows) {
                $dayOfWeek = (int) date('w', strtotime($date));
                if ($requirement->day_of_week !== null && $requirement->day_of_week !== $dayOfWeek) {
                    continue;
                }

                if ($rows->count() < $requirement->minimum_staff) {
                    $conflicts[] = [
                        'type' => 'minimum_staffing',
                        'date' => $date,
                        'shift_code_id' => $requirement->shift_code_id,
                        'message' => "Minimum staffing not met for {$date}.",
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function validateConsecutiveDutyDays(Collection $assignments): array
    {
        $conflicts = [];

        foreach ($assignments->groupBy('employee_id') as $employeeId => $rows) {
            $streak = 0;
            foreach ($rows->sortBy('schedule_date') as $assignment) {
                $streak = $assignment->shiftCode?->is_work_shift ? $streak + 1 : 0;
                if ($streak > 5) {
                    $conflicts[] = [
                        'type' => 'consecutive_duty_days',
                        'employee_id' => $employeeId,
                        'date' => $assignment->schedule_date->toDateString(),
                        'message' => "{$employeeId} exceeds maximum consecutive duty days.",
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function validateNightShiftCaps(Collection $assignments): array
    {
        $conflicts = [];

        foreach ($assignments->groupBy('employee_id') as $employeeId => $rows) {
            $nights = $rows->filter(fn ($assignment) => (bool) $assignment->shiftCode?->is_night_shift)->count();
            if ($nights > 7) {
                $conflicts[] = [
                    'type' => 'night_shift_cap',
                    'employee_id' => $employeeId,
                    'message' => "{$employeeId} has {$nights} night shifts this month.",
                ];
            }
        }

        return $conflicts;
    }
}
