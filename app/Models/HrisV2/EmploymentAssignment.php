<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentAssignment extends HrisV2Model
{
    protected $fillable = [
        'employee_id',
        'department_id',
        'division_id',
        'position_id',
        'employment_status_id',
        'step',
        'is_section_head',
        'effective_from',
        'effective_to',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'is_section_head' => 'boolean',
            'is_current' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
