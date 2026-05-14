<?php

namespace App\Services\Schedule;

use App\Models\Schedule\StaffingRequirement;
use Illuminate\Validation\ValidationException;

class StaffingRequirementService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function save(array $data, ?StaffingRequirement $requirement = null, ?string $performedBy = null): StaffingRequirement
    {
        $duplicate = StaffingRequirement::query()
            ->when($requirement, fn ($query) => $query->whereKeyNot($requirement->id))
            ->where('department_id', $data['department_id'] ?? null)
            ->where('rotation_group_id', $data['rotation_group_id'] ?? null)
            ->where('shift_code_id', $data['shift_code_id'])
            ->where('day_of_week', $data['day_of_week'] ?? null)
            ->where('effective_from', $data['effective_from'] ?? null)
            ->where('effective_to', $data['effective_to'] ?? null)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'shift_code_id' => 'A staffing requirement already exists for this department, group, shift, day, and effective date range.',
            ]);
        }

        if (
            array_key_exists('maximum_staff', $data)
            && $data['maximum_staff'] !== null
            && (int) $data['maximum_staff'] < (int) $data['minimum_staff']
        ) {
            throw ValidationException::withMessages([
                'maximum_staff' => 'Maximum staff must be greater than or equal to minimum staff.',
            ]);
        }

        $before = $requirement?->toArray();
        $requirement ??= new StaffingRequirement();
        $requirement->fill($data);
        $requirement->save();
        $this->auditLogService->record(
            $before ? 'staffing_requirement.updated' : 'staffing_requirement.created',
            $requirement,
            $before,
            $requirement->fresh()->toArray(),
            $performedBy,
        );

        return $requirement;
    }
}
