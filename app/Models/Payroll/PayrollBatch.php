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
        'gsis_days',
        'included_leave_type_ids',
        'employee_type',
        'generated_by',
        'snapshot_created_at',
        'remarks',
    ];

    protected $casts = [
        'division_id' => 'integer',
        'department_id' => 'integer',
        'working_days' => 'integer',
        'gsis_days' => 'integer',
        'included_leave_type_ids' => 'array',
        'snapshot_created_at' => 'datetime',
    ];

    public function records()
    {
        return $this->hasMany(PayrollBatchRecord::class);
    }
}
