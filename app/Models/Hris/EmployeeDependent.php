<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeDependent extends Model
{
    protected $table = 'tbl_employee_dependents';
    protected $primaryKey = 'dependent_id';

    protected $fillable = [
        'emp_id',
        'firstname',
        'middlename',
        'lastname',
        'extension',
        'occupation',
        'emp_busname',
        'emp_busadd',
        'tel_no',
        'birthdate',
        'gender',
        'relationship',
    ];

    protected $casts = [
        'birthdate' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
