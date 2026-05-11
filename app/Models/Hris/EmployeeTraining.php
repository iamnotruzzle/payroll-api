<?php

namespace App\Models\Hris;

use App\Casts\SafeDate;
use Illuminate\Database\Eloquent\Model;

class EmployeeTraining extends Model
{
    protected $table = 'tbl_employee_training';
    protected $primaryKey = 'training_id';

    protected $fillable = [
        'emp_id',
        'training_name',
        'training_venue',
        'sponsor',
        'start_date',
        'end_date',
        'hrs',
        'type',
        'url',
    ];

    protected $casts = [
        'start_date' => SafeDate::class,
        'end_date'   => SafeDate::class,
        'hrs' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
