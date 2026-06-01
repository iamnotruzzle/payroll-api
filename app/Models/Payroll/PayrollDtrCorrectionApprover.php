<?php

namespace App\Models\Payroll;

use App\Models\Hris\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDtrCorrectionApprover extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_dtr_correction_approvers';

    protected $fillable = [
        'emp_id',
        'department_id',
        'approver_emp_id',
        'configured_by_emp_id',
    ];

    protected $casts = [
        'department_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_emp_id', 'emp_id');
    }
}
