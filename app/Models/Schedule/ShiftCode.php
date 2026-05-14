<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftCode extends PayrollSchedulerModel
{
    protected $connection = 'payroll_scheduler';

    protected $fillable = [
        'code',
        'department_id',
        'name',
        'start_time',
        'end_time',
        'end_day_offset',
        'work_hours',
        'is_work_shift',
        'is_night_shift',
        'is_leave_code',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'end_day_offset' => 'integer',
            'department_id' => 'integer',
            'work_hours' => 'decimal:2',
            'is_work_shift' => 'boolean',
            'is_night_shift' => 'boolean',
            'is_leave_code' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }
}
