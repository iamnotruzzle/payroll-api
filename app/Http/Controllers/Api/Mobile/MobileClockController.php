<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Payroll\MobileTimePunch;
use App\Services\Attendance\TimePunchService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileClockController extends MobileController
{
    public const MAX_DEVICE_SKEW_SECONDS = 300;

    public function status(TimePunchService $punches): JsonResponse
    {
        $status = $punches->status($this->empId());

        return response()->json([
            'today' => $status['today'],
            'can_time_in' => $status['can_time_in'],
            'can_time_out' => $status['can_time_out'],
            'open_previous_day' => $status['open_previous_day'],
            'dtr' => $this->dtrPayload($status['current_dtr']),
        ]);
    }

    public function store(Request $request, TimePunchService $punches): JsonResponse
    {
        $data = $request->validate([
            'punch' => ['required', 'in:time_in,time_out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'device_timestamp' => ['required', 'date'],
        ]);

        $deviceTimestamp = CarbonImmutable::parse($data['device_timestamp']);
        $now = CarbonImmutable::now();

        if (abs($deviceTimestamp->diffInSeconds($now)) > self::MAX_DEVICE_SKEW_SECONDS) {
            throw ValidationException::withMessages([
                'device_timestamp' => 'Device time is more than 5 minutes off the server clock.',
            ]);
        }

        $empId = $this->empId();
        $result = $punches->punch($empId, $data['punch'], $now);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'dtr' => $this->dtrPayload($result['dtr']),
            ], 422);
        }

        MobileTimePunch::query()->create([
            'emp_id' => $empId,
            'dtr_id' => $result['dtr']?->dtr_id,
            'punch_type' => $data['punch'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'device_timestamp' => $deviceTimestamp,
            'recorded_at' => $now,
        ]);

        return response()->json([
            'message' => $result['message'],
            'dtr' => $this->dtrPayload($result['dtr']),
        ]);
    }
}
