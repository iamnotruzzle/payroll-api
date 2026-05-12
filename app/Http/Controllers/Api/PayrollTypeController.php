<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollTypeController extends Controller
{
    public function __construct(private PayrollTypeService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
            search: $request->get('q'),
            sort: $request->get('sort', 'name'),
            direction: $request->get('direction', 'asc'),
        ));
    }
}
