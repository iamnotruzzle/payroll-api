<?php

namespace App\Models\Schedule;

class EmployeeReference extends PayrollSchedulerModel
{
    protected $fillable = [
        'hris_employee_id',
        'timekeeping_employee_id',
        'payroll_employee_id',
        'display_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
