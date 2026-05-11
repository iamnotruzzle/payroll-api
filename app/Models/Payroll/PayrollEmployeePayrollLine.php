<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollEmployeePayrollLine extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_employee_payroll_lines';

    protected $fillable = [
        'payroll_generate_id',
        'emp_id',
        'line_group',
        'code',
        'name',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
