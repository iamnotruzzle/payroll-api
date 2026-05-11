<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeePayroll extends Model
{
    protected $table = 'tbl_employee_payrolls';

    protected $fillable = [
        'emp_id',
        'payroll_generate_id',
        'payroll_item_id',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
