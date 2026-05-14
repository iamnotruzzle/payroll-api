<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffingRequirement extends PayrollSchedulerModel
{
    protected $connection = 'payroll_scheduler';

    protected $fillable = [
        'department_id',
        'rotation_group_id',
        'shift_code_id',
        'day_of_week',
        'minimum_staff',
        'maximum_staff',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'rotation_group_id' => 'integer',
            'day_of_week' => 'integer',
            'minimum_staff' => 'integer',
            'maximum_staff' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class);
    }

    public function rotationGroup(): BelongsTo
    {
        return $this->belongsTo(RotationGroup::class);
    }
}
