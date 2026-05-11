<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollSyncLog extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_sync_log';
    public $timestamps = false;

    protected $fillable = [
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
