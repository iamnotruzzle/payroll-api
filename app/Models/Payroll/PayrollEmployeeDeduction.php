<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollEmployeeDeduction extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_employee_deductions';

    protected $fillable = [
        'emp_id',
        'payroll_deduction_id',
        'is_active',
        'is_percentage',
        'value',
        'effective_start',
        'effective_end',
        'reference_no',
        'remarks',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_percentage' => 'boolean',
        'value' => 'decimal:4',
        'effective_start' => 'datetime',
        'effective_end' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
