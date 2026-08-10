<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkExperience extends HrisV2Model
{
    protected $table = 'employee_work_experiences';

    protected $fillable = [
        'employee_id',
        'work_position',
        'work_status',
        'company_name',
        'company_address',
        'salary',
        'salary_grade',
        'step_inc',
        'start_date',
        'end_date',
        'is_government',
        'legacy_work_exp_id',
    ];

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_government' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
