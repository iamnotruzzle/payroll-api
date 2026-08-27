<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollSourceBatch extends Model
{
    protected $connection = 'payroll';

    protected $fillable = ['kind', 'source', 'status', 'schema_version', 'original_filename', 'checksum', 'effective_date', 'effective_period', 'statistics', 'errors', 'payload', 'created_by', 'activated_by', 'activated_at', 'rolled_back_at'];

    protected $casts = ['effective_date' => 'date', 'statistics' => 'array', 'errors' => 'array', 'payload' => 'array', 'activated_at' => 'datetime', 'rolled_back_at' => 'datetime'];
}
