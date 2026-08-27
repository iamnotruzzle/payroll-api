<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_leaves';

    protected $fillable = ['source_batch_id', 'external_id', 'emp_id', 'leave_type_external_id', 'start_date', 'end_date', 'days_wpay', 'days_wopay', 'is_cancelled'];

    protected $casts = ['start_date' => 'datetime', 'end_date' => 'datetime', 'days_wpay' => 'float', 'days_wopay' => 'float', 'is_cancelled' => 'boolean'];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_external_id', 'external_id');
    }

    public function getLeaveIdAttribute()
    {
        return $this->id;
    }

    public function getLeaveTypeAttribute()
    {
        return $this->leave_type_external_id;
    }

    public function getLeaveTypeNameAttribute()
    {
        return $this->leaveType?->name;
    }
}
