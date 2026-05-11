<?php

namespace App\Services\Schedule;

use App\Models\Schedule\RotationGroup;
use Illuminate\Support\Facades\DB;

class RotationGroupService
{
    public function save(array $data): RotationGroup
    {
        return DB::connection('payroll_scheduler')->transaction(function () use ($data) {
            $group = RotationGroup::updateOrCreate(
                ['id' => $data['id'] ?? null],
                collect($data)->only(['name', 'description', 'is_active'])->all()
            );

            if (array_key_exists('members', $data)) {
                $group->members()->delete();
                foreach (array_values($data['members']) as $index => $employeeId) {
                    if ($employeeId) {
                        $group->members()->create(['employee_id' => $employeeId, 'rotation_order' => $index + 1]);
                    }
                }
            }

            return $group->fresh('members');
        });
    }
}
