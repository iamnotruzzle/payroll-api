<?php

namespace App\Services\Schedule;

use App\Models\Employee;
use App\Models\Schedule\EmployeeReference;

class EmployeeReferenceService
{
    public function syncActiveEmployees(): int
    {
        $count = 0;

        Employee::query()->where('is_active', 'Y')->orderBy('emp_id')->chunk(200, function ($employees) use (&$count) {
            foreach ($employees as $employee) {
                EmployeeReference::updateOrCreate(
                    ['hris_employee_id' => $employee->emp_id],
                    [
                        'timekeeping_employee_id' => $employee->emp_id,
                        'payroll_employee_id' => $employee->emp_id,
                        'display_name' => trim("{$employee->lastname}, {$employee->firstname}", ', '),
                        'is_active' => true,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }
}
