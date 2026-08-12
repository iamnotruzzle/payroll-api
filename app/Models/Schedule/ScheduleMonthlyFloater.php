<?php

namespace App\Models\Schedule;

class ScheduleMonthlyFloater extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'unit_id',
        'year',
        'month',
        'emp_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'unit_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(ScheduleUnit::class, 'unit_id');
    }
}
