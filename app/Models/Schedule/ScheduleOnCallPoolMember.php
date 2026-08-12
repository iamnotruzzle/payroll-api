<?php

namespace App\Models\Schedule;

class ScheduleOnCallPoolMember extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'unit_id',
        'is_second',
        'emp_id',
        'sort_order',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'unit_id' => 'integer',
            'is_second' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(ScheduleUnit::class, 'unit_id');
    }
}
