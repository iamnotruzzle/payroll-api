<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeReference extends Model
{
    protected $table = 'tbl_employee_ref';
    protected $primaryKey = 'reference_id';

    protected $fillable = [
        'emp_id',
        'ref_name',
        'ref_address',
        'ref_telno',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
