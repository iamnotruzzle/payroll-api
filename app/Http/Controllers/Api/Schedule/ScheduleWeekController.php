<?php

namespace App\Http\Controllers\Api\Schedule;

use App\Http\Controllers\Controller;
use App\Services\Schedule\ScheduleWeekQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleWeekController extends Controller
{
    public function week(Request $request, ScheduleWeekQueryService $service): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'emp_id' => ['nullable', 'string', 'max:32'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:draft,reviewed,approved,locked'],
        ]);

        return response()->json($service->week($data));
    }

    public function attendance(Request $request, ScheduleWeekQueryService $service): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'emp_id' => ['nullable', 'string', 'max:32'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        return response()->json($service->attendancePresence($data));
    }
}
