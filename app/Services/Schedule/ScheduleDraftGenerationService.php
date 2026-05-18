<?php

namespace App\Services\Schedule;

use App\Models\Hris\Employee;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\RotationGroupMember;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleTemplate;
use App\Models\Schedule\ShiftCode;
use App\Models\Schedule\StaffingRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScheduleDraftGenerationService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private ScheduleAvailabilityService $availabilityService,
        private ScheduleConflictValidator $validator,
    ) {}

    public function generate(int $year, int $month, ?int $departmentId = null, ?int $templateId = null, ?string $performedBy = null, string $employeeType = Employee::EMPLOYEE_TYPE_PLANTILLA): array
    {
        $template = $templateId ? ScheduleTemplate::with('days.shiftCode')->findOrFail($templateId) : null;
        $existing = MonthlySchedule::query()
            ->where('department_id', $departmentId)
            ->where('rotation_group_id', $template?->rotation_group_id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing?->isLocked() || in_array($existing?->status, [MonthlySchedule::STATUS_REVIEWED, MonthlySchedule::STATUS_APPROVED], true)) {
            throw new RuntimeException('Reviewed, approved, or locked schedules cannot be regenerated.');
        }

        $restShift = ShiftCode::query()
            ->where('is_work_shift', false)
            ->where('is_active', true)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->whereIn('code', ['O', 'OFF'])
            ->orderByRaw("code = 'OFF' desc")
            ->orderByRaw('department_id IS NULL')
            ->first();
        $defaultWorkShift = ShiftCode::where('is_work_shift', true)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->orderByRaw('department_id IS NULL')
            ->orderBy('code')
            ->firstOrFail();
        $regularWeekdayShift = ShiftCode::query()
            ->where('is_work_shift', true)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->where('start_time', '08:00:00')
            ->where('end_time', '17:00:00')
            ->orderByRaw("code = '8-5' desc")
            ->orderByRaw('department_id IS NULL')
            ->first() ?? $defaultWorkShift;
        $workShifts = ShiftCode::query()
            ->where('is_work_shift', true)
            ->where('is_active', true)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->orderByRaw('department_id IS NULL')
            ->orderBy('code')
            ->get();
        $staffingShiftIds = $this->staffingRequirementShiftIds($departmentId);
        if ($staffingShiftIds->isNotEmpty()) {
            $workShifts = $workShifts
                ->whereIn('id', $staffingShiftIds->all())
                ->values();
            $defaultWorkShift = $workShifts->first() ?? $defaultWorkShift;
        }

        $schedule = DB::connection('payroll_scheduler')->transaction(function () use ($year, $month, $departmentId, $template, $restShift, $defaultWorkShift, $regularWeekdayShift, $workShifts, $performedBy, $employeeType) {
            $schedule = MonthlySchedule::query()->updateOrCreate(
                [
                    'department_id' => $departmentId,
                    'rotation_group_id' => $template?->rotation_group_id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'schedule_template_id' => $template?->id,
                    'status' => MonthlySchedule::STATUS_DRAFT,
                    'generated_by' => $performedBy,
                    'generated_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'locked_by' => null,
                    'locked_at' => null,
                ]
            );

            $schedule->assignments()->delete();

            $rotationMemberIds = $template?->rotation_group_id
                ? RotationGroupMember::query()
                    ->where('rotation_group_id', $template->rotation_group_id)
                    ->orderBy('rotation_order')
                    ->pluck('employee_id')
                : collect();
            $regularWeekdayEmployeeIds = EmployeeScheduleSetting::query()
                ->where('uses_regular_weekday_schedule', true)
                ->where('is_active', true)
                ->pluck('employee_id');
            $eligibleEmployeeIds = $rotationMemberIds
                ->merge($regularWeekdayEmployeeIds)
                ->unique()
                ->values();
            $employees = Employee::query()
                ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
                ->when($rotationMemberIds->isNotEmpty(), fn ($query) => $query->whereIn('emp_id', $eligibleEmployeeIds->all()))
                ->where('is_active', 'Y')
                ->employeeType($employeeType)
                ->orderBy('lastname')
                ->get(['emp_id', 'department_id', 'firstname', 'lastname']);

            $settings = EmployeeScheduleSetting::query()
                ->whereIn('employee_id', $employees->pluck('emp_id'))
                ->where('is_active', true)
                ->get()
                ->keyBy('employee_id');

            $date = CarbonImmutable::create($year, $month, 1);
            $lastDate = $date->endOfMonth();
            $exceptions = $this->availabilityService->exceptionShiftCodes($employees->pluck('emp_id'), $date, $lastDate);
            $rotationOrders = $template?->rotation_group_id
                ? RotationGroupMember::query()
                    ->where('rotation_group_id', $template->rotation_group_id)
                    ->pluck('rotation_order', 'employee_id')
                : collect();
            $monthSeed = (($year * 12) + $month) % max(1, $template?->days->count() ?: $workShifts->count() ?: 1);
            $staffingAssignments = $this->staffingRequirementAssignments(
                $departmentId,
                $employees->pluck('emp_id'),
                $date,
                $lastDate,
                $monthSeed,
                $restShift,
            );
            $dutyStreaks = $employees
                ->pluck('emp_id')
                ->mapWithKeys(fn ($employeeId) => [$employeeId => 0])
                ->all();

            while ($date <= $lastDate) {
                foreach ($employees as $index => $employee) {
                    $setting = $settings->get($employee->emp_id);
                    $hasException = isset($exceptions[$employee->emp_id][$date->toDateString()]);
                    $generatedShift = $this->resolveShift(
                        $date,
                        (int) ($rotationOrders[$employee->emp_id] ?? $index),
                        $monthSeed,
                        $setting,
                        $template,
                        $workShifts,
                        $restShift,
                        $defaultWorkShift,
                        $regularWeekdayShift
                    );
                    $exceptionShift = $hasException ? $exceptions[$employee->emp_id][$date->toDateString()] : null;
                    $exceptionWasApplied = $this->isAutoAssignableShift($exceptionShift);
                    $usesRegularWeekdaySchedule = ! $setting || $setting->uses_regular_weekday_schedule;
                    $staffingShift = $usesRegularWeekdaySchedule
                        ? null
                        : ($staffingAssignments[$employee->emp_id][$date->toDateString()] ?? null);
                    $shift = $exceptionWasApplied ? $exceptionShift : ($staffingShift ?? $generatedShift);
                    if (! $this->shouldPreserveTemplateShift($template, $exceptionWasApplied, $staffingShift)) {
                        $shift = $this->normalizeAutoAllocatedShift($shift, $defaultWorkShift, $restShift);
                    }

                    if (! $shift) {
                        continue;
                    }

                    $maxConsecutiveDutyDays = (int) ($setting?->max_consecutive_duty_days ?: 5);
                    $forcedConsecutiveRest = false;

                    if (
                        ! $exceptionWasApplied
                        && ! $usesRegularWeekdaySchedule
                        && $restShift
                        && $shift->is_work_shift
                        && $dutyStreaks[$employee->emp_id] >= $maxConsecutiveDutyDays
                    ) {
                        $shift = $restShift;
                        $forcedConsecutiveRest = true;
                    }

                    $source = $exceptionWasApplied
                        ? 'protected_exception'
                        : ($staffingShift
                            ? 'staffing_requirement'
                            : ($usesRegularWeekdaySchedule
                                ? 'regular_weekday'
                                : ($shift->is_work_shift
                                ? ($setting?->can_rotate_shift ? 'generated_rotation' : 'default_schedule')
                                : ($forcedConsecutiveRest ? 'max_consecutive_rest' : ($setting?->can_rotate_shift ? 'generated_rotation' : 'default_schedule')))));

                    ScheduleAssignment::create([
                        'monthly_schedule_id' => $schedule->id,
                        'employee_id' => $employee->emp_id,
                        'schedule_date' => $date->toDateString(),
                        'shift_code_id' => $shift->id,
                        'source' => $source,
                    ]);

                    $dutyStreaks[$employee->emp_id] = $shift->is_work_shift
                        ? $dutyStreaks[$employee->emp_id] + 1
                        : 0;
                }

                $date = $date->addDay();
            }

            $this->auditLogService->record('schedule.generated', $schedule, null, $schedule->fresh()->toArray(), $performedBy);

            return $schedule->fresh('assignments.shiftCode');
        });

        return [
            'schedule' => $schedule,
            'conflicts' => $this->validator->validate($schedule),
        ];
    }

    private function staffingRequirementAssignments(
        ?int $departmentId,
        Collection $employeeIds,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        int $monthSeed,
        ?ShiftCode $restShift,
    ): array {
        $requirements = StaffingRequirement::query()
            ->with('shiftCode')
            ->where('is_active', true)
            ->whereNotNull('rotation_group_id')
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->orderBy('rotation_group_id')
            ->orderBy('shift_code_id')
            ->get()
            ->filter(fn (StaffingRequirement $requirement) => $this->isAutoAssignableShift($requirement->shiftCode));

        if ($requirements->isEmpty()) {
            return [];
        }

        $employeeLookup = $employeeIds->flip();
        $membersByGroup = RotationGroupMember::query()
            ->whereIn('rotation_group_id', $requirements->pluck('rotation_group_id')->unique()->all())
            ->orderBy('rotation_order')
            ->get()
            ->groupBy('rotation_group_id')
            ->map(fn (Collection $members) => $members
                ->pluck('employee_id')
                ->filter(fn ($employeeId) => $employeeLookup->has($employeeId))
                ->values());

        $assignments = [];
        $state = [];

        foreach ($membersByGroup as $rotationGroupId => $members) {
            if ($members->isEmpty()) {
                continue;
            }

            foreach ($members as $employeeId) {
                $state[$employeeId] = [
                    'last_shift' => null,
                    'last_date' => null,
                    'weekly_hours' => [],
                ];
            }

            for ($date = $monthStart; $date <= $monthEnd; $date = $date->addDay()) {
                $dateKey = $date->toDateString();
                $weekKey = $date->startOfWeek()->toDateString();
                $dayRequirements = $requirements
                    ->where('rotation_group_id', $rotationGroupId)
                    ->filter(fn (StaffingRequirement $requirement) => $this->requirementAppliesOn($requirement, $date))
                    ->sortBy(fn (StaffingRequirement $requirement) => $this->shiftStartMinutes($requirement->shiftCode))
                    ->values();

                if ($dayRequirements->isEmpty()) {
                    continue;
                }

                $taken = [];
                $slots = $dayRequirements
                    ->flatMap(fn (StaffingRequirement $requirement) => collect(range(1, (int) $requirement->minimum_staff))
                        ->map(fn () => $requirement))
                    ->values();

                foreach ($slots as $slotIndex => $requirement) {
                    $candidate = $this->bestStaffingCandidate($members, $taken, $state, $requirement->shiftCode, $date, $weekKey, $monthSeed + $slotIndex);
                    if (! $candidate) {
                        continue;
                    }

                    $assignments[$candidate][$dateKey] = $requirement->shiftCode;
                    $taken[$candidate] = true;
                    $state[$candidate]['last_shift'] = $requirement->shiftCode;
                    $state[$candidate]['last_date'] = $date;
                    $state[$candidate]['weekly_hours'][$weekKey] = ($state[$candidate]['weekly_hours'][$weekKey] ?? 0)
                        + (float) ($requirement->shiftCode?->work_hours ?? 0);
                }

                if ($restShift) {
                    foreach ($members as $employeeId) {
                        if (isset($taken[$employeeId])) {
                            continue;
                        }

                        $assignments[$employeeId][$dateKey] = $restShift;
                        $state[$employeeId]['last_shift'] = $restShift;
                        $state[$employeeId]['last_date'] = $date;
                    }
                }
            }
        }

        return $assignments;
    }

    private function bestStaffingCandidate(Collection $members, array $taken, array $state, ?ShiftCode $shift, CarbonImmutable $date, string $weekKey, int $seed): ?string
    {
        if (! $shift) {
            return null;
        }

        $candidates = $members
            ->reject(fn ($employeeId) => isset($taken[$employeeId]))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $candidateCount = $candidates->count();

        return $candidates
            ->sortByDesc(function ($employeeId, $index) use ($state, $shift, $date, $weekKey, $seed, $candidateCount) {
                $employeeState = $state[$employeeId] ?? [];
                $lastShift = $employeeState['last_shift'] ?? null;
                $weeklyHours = (float) (($employeeState['weekly_hours'][$weekKey] ?? 0));
                $sameShiftBonus = $lastShift && $lastShift->id === $shift->id ? 1000 : 0;
                $safeTurnaroundBonus = $this->hasSafeTurnaround($lastShift, $shift, $employeeState['last_date'] ?? null, $date) ? 500 : -1000;
                $weeklyTarget = (float) $shift->work_hours >= 12 ? 48 : 40;
                $hoursBalance = max(0, $weeklyTarget - $weeklyHours);
                $rotationTieBreaker = (($seed + $index) % max(1, $candidateCount)) / 100;

                return $sameShiftBonus + $safeTurnaroundBonus + $hoursBalance + $rotationTieBreaker;
            })
            ->first();
    }

    private function hasSafeTurnaround(?ShiftCode $previousShift, ShiftCode $nextShift, ?CarbonImmutable $previousDate, CarbonImmutable $nextDate): bool
    {
        if (! $previousShift || ! $previousShift->is_work_shift || ! $previousDate) {
            return true;
        }

        if (! $nextShift->is_work_shift) {
            return true;
        }

        $previousEnd = CarbonImmutable::parse($previousDate->toDateString().' '.$previousShift->end_time)
            ->addDays((int) $previousShift->end_day_offset);
        if ((int) $previousShift->end_day_offset === 0 && $previousShift->end_time <= $previousShift->start_time) {
            $previousEnd = $previousEnd->addDay();
        }

        $nextStart = CarbonImmutable::parse($nextDate->toDateString().' '.$nextShift->start_time);

        return $previousEnd->diffInMinutes($nextStart, false) >= 480;
    }

    private function shiftStartMinutes(?ShiftCode $shift): int
    {
        if (! $shift?->start_time) {
            return 0;
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr($shift->start_time, 0, 5)));

        return ($hours * 60) + $minutes;
    }

    private function staffingRequirementShiftIds(?int $departmentId): Collection
    {
        return StaffingRequirement::query()
            ->with('shiftCode')
            ->where('is_active', true)
            ->where(function ($query) use ($departmentId) {
                $query->whereNull('department_id')->orWhere('department_id', $departmentId);
            })
            ->get()
            ->filter(fn (StaffingRequirement $requirement) => $this->isAutoAssignableShift($requirement->shiftCode))
            ->pluck('shift_code_id')
            ->unique()
            ->values();
    }

    private function requirementAppliesOn(StaffingRequirement $requirement, CarbonImmutable $date): bool
    {
        if ($requirement->effective_from && $date->lt(CarbonImmutable::parse($requirement->effective_from))) {
            return false;
        }

        if ($requirement->effective_to && $date->gt(CarbonImmutable::parse($requirement->effective_to))) {
            return false;
        }

        return $requirement->day_of_week === null || (int) $requirement->day_of_week === $date->dayOfWeek;
    }

    private function resolveShift(
        CarbonImmutable $date,
        int $employeeIndex,
        int $monthSeed,
        ?EmployeeScheduleSetting $setting,
        ?ScheduleTemplate $template,
        $workShifts,
        ?ShiftCode $restShift,
        ShiftCode $defaultWorkShift,
        ShiftCode $regularWeekdayShift
    ): ?ShiftCode {
        if (! $setting || $setting->uses_regular_weekday_schedule) {
            if ($date->isWeekend()) {
                return $restShift;
            }

            return $regularWeekdayShift;
        }

        if ($setting && ! $setting->can_rotate_shift && $setting->defaultShiftCode) {
            return $this->normalizeAutoAllocatedShift($setting->defaultShiftCode, $defaultWorkShift, $restShift);
        }

        if (! $template || $template->days->isEmpty()) {
            if ($setting?->can_rotate_shift && $workShifts->isNotEmpty()) {
                return $workShifts[($date->day - 1 + $employeeIndex + $monthSeed) % $workShifts->count()];
            }

            return $this->normalizeAutoAllocatedShift($setting?->defaultShiftCode ?? $defaultWorkShift, $defaultWorkShift, $restShift);
        }

        $days = $template->days->values();
        $offset = ($date->day - 1 + $employeeIndex + $monthSeed) % $days->count();

        return $this->normalizeAutoAllocatedShift($days[$offset]->shiftCode ?? $defaultWorkShift, $defaultWorkShift, $restShift);
    }

    private function shouldPreserveTemplateShift(?ScheduleTemplate $template, bool $exceptionWasApplied, ?ShiftCode $staffingShift): bool
    {
        return (bool) $template?->days->isNotEmpty()
            && ! $exceptionWasApplied
            && ! $staffingShift;
    }

    private function normalizeAutoAllocatedShift(?ShiftCode $shift, ShiftCode $defaultWorkShift, ?ShiftCode $restShift): ?ShiftCode
    {
        if (! $shift) {
            return null;
        }

        if ($shift->is_work_shift) {
            return $shift;
        }

        return in_array(strtoupper((string) $shift->code), ['O', 'OFF'], true)
            ? ($restShift ?? $shift)
            : $defaultWorkShift;
    }

    private function isAutoAssignableShift(?ShiftCode $shift): bool
    {
        return (bool) $shift && ($shift->is_work_shift || in_array(strtoupper((string) $shift->code), ['O', 'OFF'], true));
    }
}
