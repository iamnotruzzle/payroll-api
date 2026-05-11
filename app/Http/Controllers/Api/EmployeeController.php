<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private function applyActiveFilter($query, Request $request)
    {
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active') ? 'Y' : 'N');
        }

        return $query;
    }

    // paginated list of employees with optional active filter
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['position', 'department.division'])
            ->orderBy('lastname');

        $this->applyActiveFilter($query, $request);

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    // single employee details by emp_id
    public function show(string $empId): JsonResponse
    {
        $employee = Employee::with(['position', 'department.division'])
            ->where('emp_id', $empId)
            ->first();

        if (! $employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        return response()->json($employee);
    }

    // search employees by emp_id, firstname, lastname, or middlename with optional active filter
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        $q = $request->get('q');

        $query = Employee::with(['position', 'department.division'])
            ->where(function ($query) use ($q) {
                $query->where('emp_id', 'like', "%{$q}%")
                    ->orWhere('firstname', 'like', "%{$q}%")
                    ->orWhere('lastname', 'like', "%{$q}%")
                    ->orWhere('middlename', 'like', "%{$q}%");
            })
            ->orderBy('lastname');

        $this->applyActiveFilter($query, $request);

        return response()->json($query->paginate(20));
    }

    // list employees by department_id with optional active filter
    public function byDepartment(Request $request, int $departmentId): JsonResponse
    {
        $query = Employee::with(['position', 'department.division'])
            ->where('department_id', $departmentId)
            ->orderBy('lastname');

        $this->applyActiveFilter($query, $request);

        return response()->json($query->get());
    }
}
