<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payroll\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(private HolidayService $service) {}

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
            'holiday_date'  => 'required|date',
            'name'          => 'required|string|max:255',
            'holiday_type'  => 'required|string',
            'holiday_scope' => 'required|string',
            'label_code'    => 'required|string',
            'is_paid'       => 'required|boolean',
            'is_active'     => 'required|boolean',
        ]);

        if ($this->service->duplicateExists($data['holiday_date'])) {
            return response()->json(['message' => 'A holiday already exists for this date.'], 422);
        }

        return response()->json($this->service->save($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'holiday_date'  => 'required|date',
            'name'          => 'required|string|max:255',
            'holiday_type'  => 'required|string',
            'holiday_scope' => 'required|string',
            'label_code'    => 'required|string',
            'is_paid'       => 'required|boolean',
            'is_active'     => 'required|boolean',
        ]);

        if ($this->service->duplicateExists($data['holiday_date'], $id)) {
            return response()->json(['message' => 'A holiday already exists for this date.'], 422);
        }

        return response()->json($this->service->save($data, $id));
    }
}
