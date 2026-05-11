<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollAuditLog extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'payroll_generate_id',
        'action',
        'performed_by',
        'remarks',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
