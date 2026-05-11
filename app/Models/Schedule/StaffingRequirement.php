<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffingRequirement extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'shift_code_id',
        'day_of_week',
        'minimum_staff',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'day_of_week' => 'integer',
            'minimum_staff' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class);
    }
}
