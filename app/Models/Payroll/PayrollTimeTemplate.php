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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
