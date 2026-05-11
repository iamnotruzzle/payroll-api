<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAssignment extends PayrollSchedulerModel
{
    protected $fillable = [
        'monthly_schedule_id',
        'employee_id',
        'schedule_date',
        'shift_code_id',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return ['schedule_date' => 'date'];
    }

    public function monthlySchedule(): BelongsTo
    {
        return $this->belongsTo(MonthlySchedule::class);
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class);
    }
}
