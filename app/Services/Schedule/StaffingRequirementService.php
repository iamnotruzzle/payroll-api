<?php

namespace App\Services\Schedule;

use App\Models\Schedule\StaffingRequirement;

class StaffingRequirementService
{
    public function save(array $data, ?StaffingRequirement $requirement = null): StaffingRequirement
    {
        $requirement ??= new StaffingRequirement();
        $requirement->fill($data);
        $requirement->save();

        return $requirement;
    }
}
