<?php

namespace App\Models\Schedule;

class ScheduleFloaterPoolMember extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'emp_id',
        'unit_id',
        'sort_order',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'unit_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(ScheduleUnit::class, 'unit_id');
    }
}
