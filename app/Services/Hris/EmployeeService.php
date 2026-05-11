<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryGrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EmployeeService
{
    private function baseQuery()
    {
        return Employee::with(['position', 'department.division']);
    }

    private function applyActiveFilter($query, bool $showInactive)
    {
        if (!$showInactive) {
            $query->where(function ($q) {
                $q->where('is_active', 'Y');
            });
        }

        return $query;
    }

    private function applySearch($query, string $search)
    {
        $tokens = preg_split('/\s+/', trim($search));

        if (count($tokens) === 1) {
            $token = $tokens[0];
            $query->where(function ($q) use ($token) {
                $q->where('emp_id', 'like', "%{$token}%")
                    ->orWhere('firstname', 'like', "%{$token}%")
                    ->orWhere('lastname', 'like', "%{$token}%")
                    ->orWhere('middlename', 'like', "%{$token}%");
            });
        } else {
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->where(function ($q2) use ($token) {
                        $q2->where('emp_id', 'like', "%{$token}%")
                            ->orWhere('firstname', 'like', "%{$token}%")
                            ->orWhere('lastname', 'like', "%{$token}%")
                            ->orWhere('middlename', 'like', "%{$token}%");
                    });
                }
            });
        }

        return $query;
    }

    private function attachSalaries(LengthAwarePaginator $paginated): LengthAwarePaginator
    {
        $paginated->getCollection()->transform(function ($employee) {
            $salaryGrade = $employee->position?->salary_grade;
            $employee->salary = $salaryGrade
                ? SalaryGrade::where('salary_grade', $salaryGrade)
                ->orderByDesc('effectivity_date')
                ->value('salary')
                : null;
            return $employee;
        });

        return $paginated;
    }

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?int $departmentId = null,
        bool $showInactive = false,
        string $sort = 'lastname',
        string $direction = 'asc'
    ): LengthAwarePaginator {
        $query = $this->baseQuery();

        $this->applyActiveFilter($query, $showInactive);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $this->applySearch($query, $search);
        }

        $query->orderBy(match ($sort) {
            'emp_id'     => 'emp_id',
            'department' => 'department_id',
            'position'   => 'position_id',
            default      => 'lastname',
        }, $direction === 'descending' ? 'desc' : 'asc');

        if ($sort === 'lastname') {
            $query->orderBy('firstname', $direction === 'descending' ? 'desc' : 'asc');
        }

        return $this->attachSalaries($query->paginate($perPage, ['*'], 'page', $page));
    }

    public function findByEmpId(string $empId): ?Employee
    {
        $employee = $this->baseQuery()->where('emp_id', $empId)->first();

        if ($employee?->position) {
            $employee->salary = SalaryGrade::where('salary_grade', $employee->position->salary_grade)
                ->orderByDesc('effectivity_date')
                ->value('salary');
        }

        return $employee;
    }

    public function byDepartment(int $departmentId, bool $showInactive = false): Collection
    {
        $query = $this->baseQuery()
            ->where('department_id', $departmentId)
            ->orderBy('lastname');

        $this->applyActiveFilter($query, $showInactive);

        return $query->get();
    }

    public function setActive(string $empId, bool $isActive): bool
    {
        return Employee::where('emp_id', $empId)
            ->update(['is_active' => $isActive ? 'Y' : 'N']) > 0;
    }
}
