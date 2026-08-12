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
        $payload = [
            'notes' => $data['notes'] ?? $assignment->notes,
            'source' => 'manual',
        ];

        if (array_key_exists('shift_code_id', $data)) {
            $payload['shift_code_id'] = $data['shift_code_id'];
        }

        if (array_key_exists('unit_id', $data)) {
            $payload['unit_id'] = $data['unit_id'];
        }

        if (array_key_exists('is_temporary_floater', $data)) {
            $payload['is_temporary_floater'] = (bool) $data['is_temporary_floater'];
        }

        $assignment->fill($payload);
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
