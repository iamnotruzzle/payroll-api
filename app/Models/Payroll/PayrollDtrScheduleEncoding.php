<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollDtrScheduleEncoding extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_dtr_schedule_encodings';

    protected $fillable = [
        'emp_id',
        'dtr_date',
        'payroll_time_template_id',
        'encoded_by',
    ];

    protected $casts = [
        'dtr_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
