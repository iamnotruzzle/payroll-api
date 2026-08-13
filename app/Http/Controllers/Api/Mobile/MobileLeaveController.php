<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveService;
use App\Support\Hris\LeaveDates;
use App\Support\Hris\LeaveStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MobileLeaveController extends MobileController
{
    public function types(): JsonResponse
    {
        $types = LeaveType::query()
            ->when(
                Schema::connection('hris')->hasColumn('tbl_leave_type', 'to_display'),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('to_display', 1)->orWhereNull('to_display');
                })
            )
            ->orderBy('leave_name')
            ->get(['leave_type_id', 'leave_name', 'description', 'max_value']);

        return response()->json(['data' => $types]);
    }

    public function index(Request $request, LeaveService $leaveService): JsonResponse
    {
        $status = $request->get('status', 'active');
        $empId = $this->empId();

        $query = EmployeeLeave::query()
            ->with(['leaveType', 'statusLookup'])
            ->where('emp_id', $empId)
            ->whereNotNull('start_date')
            ->whereNotIn('status', LeaveService::LEDGER_STATUS_IDS);

        if ($status !== 'all') {
            $activeIds = array_values(array_unique(array_merge(
                LeaveStatuses::idsFor(LeaveStatuses::PENDING),
                LeaveStatuses::idsFor(LeaveStatuses::APPROVED),
            )));

            if ($activeIds !== []) {
                $query->whereIn('status', $activeIds);
            }
        }

        $leaves = $query
            ->orderByDesc('filing_date')
            ->orderByDesc('leave_id')
            ->limit(40)
            ->get()
            ->map(fn (EmployeeLeave $leave) => $this->leavePayload($leave, $leaveService));

        return response()->json(['data' => $leaves]);
    }

    public function store(Request $request, LeaveService $leaveService): JsonResponse
    {
        $data = $request->validate([
            'leave_type' => ['required', 'integer', 'exists:hris.tbl_leave_type,leave_type_id'],
            'date_mode' => ['required', 'in:pick,weekdays,calendar'],
            'start_date' => ['nullable', 'date', 'required_unless:date_mode,pick'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_unless:date_mode,pick'],
            'selected_dates' => ['nullable', 'required_if:date_mode,pick'],
            'days_wpay' => ['nullable', 'numeric', 'min:0'],
            'days_wopay' => ['nullable', 'numeric', 'min:0'],
            'auto_split_credits' => ['boolean'],
            'applicant_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $empId = $this->empId();
        $payload = [
            'emp_id' => $empId,
            'leave_type' => (int) $data['leave_type'],
            'date_mode' => $data['date_mode'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'selected_dates' => $data['selected_dates'] ?? null,
            'auto_split_credits' => array_key_exists('auto_split_credits', $data)
                ? (bool) $data['auto_split_credits']
                : true,
            'applicant_note' => $data['applicant_note'] ?? null,
        ];

        if (! $payload['auto_split_credits']) {
            $payload['days_wpay'] = $data['days_wpay'] ?? null;
            $payload['days_wopay'] = $data['days_wopay'] ?? null;
        }

        $leave = $leaveService->apply($payload, $empId)->load(['leaveType', 'statusLookup']);

        return response()->json([
            'message' => 'Your leave request was filed.',
            'leave' => $this->leavePayload($leave, $leaveService),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function leavePayload(EmployeeLeave $leave, LeaveService $leaveService): array
    {
        $statusId = $leave->status !== null ? (int) $leave->status : null;

        return [
            'leave_id' => $leave->leave_id,
            'leave_type' => $leave->leave_type,
            'leave_type_name' => $leave->leave_type_name,
            'status' => $statusId,
            'status_name' => $leave->status_name,
            'status_key' => LeaveStatuses::keyFor($statusId),
            'is_pending' => $leaveService->isPending($leave),
            'is_approved' => $leaveService->isApproved($leave),
            'filing_date' => optional($leave->filing_date)?->toDateString(),
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days_wpay' => $leave->days_wpay,
            'days_wopay' => $leave->days_wopay,
            'applicant_note' => $leave->applicant_note,
            'selected_dates' => LeaveDates::for($leave),
        ];
    }
}
