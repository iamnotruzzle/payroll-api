<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleUserUnit extends PayrollSchedulerModel
{
    protected $fillable = [
        'emp_id',
        'schedule_unit_id',
    ];

    protected function casts(): array
    {
        return [
            'schedule_unit_id' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ScheduleUnit::class, 'schedule_unit_id');
    }
}
