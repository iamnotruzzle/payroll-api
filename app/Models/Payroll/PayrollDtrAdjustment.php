<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollDtrAdjustment extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_dtr_adjustments';

    protected $fillable = [
        'emp_id',
        'dtr_date',
        'adjustment_type',
        'minutes',
        'remarks',
        'encoded_by',
    ];

    protected $casts = [
        'dtr_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
