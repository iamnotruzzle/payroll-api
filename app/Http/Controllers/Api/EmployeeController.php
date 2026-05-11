<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hris\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate($request));
    }

    public function show(string $empId): JsonResponse
    {
        $employee = $this->service->findByEmpId($empId);

        if (! $employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        return response()->json($employee);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        return response()->json($this->service->search($request));
    }

    public function byDepartment(Request $request, int $departmentId): JsonResponse
    {
        return response()->json($this->service->byDepartment($request, $departmentId));
    }
}
