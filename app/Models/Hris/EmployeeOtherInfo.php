<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeOtherInfo extends Model
{
    protected $connection = 'hris';
    protected $table = 'tbl_employee_otherinfo';
    protected $primaryKey = 'otherinfo_id';

    protected $fillable = [
        'emp_id',
        'title',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
