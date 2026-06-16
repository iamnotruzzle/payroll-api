<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollStatutoryContributionBracket extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_statutory_contribution_brackets';

    protected $fillable = [
        'statutory_contribution_id',
        'effective_start',
        'effective_end',
        'min_salary',
        'max_salary',
        'employee_rate',
        'employer_rate',
        'employee_fixed_amount',
        'employer_fixed_amount',
        'employee_cap',
        'employer_cap',
        'remarks',
    ];

    protected $casts = [
        'effective_start' => 'date',
        'effective_end' => 'date',
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'employee_rate' => 'decimal:4',
        'employer_rate' => 'decimal:4',
        'employee_fixed_amount' => 'decimal:2',
        'employer_fixed_amount' => 'decimal:2',
        'employee_cap' => 'decimal:2',
        'employer_cap' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
