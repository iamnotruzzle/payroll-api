<?php

namespace App\Models\Schedule;

class SchedulePrintLogo extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'label',
        'path',
        'x_position',
        'y_position',
        'width',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'x_position' => 'decimal:2',
            'y_position' => 'decimal:2',
            'width' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
