<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hris\SalaryGradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryGradeController extends Controller
{
    public function __construct(private SalaryGradeService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
            salaryGrade: $request->filled('salary_grade') ? (int) $request->get('salary_grade') : null,
            tranche: $request->filled('tranche') ? (int) $request->get('tranche') : null,
            sort: $request->get('sort', 'effectivity_date'),
            direction: $request->get('direction', 'desc'),
        ));
    }

    public function salaryGradeOptions(): JsonResponse
    {
        return response()->json($this->service->salaryGradeOptions());
    }

    public function trancheOptions(): JsonResponse
    {
        return response()->json($this->service->trancheOptions());
    }
}
