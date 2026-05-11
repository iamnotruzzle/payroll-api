<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollMraReport extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_mra_reports';
    public $timestamps = false;

    protected $fillable = [
        'department_id',
        'period_start',
        'period_end',
        'status',
        'generated_by',
        'generated_at',
        'remarks',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'generated_at' => 'datetime',
    ];
}
