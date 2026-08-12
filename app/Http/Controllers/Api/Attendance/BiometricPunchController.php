<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Services\Attendance\BiometricPunchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricPunchController extends Controller
{
    public function store(Request $request, BiometricPunchService $punches): JsonResponse
    {
        $data = $request->validate([
            'emp_id' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
            'machine_id' => ['nullable', 'string', 'max:50'],
            'innout' => ['required'],
        ]);

        $employee = Employee::query()->whereKey($data['emp_id'])->first();
        if (! $employee || $employee->is_active !== 'Y') {
            return response()->json([
                'field' => '',
                'message' => 'Employee is inactive or not found.',
                'status' => 'Warning!',
                'timein_am' => '',
                'timeout_am' => '',
                'timein_pm' => '',
                'timeout_pm' => '',
            ], 422);
        }

        $result = $punches->punch(
            (string) $data['emp_id'],
            (string) ($data['machine_id'] ?? '0'),
            $data['innout']
        );

        return response()->json($result);
    }
}
