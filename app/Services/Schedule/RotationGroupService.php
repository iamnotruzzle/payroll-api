<?php

namespace App\Services\Schedule;

use App\Models\Schedule\RotationGroup;
use Illuminate\Support\Facades\DB;

class RotationGroupService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function save(array $data, ?string $performedBy = null): RotationGroup
    {
        return DB::connection('payroll_scheduler')->transaction(function () use ($data, $performedBy) {
            $existing = isset($data['id']) ? RotationGroup::find($data['id']) : null;
            $before = $existing?->load('members')->toArray();
            $group = RotationGroup::updateOrCreate(
                ['id' => $data['id'] ?? null],
                collect($data)->only(['department_id', 'name', 'description', 'is_active'])->all()
            );

            if (array_key_exists('members', $data)) {
                $group->members()->delete();
                foreach (array_values($data['members']) as $index => $employeeId) {
                    if ($employeeId) {
                        $group->members()->create(['employee_id' => $employeeId, 'rotation_order' => $index + 1]);
                    }
                }
            }

            $fresh = $group->fresh('members');
            $this->auditLogService->record(
                $before ? 'rotation_group.updated' : 'rotation_group.created',
                $fresh,
                $before,
                $fresh->toArray(),
                $performedBy,
            );

            return $fresh;
        });
    }
}
