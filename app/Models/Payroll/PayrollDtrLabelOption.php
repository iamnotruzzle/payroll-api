<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollDtrLabelOption extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_dtr_label_options';

    protected $fillable = [
        'code',
        'name',
        'counts_as_excused_workday',
        'counts_as_mra_hours',
        'counts_as_leave_with_pay',
        'counts_as_leave_without_pay',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'counts_as_excused_workday' => 'boolean',
        'counts_as_mra_hours' => 'boolean',
        'counts_as_leave_with_pay' => 'boolean',
        'counts_as_leave_without_pay' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
