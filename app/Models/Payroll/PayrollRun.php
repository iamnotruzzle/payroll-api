<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_generates';

    protected $fillable = [
        'payroll_period_id',
        'payroll_date',
        'payroll_type_id',
        'department_id',
        'department_name',
        'status',
        'generated_by',
        'gross_pay',
        'total_additions',
        'total_deductions',
        'net_pay',
    ];

    protected $casts = [
        'payroll_date' => 'datetime',
        'gross_pay' => 'decimal:2',
        'total_additions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
