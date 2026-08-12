<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Services\Attendance\DtrClientSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DtrClientSyncController extends Controller
{
    public function store(Request $request, DtrClientSyncService $sync): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payload' => ['required', 'array', 'min:1'],
        ], [
            'payload.required' => 'Payload is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'total_records' => 0,
                'synced' => 0,
                'failed' => true,
                'errors' => $validator->errors()->all(),
            ], 400);
        }

        /** @var array<int, array<string, mixed>> $payload */
        $payload = $request->input('payload', []);

        return response()->json($sync->sync($payload));
    }
}
