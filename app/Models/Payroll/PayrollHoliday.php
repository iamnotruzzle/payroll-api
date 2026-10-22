<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollHoliday extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_holidays';

    protected $fillable = [
        'holiday_date',
        'name',
        'holiday_type',
        'holiday_scope',
        'label_code',
        'is_paid',
        'is_active',
        'hris_holiday_id',
    ];

    protected $casts = [
        'holiday_date' => 'datetime',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
