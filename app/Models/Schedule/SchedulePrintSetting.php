<?php

namespace App\Models\Schedule;

class SchedulePrintSetting extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'organization_name',
        'schedule_heading',
        'area_label',
        'logo_path',
        'logo_position',
        'logo_width',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'logo_width' => 'integer',
        ];
    }
}
