<?php

namespace App\Models\Hris;

use App\Casts\SafeCarbonDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLeave extends Model
{
    protected $connection = 'hris';
    protected $table = 'tbl_employee_leave';
    protected $primaryKey = 'leave_id';

    protected $fillable = [
        'emp_id',
        'leave_type',
        'leave_spent',
        'leave_spent_to',
        'commutation',
        'filing_date',
        'start_date',
        'end_date',
        'remarks',
        'days_wpay',
        'days_wopay',
        'status',
    ];

    protected $casts = [
        'filing_date' => 'date:Y-m-d',
        // SafeCarbonDate (not SafeDate): leave UI calls ->format() / ->toDateString().
        'start_date' => SafeCarbonDate::class,
        'end_date' => SafeCarbonDate::class,
        'days_wpay' => 'float',
        'days_wopay' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type', 'leave_type_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function statusLookup(): BelongsTo
    {
        return $this->belongsTo(LeaveStatusLookup::class, 'status', 'status_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLog::class, 'leave_id', 'leave_id');
    }

    public function getLeaveTypeNameAttribute()
    {
        return $this->leaveType ? $this->leaveType->leave_name : null;
    }

    public function getStatusNameAttribute(): ?string
    {
        return $this->statusLookup?->status_name;
    }
}
