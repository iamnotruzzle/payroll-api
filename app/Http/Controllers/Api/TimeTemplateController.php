<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payroll\TimeTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeTemplateController extends Controller
{
    public function __construct(private TimeTemplateService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->paginate(
            page: (int) $request->get('page', 1),
            perPage: (int) $request->get('per_page', 10),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'start_time'     => 'required|date_format:H:i:s',
            'end_time'       => 'required|date_format:H:i:s',
            'end_day_offset' => 'required|integer|min:0|max:7',
            'work_hours'     => 'required|numeric|min:0|max:168',
            'is_active'      => 'required|boolean',
        ]);

        $template = $this->service->save($validated);

        return response()->json($template, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'start_time'     => 'required|date_format:H:i:s',
            'end_time'       => 'required|date_format:H:i:s',
            'end_day_offset' => 'required|integer|min:0|max:7',
            'work_hours'     => 'required|numeric|min:0|max:168',
            'is_active'      => 'required|boolean',
        ]);

        $template = $this->service->save($validated, $id);

        return response()->json($template);
    }
}
