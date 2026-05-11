<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use RuntimeException;

class ScheduleApprovalService
{
    public function __construct(private AuditLogService $auditLogService, private ScheduleConflictValidator $validator) {}

    public function review(MonthlySchedule $schedule, ?string $performedBy = null): MonthlySchedule
    {
        $this->ensureUnlocked($schedule);
        $before = $schedule->toArray();
        $schedule->update(['status' => MonthlySchedule::STATUS_REVIEWED, 'reviewed_by' => $performedBy, 'reviewed_at' => now()]);
        $this->auditLogService->record('schedule.reviewed', $schedule, $before, $schedule->fresh()->toArray(), $performedBy);

        return $schedule->fresh();
    }

    public function approve(MonthlySchedule $schedule, ?string $performedBy = null): MonthlySchedule
    {
        $this->ensureUnlocked($schedule);
        $conflicts = $this->validator->validate($schedule);
        if ($conflicts !== []) {
            throw new RuntimeException('Schedule has unresolved conflicts.');
        }

        $before = $schedule->toArray();
        $schedule->update(['status' => MonthlySchedule::STATUS_APPROVED, 'approved_by' => $performedBy, 'approved_at' => now()]);
        $this->auditLogService->record('schedule.approved', $schedule, $before, $schedule->fresh()->toArray(), $performedBy);

        return $schedule->fresh();
    }

    private function ensureUnlocked(MonthlySchedule $schedule): void
    {
        if ($schedule->isLocked()) {
            throw new RuntimeException('Locked schedules cannot be changed.');
        }
    }
}
