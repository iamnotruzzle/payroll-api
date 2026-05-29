<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollBatch extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_batches';

    protected $fillable = [
        'department_id',
        'division_id',
        'payroll_period',
        'payroll_type',
        'payroll_type_code',
        'working_days',
        'employee_type',
        'generated_by',
        'snapshot_created_at',
        'remarks',
    ];

    protected $casts = [
        'division_id' => 'integer',
        'department_id' => 'integer',
        'working_days' => 'integer',
        'snapshot_created_at' => 'datetime',
    ];

    public function records()
    {
        return $this->hasMany(PayrollBatchRecord::class);
    }
}
