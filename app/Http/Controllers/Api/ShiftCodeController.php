<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule\ShiftCode;
use App\Services\Schedule\ShiftCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftCodeController extends Controller
{
    public function __construct(private ShiftCodeService $shiftCodeService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->shiftCodeService->list($request->only(['search', 'per_page', 'department_id'])));
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
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
        ]);

        $this->shiftCodeService->seedDefaults($request->user()?->getAuthIdentifier(), $data['department_id'] ?? null);

        return response()->json(['message' => 'Default shift codes are ready.']);
    }

    private function validated(Request $request, ?ShiftCode $shiftCode = null): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'integer'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('payroll_scheduler.shift_codes', 'code')
                    ->ignore($shiftCode?->id)
                    ->where(fn ($query) => $query->where('department_id', $request->input('department_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_day_offset' => ['nullable', 'integer', 'between:0,2'],
            'work_hours' => ['nullable', 'numeric', 'min:0', 'max:72'],
            'is_work_shift' => ['boolean'],
            'is_night_shift' => ['boolean'],
            'is_leave_code' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
