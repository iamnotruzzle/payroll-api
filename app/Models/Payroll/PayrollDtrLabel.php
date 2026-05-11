<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollDtrLabel extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_dtr_labels';

    protected $fillable = [
        'emp_id',
        'dtr_date',
        'label',
        'remarks',
    ];

    protected $casts = [
        'dtr_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
