<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeDtr extends Model
{
    protected $table = 'tbl_employee_dtr';
    protected $primaryKey = 'dtr_id';

    protected $fillable = [
        'emp_id',
        'timein_am',
        'timeout_am',
        'timein_pm',
        'timeout_pm',
        'timeout_nextday',
        'dtr_date',
        'machine_id',
    ];

    protected $casts = [
        'dtr_date' => 'datetime',
        'timeout_nextday' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
