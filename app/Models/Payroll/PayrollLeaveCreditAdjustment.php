<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollLeaveCreditAdjustment extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_leave_credit_adjustments';
    public $timestamps = false;

    protected $fillable = [
        'mra_report_id',
        'emp_id',
        'employee_name',
        'leave_type',
        'beginning_balance',
        'adjustment_days',
        'ending_balance',
        'undertime_tardy_minutes',
        'remarks',
        'created_at',
    ];

    protected $casts = [
        'beginning_balance' => 'decimal:4',
        'adjustment_days' => 'decimal:4',
        'ending_balance' => 'decimal:4',
        'created_at' => 'datetime',
    ];
}
