<?php

namespace App\Models\Hris;

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
        'filing_date' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'days_wpay' => 'float',
        'days_wopay' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
