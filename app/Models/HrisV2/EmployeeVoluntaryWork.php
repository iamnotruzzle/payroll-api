<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeVoluntaryWork extends HrisV2Model
{
    protected $table = 'employee_voluntary_works';

    protected $fillable = [
        'employee_id',
        'organization_name',
        'start_date',
        'end_date',
        'hours',
        'position',
        'legacy_volwork_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'hours' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
