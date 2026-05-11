<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollTimekeepingSummary extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_timekeeping_summaries';
    public $timestamps = false;

    protected $fillable = [
        'payroll_generate_id',
        'emp_id',
        'total_work_days',
        'days_with_dtr',
        'regular_hours',
        'undertime_hours',
        'tardy_hours',
        'mra_hours',
        'leave_days_with_pay',
        'leave_days_without_pay',
        'absent_days',
        'created_at',
    ];

    protected $casts = [
        'regular_hours' => 'decimal:4',
        'undertime_hours' => 'decimal:4',
        'tardy_hours' => 'decimal:4',
        'mra_hours' => 'decimal:4',
        'leave_days_with_pay' => 'decimal:4',
        'leave_days_without_pay' => 'decimal:4',
        'absent_days' => 'decimal:4',
        'created_at' => 'datetime',
    ];
}
