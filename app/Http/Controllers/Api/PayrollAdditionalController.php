<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollAdditionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollAdditionalController extends Controller
{
    public function __construct(private PayrollAdditionalService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
            search: $request->get('q'),
            isPercentage: $request->has('is_percentage') ? $request->boolean('is_percentage') : null,
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
            sort: $request->get('sort', 'name'),
            direction: $request->get('direction', 'asc'),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'is_percentage' => 'required|boolean',
            'value'         => 'required|numeric|min:0',
            'is_active'     => 'required|boolean',
        ]);

        return response()->json($this->service->save($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'is_percentage' => 'required|boolean',
            'value'         => 'required|numeric|min:0',
            'is_active'     => 'required|boolean',
        ]);

        return response()->json($this->service->save($data, $id));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Deleted.']);
    }
}
