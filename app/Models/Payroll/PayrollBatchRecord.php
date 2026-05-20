<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollBatchRecord extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_batch_records';

    protected $fillable = [
        'payroll_batch_id',
        'emp_id',
        'department_id',
        'gross',
        'net',
        'fifteenth',
        'thirtieth',
        'snapshot_json',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id');
    }
}
