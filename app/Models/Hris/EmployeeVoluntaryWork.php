<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeVoluntaryWork extends Model
{
    protected $table = 'tbl_employee_volwork';
    protected $primaryKey = 'volwork_id';

    protected $fillable = [
        'emp_id',
        'volname',
        'start_date',
        'end_date',
        'hrs',
        'position',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'hrs' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
