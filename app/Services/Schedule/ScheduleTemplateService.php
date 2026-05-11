<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ScheduleTemplate;
use Illuminate\Support\Facades\DB;

class ScheduleTemplateService
{
    public function save(array $data): ScheduleTemplate
    {
        return DB::connection('payroll_scheduler')->transaction(function () use ($data) {
            $template = ScheduleTemplate::updateOrCreate(
                ['id' => $data['id'] ?? null],
                collect($data)->only(['name', 'department_id', 'rotation_group_id', 'is_active'])->all()
            );

            if (array_key_exists('days', $data)) {
                $template->days()->delete();
                foreach ($data['days'] as $index => $shiftCodeId) {
                    $template->days()->create([
                        'day_sequence' => $index + 1,
                        'shift_code_id' => $shiftCodeId,
                    ]);
                }
            }

            return $template->fresh('days.shiftCode');
        });
    }
}
