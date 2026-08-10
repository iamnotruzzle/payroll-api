<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEligibility extends HrisV2Model
{
    protected $table = 'employee_eligibilities';

    protected $fillable = [
        'employee_id',
        'eligibility_lookup_id',
        'title',
        'confer_date',
        'confer_place',
        'rating',
        'license_no',
        'exp_date',
        'legacy_eligibility_id',
    ];

    protected function casts(): array
    {
        return [
            'confer_date' => 'date',
            'exp_date' => 'date',
            'rating' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
