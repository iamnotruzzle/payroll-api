<?php

namespace App\Models\Hris;

use App\Casts\SafeDate;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeave extends Model
{

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
        'start_date' => SafeDate::class,
        'end_date'   => SafeDate::class,
        'days_wpay' => 'float',
        'days_wopay' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type', 'leave_type_id');
    }

    public function getLeaveTypeNameAttribute()
    {
        return $this->leaveType ? $this->leaveType->leave_name : null;
    }
}