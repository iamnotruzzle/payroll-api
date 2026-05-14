<?php

namespace App\Models\Schedule;

class ScheduleSignatory extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'purpose',
        'person_name',
        'designation',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
