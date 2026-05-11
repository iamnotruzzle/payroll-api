<?php

namespace App\Models\Hris;

use App\Casts\SafeDate;
use Illuminate\Database\Eloquent\Model;

class EmployeeEducation extends Model
{
    protected $table = 'tbl_employee_education';
    protected $primaryKey = 'education_id';

    protected $fillable = [
        'emp_id',
        'education_level',
        'education_title',
        'school',
        'start_date',
        'end_date',
        'units',
        'year_graduated',
        'honors',
        'url',
    ];

    protected $casts = [
        'start_date' => SafeDate::class,
        'end_date'   => SafeDate::class,
        'units' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
