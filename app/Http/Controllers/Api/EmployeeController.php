<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hris\EmployeeSectionService;
use App\Services\Hris\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $service,
        private EmployeeSectionService $sectionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
            search: $request->get('q'),
            departmentId: $request->filled('department_id') ? (int) $request->get('department_id') : null,
            showInactive: $request->boolean('show_inactive'),
            sort: $request->get('sort', 'lastname'),
            direction: $request->get('direction', 'asc'),
        ));
    }

    public function show(string $empId): JsonResponse
    {
        $employee = $this->service->findByEmpId($empId);

        if (! $employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        return response()->json($employee);
    }

    public function setActive(Request $request, string $empId): JsonResponse
    {
        $request->validate(['is_active' => 'required|boolean']);

        $updated = $this->service->setActive($empId, $request->boolean('is_active'));

        if (! $updated) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        return response()->json(['message' => 'Status updated.']);
    }

    public function byDepartment(Request $request, int $departmentId): JsonResponse
    {
        return response()->json($this->service->byDepartment(
            departmentId: $departmentId,
            showInactive: $request->boolean('show_inactive'),
        ));
    }

    public function dependents(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->dependents($empId));
    }

    public function education(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->education($empId));
    }

    public function eligibilities(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->eligibilities($empId));
    }

    public function workExperiences(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->workExperiences($empId));
    }

    public function trainings(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->trainings($empId));
    }

    public function otherInfo(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->otherInfo($empId));
    }

    public function leaves(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->leaves($empId));
    }

    public function dtrs(string $empId): JsonResponse
    {
        return response()->json($this->sectionService->dtrs($empId));
    }
}
