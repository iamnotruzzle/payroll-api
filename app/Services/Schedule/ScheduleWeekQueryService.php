<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ScheduleWeekQueryService
{
    /**
     * Week / range schedule for external consumers.
     *
     * @param  array{
     *     department_id?: int|null,
     *     unit_id?: int|null,
     *     emp_id?: string|null,
     *     from: string,
     *     to: string,
     *     statuses?: list<string>|null
     * }  $filters
     * @return array{from: string, to: string, count: int, items: list<array<string, mixed>>}
     */
    public function week(array $filters): array
    {
        [$from, $to] = $this->normalizeRange($filters['from'] ?? null, $filters['to'] ?? null);
        $statuses = $filters['statuses'] ?? null;

        $items = $this->queryAssignments($from, $to, $filters, $statuses)
            ->map(fn (ScheduleAssignment $assignment) => $this->mapAssignment($assignment))
            ->values()
            ->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Schedule presence for a week — approved/locked assignments only (not DTR punches).
     *
     * @param  array{
     *     department_id?: int|null,
     *     unit_id?: int|null,
     *     emp_id?: string|null,
     *     from: string,
     *     to: string
     * }  $filters
     * @return array{from: string, to: string, count: int, source: string, items: list<array<string, mixed>>}
     */
    public function attendancePresence(array $filters): array
    {
        [$from, $to] = $this->normalizeRange($filters['from'] ?? null, $filters['to'] ?? null);

        $items = $this->queryAssignments(
            $from,
            $to,
            $filters,
            [MonthlySchedule::STATUS_APPROVED, MonthlySchedule::STATUS_LOCKED]
        )
            ->map(function (ScheduleAssignment $assignment) {
                $mapped = $this->mapAssignment($assignment);

                return [
                    'emp_id' => $mapped['emp_id'],
                    'schedule_date' => $mapped['schedule_date'],
                    'shift_code' => $mapped['shift_code'],
                    'shift_name' => $mapped['shift_name'],
                    'is_work_shift' => $mapped['is_work_shift'],
                    'is_night_shift' => $mapped['is_night_shift'],
                    'unit_id' => $mapped['unit_id'],
                    'unit_code' => $mapped['unit_code'],
                    'department_id' => $mapped['department_id'],
                    'monthly_schedule_id' => $mapped['monthly_schedule_id'],
                    'schedule_status' => $mapped['schedule_status'],
                    'present' => (bool) $mapped['is_work_shift'],
                ];
            })
            ->values()
            ->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => count($items),
            'source' => 'schedule_assignments_approved_or_locked',
            'note' => 'Presence is derived from approved/locked schedule assignments only; this is not biometric DTR.',
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $statuses
     */
    private function queryAssignments(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
        ?array $statuses,
    ): Collection {
        $departmentId = isset($filters['department_id']) && $filters['department_id'] !== '' && $filters['department_id'] !== null
            ? (int) $filters['department_id']
            : null;
        $unitId = isset($filters['unit_id']) && $filters['unit_id'] !== '' && $filters['unit_id'] !== null
            ? (int) $filters['unit_id']
            : null;
        $empId = isset($filters['emp_id']) && is_string($filters['emp_id']) && $filters['emp_id'] !== ''
            ? $filters['emp_id']
            : null;

        if ($departmentId === null && $unitId === null && $empId === null) {
            throw ValidationException::withMessages([
                'filter' => 'Provide at least one of department_id, unit_id, or emp_id.',
            ]);
        }

        return ScheduleAssignment::query()
            ->with(['shiftCode', 'unit', 'monthlySchedule'])
            ->whereBetween('schedule_date', [$from->toDateString(), $to->toDateString()])
            ->when($empId, fn ($query) => $query->where('employee_id', $empId))
            ->when($unitId, fn ($query) => $query->where('unit_id', $unitId))
            ->whereHas('monthlySchedule', function ($query) use ($departmentId, $statuses) {
                if ($departmentId !== null) {
                    $query->where('department_id', $departmentId);
                }
                if ($statuses !== null && $statuses !== []) {
                    $query->whereIn('status', $statuses);
                }
            })
            ->orderBy('schedule_date')
            ->orderBy('employee_id')
            ->get();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function normalizeRange(?string $from, ?string $to): array
    {
        if (! $from || ! $to) {
            throw ValidationException::withMessages([
                'from' => 'from and to dates are required (Y-m-d).',
            ]);
        }

        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'from' => 'from must be on or before to.',
            ]);
        }

        if ($start->diffInDays($end) > 45) {
            throw ValidationException::withMessages([
                'to' => 'Date range cannot exceed 45 days.',
            ]);
        }

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAssignment(ScheduleAssignment $assignment): array
    {
        $schedule = $assignment->monthlySchedule;

        return [
            'assignment_id' => $assignment->id,
            'emp_id' => $assignment->employee_id,
            'schedule_date' => $assignment->schedule_date->toDateString(),
            'shift_code_id' => $assignment->shift_code_id,
            'shift_code' => $assignment->shiftCode?->code,
            'shift_name' => $assignment->shiftCode?->name,
            'is_work_shift' => (bool) $assignment->shiftCode?->is_work_shift,
            'is_night_shift' => (bool) $assignment->shiftCode?->is_night_shift,
            'unit_id' => $assignment->unit_id,
            'unit_code' => $assignment->unit?->code,
            'is_temporary_floater' => (bool) $assignment->is_temporary_floater,
            'department_id' => $schedule?->department_id,
            'monthly_schedule_id' => $assignment->monthly_schedule_id,
            'schedule_status' => $schedule?->status,
            'year' => $schedule?->year,
            'month' => $schedule?->month,
        ];
    }
}
