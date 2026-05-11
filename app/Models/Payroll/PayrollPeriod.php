<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_periods';

    protected $fillable = [
        'period_start',
        'period_end',
        'period_type',
        'is_locked',
        'locked_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
