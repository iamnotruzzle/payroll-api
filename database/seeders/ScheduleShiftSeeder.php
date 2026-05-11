<?php

namespace Database\Seeders;

use App\Services\Schedule\ShiftCodeService;
use Illuminate\Database\Seeder;

class ScheduleShiftSeeder extends Seeder
{
    public function run(ShiftCodeService $shiftCodeService): void
    {
        $shiftCodeService->seedDefaults('seeder');
    }
}
