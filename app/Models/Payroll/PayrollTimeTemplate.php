<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollTimeTemplate extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_time_templates';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'end_day_offset',
        'work_hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'work_hours' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
