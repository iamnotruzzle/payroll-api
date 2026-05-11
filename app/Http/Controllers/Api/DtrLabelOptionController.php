<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payroll\DtrLabelOptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DtrLabelOptionController extends Controller
{
    public function __construct(private DtrLabelOptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                       => 'required|string|max:100',
            'name'                       => 'required|string|max:255',
            'sort_order'                 => 'required|integer|min:0',
            'counts_as_excused_workday'  => 'required|boolean',
            'counts_as_mra_hours'        => 'required|boolean',
            'counts_as_leave_with_pay'   => 'required|boolean',
            'counts_as_leave_without_pay' => 'required|boolean',
            'is_active'                  => 'required|boolean',
        ]);

        return response()->json($this->service->save($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'code'                       => 'required|string|max:100',
            'name'                       => 'required|string|max:255',
            'sort_order'                 => 'required|integer|min:0',
            'counts_as_excused_workday'  => 'required|boolean',
            'counts_as_mra_hours'        => 'required|boolean',
            'counts_as_leave_with_pay'   => 'required|boolean',
            'counts_as_leave_without_pay' => 'required|boolean',
            'is_active'                  => 'required|boolean',
        ]);

        return response()->json($this->service->save($data, $id));
    }
}
