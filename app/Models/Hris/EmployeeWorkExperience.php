<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeWorkExperience extends Model
{
    protected $table = 'tbl_employee_work_exp';
    protected $primaryKey = 'work_exp_id';

    protected $fillable = [
        'emp_id',
        'work_position',
        'work_status',
        'company_name',
        'company_address',
        'salary',
        'sg',
        'step_inc',
        'start_date',
        'end_date',
        'is_government',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
