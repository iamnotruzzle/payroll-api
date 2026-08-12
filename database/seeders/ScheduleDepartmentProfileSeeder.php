<?php

namespace Database\Seeders;

use App\Models\Schedule\ScheduleDepartmentProfile;
use App\Services\Schedule\ScheduleDivisionService;
use Illuminate\Database\Seeder;

class ScheduleDepartmentProfileSeeder extends Seeder
{
    /**
     * Seed CNO (Nursing Service) department profiles with schedulev2-parity flags.
     * Non-CNO departments stay without a row until first visit (simple defaults) or
     * until `schedule:provision-department-profiles --also-simple --apply`.
     */
    public function run(ScheduleDivisionService $divisionService): void
    {
        $defaults = $divisionService->cnoProfileDefaults();

        foreach ($divisionService->departmentsForDivision($divisionService->cnoDivisionId()) as $department) {
            ScheduleDepartmentProfile::query()->updateOrCreate(
                ['department_id' => (int) $department->department_id],
                $defaults
            );
        }
    }
}
