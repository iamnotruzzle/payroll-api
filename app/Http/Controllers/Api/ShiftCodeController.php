<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule\ShiftCode;
use App\Services\Schedule\ShiftCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftCodeController extends Controller
{
    public function __construct(private ShiftCodeService $shiftCodeService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->shiftCodeService->list($request->only(['search', 'per_page'])));
    }

    public function store(Request $request): JsonResponse
    {
        $shiftCode = $this->shiftCodeService->save($this->validated($request), performedBy: $request->user()?->getAuthIdentifier());

        return response()->json($shiftCode, 201);
    }

    public function show(ShiftCode $shiftCode): JsonResponse
    {
        return response()->json($shiftCode);
    }

    public function update(Request $request, ShiftCode $shiftCode): JsonResponse
    {
        return response()->json($this->shiftCodeService->save($this->validated($request, $shiftCode), $shiftCode, $request->user()?->getAuthIdentifier()));
    }

    public function seedDefaults(Request $request): JsonResponse
    {
        $this->shiftCodeService->seedDefaults($request->user()?->getAuthIdentifier());

        return response()->json(['message' => 'Default shift codes are ready.']);
    }

    private function validated(Request $request, ?ShiftCode $shiftCode = null): array
    {
        $id = $shiftCode?->id ?? 'NULL';

        return $request->validate([
            'code' => ['required', 'string', 'max:20', "unique:payroll_scheduler.shift_codes,code,{$id}"],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_day_offset' => ['nullable', 'integer', 'between:0,2'],
            'is_work_shift' => ['boolean'],
            'is_night_shift' => ['boolean'],
            'is_leave_code' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
