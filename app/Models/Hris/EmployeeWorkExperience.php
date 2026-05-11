<?php

namespace App\Models\Hris;

use App\Casts\SafeDate;
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
        'start_date' => SafeDate::class,
        'end_date'   => SafeDate::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function getStartDateAttribute($value): ?string
    {
        if (!$value || str_starts_with($value, '0000-00-00')) return null;
        return $value;
    }

    protected function getEndDateAttribute($value): ?string
    {
        if (!$value || str_starts_with($value, '0000-00-00')) return null;
        return $value;
    }
}
