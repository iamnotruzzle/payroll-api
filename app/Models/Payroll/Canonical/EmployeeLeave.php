<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_leaves';

    protected $fillable = ['source_batch_id', 'external_id', 'emp_id', 'leave_type_external_id', 'start_date', 'end_date', 'days_wpay', 'days_wopay', 'is_cancelled', 'status', 'leave_dates'];

    protected $casts = ['start_date' => 'datetime', 'end_date' => 'datetime', 'days_wpay' => 'float', 'days_wopay' => 'float', 'is_cancelled' => 'boolean', 'leave_dates' => 'array'];

    protected static function booted(): void
    {
        static::addGlobalScope('approved', function ($query) {
            if (\Illuminate\Support\Facades\Schema::connection('payroll')->hasColumn('payroll_canonical_leaves', 'status')) {
                $query->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'approved')->orWhere('is_cancelled', true));
            }
        });
    }

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
        // The leave_type ID accessor also matches camel-case leaveType access.
        return $this->getRelationValue('leaveType')?->name;
    }
}
