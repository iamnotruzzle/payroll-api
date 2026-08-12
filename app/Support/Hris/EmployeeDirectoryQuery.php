<?php

namespace App\Support\Hris;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeDirectoryQuery
{
    public static function paginate(string $search = '', string $status = 'all', int $perPage = 20): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['department', 'position'])
            ->when($status === 'active', fn (Builder $q) => $q->where('is_active', 'Y'))
            ->when($status === 'inactive', fn (Builder $q) => $q->where('is_active', '!=', 'Y'))
            ->when($search !== '', function (Builder $query) use ($search) {
                $tokens = preg_split('/\s+/', trim($search)) ?: [];
                $query->where(function (Builder $query) use ($search, $tokens) {
                    $query->where('emp_id', 'like', "%{$search}%");
                    foreach ($tokens as $token) {
                        $query->orWhere('firstname', 'like', "%{$token}%")
                            ->orWhere('lastname', 'like', "%{$token}%")
                            ->orWhere('middlename', 'like', "%{$token}%");
                    }
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->paginate($perPage);
    }

    public static function findForProfile(string $empId): ?Employee
    {
        return Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $empId)
            ->first();
    }

    public static function departmentName(Employee $employee): ?string
    {
        return $employee->department?->department;
    }

    public static function positionName(Employee $employee): ?string
    {
        return $employee->position?->position_title
            ?? $employee->position?->position
            ?? $employee->position?->position_name
            ?? null;
    }

    public static function isActive(Employee $employee): bool
    {
        return $employee->is_active === 'Y';
    }

    /**
     * @return Collection<int, object{department_id:int|string,label:string}>
     */
    public static function departmentOptions(): Collection
    {
        return Department::query()
            ->orderBy('department')
            ->get(['department_id', 'department'])
            ->map(fn (Department $department) => (object) [
                'department_id' => $department->department_id,
                'label' => $department->department,
            ]);
    }
}
