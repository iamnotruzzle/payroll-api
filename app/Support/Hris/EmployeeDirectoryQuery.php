<?php

namespace App\Support\Hris;

use App\Models\Hris\Department;
use App\Models\Hris\Employee as LegacyEmployee;
use App\Models\Hris\Position;
use App\Models\HrisV2\Employee as V2Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeDirectoryQuery
{
    public static function usesV2(): bool
    {
        return (bool) config('hris.use_v2', false);
    }

    public static function paginate(string $search = '', string $status = 'all', int $perPage = 20): LengthAwarePaginator
    {
        return self::usesV2()
            ? self::paginateV2($search, $status, $perPage)
            : self::paginateLegacy($search, $status, $perPage);
    }

    public static function findForProfile(string $empId): LegacyEmployee|V2Employee|null
    {
        return self::usesV2()
            ? V2Employee::query()
                ->with(['personal', 'governmentIds', 'contact', 'currentAssignment'])
                ->where('emp_id', $empId)
                ->first()
            : LegacyEmployee::query()
                ->with(['department', 'position'])
                ->where('emp_id', $empId)
                ->first();
    }

    public static function departmentName(LegacyEmployee|V2Employee $employee): ?string
    {
        if ($employee instanceof LegacyEmployee) {
            return $employee->department?->department;
        }

        $departmentId = $employee->currentAssignment?->department_id;

        if (! $departmentId) {
            return null;
        }

        return Department::query()->where('department_id', $departmentId)->value('department');
    }

    public static function positionName(LegacyEmployee|V2Employee $employee): ?string
    {
        if ($employee instanceof LegacyEmployee) {
            return $employee->position?->position_title ?? $employee->position?->position ?? $employee->position?->position_name ?? null;
        }

        $positionId = $employee->currentAssignment?->position_id;

        if (! $positionId) {
            return null;
        }

        $position = Position::query()->where('position_id', $positionId)->first();

        return $position?->position_title ?? $position?->position ?? $position?->position_name ?? null;
    }

    public static function isActive(LegacyEmployee|V2Employee $employee): bool
    {
        if ($employee instanceof LegacyEmployee) {
            return $employee->is_active === 'Y';
        }

        return (bool) $employee->is_active;
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

    private static function paginateLegacy(string $search, string $status, int $perPage): LengthAwarePaginator
    {
        return LegacyEmployee::query()
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

    private static function paginateV2(string $search, string $status, int $perPage): LengthAwarePaginator
    {
        return V2Employee::query()
            ->with(['currentAssignment', 'contact'])
            ->when($status === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
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
}
