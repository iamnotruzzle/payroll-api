<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hris\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct(private PositionService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
            search: $request->get('q'),
            salaryGrade: $request->filled('salary_grade') ? (int) $request->get('salary_grade') : null,
            sort: $request->get('sort', 'position_title'),
            direction: $request->get('direction', 'asc'),
        ));
    }

    public function salaryGrades(): JsonResponse
    {
        return response()->json($this->service->salaryGrades());
    }
}
