<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollStatutoryContributionBracket extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_statutory_contribution_brackets';

    protected $fillable = [
        'statutory_contribution_id',
        'min_salary',
        'max_salary',
        'employee_rate',
        'employer_rate',
        'employee_cap',
        'employer_cap',
        'remarks',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'employee_rate' => 'decimal:4',
        'employer_rate' => 'decimal:4',
        'employee_cap' => 'decimal:2',
        'employer_cap' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
