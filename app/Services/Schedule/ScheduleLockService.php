<?php

namespace App\Services\Schedule;

use App\Models\Schedule\MonthlySchedule;
use RuntimeException;

class ScheduleLockService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function lock(MonthlySchedule $schedule, ?string $performedBy = null): MonthlySchedule
    {
        if ($schedule->status !== MonthlySchedule::STATUS_APPROVED) {
            throw new RuntimeException('Only approved schedules can be locked.');
        }

        $before = $schedule->toArray();
        $schedule->update(['status' => MonthlySchedule::STATUS_LOCKED, 'locked_by' => $performedBy, 'locked_at' => now()]);
        $this->auditLogService->record('schedule.locked', $schedule, $before, $schedule->fresh()->toArray(), $performedBy);

        return $schedule->fresh();
    }
}
