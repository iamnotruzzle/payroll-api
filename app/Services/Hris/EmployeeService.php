<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class EmployeeService
{
    private function baseQuery()
    {
        return Employee::with(['position', 'department.division']);
    }

    private function applyActiveFilter($query, Request $request)
    {
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active') ? 'Y' : 'N');
        }

        return $query;
    }

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = $this->baseQuery()->orderBy('lastname');

        $this->applyActiveFilter($query, $request);

        return $query->paginate($request->get('per_page', 20));
    }

    public function findByEmpId(string $empId): ?Employee
    {
        return $this->baseQuery()
            ->where('emp_id', $empId)
            ->first();
    }

    public function search(Request $request): LengthAwarePaginator
    {
        $q = trim($request->get('q'));
        $tokens = preg_split('/\s+/', $q);

        $query = $this->baseQuery()
            ->where(function ($query) use ($q, $tokens) {
                // match full query against emp_id
                $query->where('emp_id', 'like', "%{$q}%");

                // each token must match at least one field
                foreach ($tokens as $token) {
                    $query->orWhere(function ($q2) use ($token) {
                        $q2->where('firstname', 'like', "%{$token}%")
                            ->orWhere('lastname', 'like', "%{$token}%")
                            ->orWhere('middlename', 'like', "%{$token}%");
                    });
                }

                // all tokens must appear somewhere across the full name
                $query->orWhere(function ($q2) use ($tokens) {
                    foreach ($tokens as $token) {
                        $q2->where(function ($q3) use ($token) {
                            $q3->where('firstname', 'like', "%{$token}%")
                                ->orWhere('lastname', 'like', "%{$token}%")
                                ->orWhere('middlename', 'like', "%{$token}%");
                        });
                    }
                });
            })
            ->orderBy('lastname');

        $this->applyActiveFilter($query, $request);

        return $query->paginate(20);
    }

    public function byDepartment(Request $request, int $departmentId): Collection
    {
        $query = $this->baseQuery()
            ->where('department_id', $departmentId)
            ->orderBy('lastname');

        $this->applyActiveFilter($query, $request);

        return $query->get();
    }
}
