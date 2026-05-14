<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTemplateDay extends PayrollSchedulerModel
{
    protected $connection = 'payroll_scheduler';

    protected $fillable = ['schedule_template_id', 'day_sequence', 'shift_code_id'];

    protected function casts(): array
    {
        return ['day_sequence' => 'integer'];
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class);
    }
}