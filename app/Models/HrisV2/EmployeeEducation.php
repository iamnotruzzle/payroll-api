<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEducation extends HrisV2Model
{
    protected $table = 'employee_educations';

    protected $fillable = [
        'employee_id',
        'education_level',
        'education_title',
        'school',
        'start_date',
        'end_date',
        'units',
        'year_graduated',
        'honors',
        'url',
        'legacy_education_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'units' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
