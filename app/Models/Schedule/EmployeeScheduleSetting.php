<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeScheduleSetting extends PayrollSchedulerModel
{
    protected $fillable = [
        'employee_id',
        'default_shift_code_id',
        'can_rotate_shift',
        'max_consecutive_duty_days',
        'max_night_shifts_per_month',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'can_rotate_shift' => 'boolean',
            'max_consecutive_duty_days' => 'integer',
            'max_night_shifts_per_month' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function defaultShiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class, 'default_shift_code_id');
    }
}
