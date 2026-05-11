<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeeEligibility extends Model
{

    protected $table = 'tbl_employee_eligibility';
    protected $primaryKey = 'eligibility_id';

    protected $fillable = [
        'emp_id',
        'eligibility_title',
        'confer_date',
        'confer_place',
        'rating',
        'license_no',
        'exp_date',
    ];

    protected $casts = [
        'confer_date' => 'datetime',
        'exp_date' => 'datetime',
        'rating' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
