<?php

namespace App\Services\Payroll;

use App\Models\Payroll\Canonical\Department;
use App\Models\Payroll\Canonical\Employee;

class PayrollSyncScope
{
    public function employees(array $scope)
    {
        $query = Employee::query()->employeeType($scope['employee_types'] ?? [Employee::EMPLOYEE_TYPE_ALL]);
        if (! empty($scope['department_ids'])) {
            $query->whereIn('department_external_id', $scope['department_ids']);
        } elseif (! empty($scope['division_ids'])) {
            $query->whereIn('department_external_id', Department::query()->whereIn('division_external_id', $scope['division_ids'])->select('external_id'));
        }

        return $query;
    }

    public function matchesType(array $row, array $scope): bool
    {
        $types = $scope['employee_types'] ?? [Employee::EMPLOYEE_TYPE_ALL];
        if (in_array(Employee::EMPLOYEE_TYPE_ALL, $types, true)) {
            return true;
        }
        $map = ['plantilla' => 1, 'casual' => 2, 'part_time' => 3, 'contractual' => 4,
            'temporary' => 5, 'visiting_consultant' => 6, 'cos' => 7, 'probationary' => 8, 'intern' => 9, 'external' => 10];
        foreach ($types as $type) {
            $external = (bool) ($row['is_external'] ?? false);
            if ($type === Employee::EMPLOYEE_TYPE_EXTERNAL ? ($external || (int) $row['employment_status_id'] === 10)
                : (! $external && (int) $row['employment_status_id'] === ($map[$type] ?? -1))) {
                return true;
            }
        }

        return false;
    }
}
