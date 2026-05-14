<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ScheduleAssignment;
use RuntimeException;

class ScheduleAssignmentService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function update(ScheduleAssignment $assignment, array $data, ?string $performedBy = null): ScheduleAssignment
    {
        $schedule = $assignment->monthlySchedule;
        if ($schedule->isLocked()) {
            throw new RuntimeException('Locked schedules cannot be changed.');
        }

        $before = $assignment->toArray();
        $assignment->fill([
            'shift_code_id' => $data['shift_code_id'],
            'notes' => $data['notes'] ?? $assignment->notes,
            'source' => 'manual',
        ]);
        $assignment->save();

        $this->auditLogService->record(
            'schedule.assignment.updated',
            $assignment,
            $before,
            $assignment->fresh()->toArray(),
            $performedBy,
        );

        return $assignment->fresh('shiftCode');
    }
}
