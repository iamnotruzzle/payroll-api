<?php

namespace App\Models\Hris;

use App\Casts\SafeDate;
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
        'start_date' => SafeDate::class,
        'end_date'   => SafeDate::class,
        'hrs' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
