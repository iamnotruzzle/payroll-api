<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\ScheduleAssignment;
use App\Models\Schedule\ScheduleTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchedulePatternFillService
{
    public function __construct(
        private ScheduleAssignmentService $assignmentService,
    ) {}

    /**
     * @param  list<string>|null  $employeeIds  null = all employees on the schedule (within optional filters)
     * @return array{
     *     changes: list<array<string, mixed>>,
     *     summary: array{total: int, changed: int, unchanged: int, employees: int}
     * }
     */
    public function preview(
        MonthlySchedule $schedule,
        ScheduleTemplate $template,
        ?array $employeeIds = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $this->assertEditable($schedule);
        $patternDays = $this->patternDays($template);
        $assignments = $this->targetAssignments($schedule, $employeeIds, $dateFrom, $dateTo);

        $changes = [];
        $changed = 0;
        $unchanged = 0;

        foreach ($assignments as $assignment) {
            $patternDay = $this->patternDayForDate($assignment->schedule_date, $patternDays);
            $fromId = (int) $assignment->shift_code_id;
            $toId = (int) $patternDay->shift_code_id;
            $willChange = $fromId !== $toId;

            if ($willChange) {
                $changed++;
            } else {
                $unchanged++;
            }

            $changes[] = [
                'assignment_id' => $assignment->id,
                'employee_id' => $assignment->employee_id,
                'schedule_date' => $assignment->schedule_date->toDateString(),
                'from_shift_code_id' => $fromId,
                'from_code' => $assignment->shiftCode?->code,
                'to_shift_code_id' => $toId,
                'to_code' => $patternDay->shiftCode?->code,
                'will_change' => $willChange,
            ];
        }

        return [
            'changes' => $changes,
            'summary' => [
                'total' => count($changes),
                'changed' => $changed,
                'unchanged' => $unchanged,
                'employees' => $assignments->pluck('employee_id')->unique()->count(),
            ],
        ];
    }

    /**
     * @param  list<string>|null  $employeeIds
     * @return array{applied: int, unchanged: int, employees: int}
     */
    public function apply(
        MonthlySchedule $schedule,
        ScheduleTemplate $template,
        ?array $employeeIds = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $performedBy = null,
    ): array {
        $preview = $this->preview($schedule, $template, $employeeIds, $dateFrom, $dateTo);
        $toApply = collect($preview['changes'])->where('will_change', true)->values();

        DB::connection('payroll_scheduler')->transaction(function () use ($toApply, $performedBy): void {
            foreach ($toApply as $change) {
                $assignment = ScheduleAssignment::with('monthlySchedule')->findOrFail($change['assignment_id']);
                $this->assignmentService->update(
                    $assignment,
                    ['shift_code_id' => $change['to_shift_code_id']],
                    $performedBy
                );
            }
        });

        return [
            'applied' => $toApply->count(),
            'unchanged' => (int) $preview['summary']['unchanged'],
            'employees' => (int) $preview['summary']['employees'],
        ];
    }

    private function assertEditable(MonthlySchedule $schedule): void
    {
        if ($schedule->isLocked()) {
            throw new RuntimeException('Locked schedules cannot be changed.');
        }
    }

    private function patternDays(ScheduleTemplate $template): Collection
    {
        $template->loadMissing('days.shiftCode');
        $days = $template->days->values();

        if ($days->isEmpty()) {
            throw new RuntimeException('Selected pattern has no days.');
        }

        return $days;
    }

    /**
     * @param  list<string>|null  $employeeIds
     */
    private function targetAssignments(
        MonthlySchedule $schedule,
        ?array $employeeIds,
        ?string $dateFrom,
        ?string $dateTo,
    ): Collection {
        $from = $dateFrom ? CarbonImmutable::parse($dateFrom)->toDateString() : null;
        $to = $dateTo ? CarbonImmutable::parse($dateTo)->toDateString() : null;

        if ($from && $to && $from > $to) {
            throw new RuntimeException('Date range is invalid (from is after to).');
        }

        return ScheduleAssignment::with('shiftCode')
            ->where('monthly_schedule_id', $schedule->id)
            ->when($employeeIds !== null, function ($query) use ($employeeIds) {
                $ids = array_values(array_filter(array_map('strval', $employeeIds)));
                if ($ids === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('employee_id', $ids);
                }
            })
            ->when($from, fn ($query) => $query->whereDate('schedule_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('schedule_date', '<=', $to))
            ->orderBy('employee_id')
            ->orderBy('schedule_date')
            ->get();
    }

    private function patternDayForDate($scheduleDate, Collection $patternDays)
    {
        $date = CarbonImmutable::parse($scheduleDate);
        $dayIndex = $patternDays->count() === 7
            ? ((int) $date->isoWeekday()) - 1
            : ((int) $date->format('j')) - 1;

        return $patternDays[$dayIndex % $patternDays->count()];
    }
}
