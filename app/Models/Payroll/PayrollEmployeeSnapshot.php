<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollEmployeeSnapshot extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_employee_snapshots';
    public $timestamps = false;

    protected $fillable = [
        'payroll_generate_id',
        'emp_id',
        'employee_name',
        'first_name',
        'middle_name',
        'last_name',
        'extension',
        'department_id',
        'department_name',
        'position_id',
        'position_title',
        'salary_grade',
        'step',
        'monthly_salary',
        'created_at',
    ];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
