<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Support\Hris\LeaveStatuses;
use Illuminate\View\View;

class LeavePageController extends Controller
{
    public function requests(): View
    {
        return view('leave.requests');
    }

    public function approvals(): View
    {
        return view('leave.approvals');
    }

    public function credits(): View
    {
        return view('leave.credits');
    }

    public function card(): View
    {
        return view('leave.card');
    }

    public function reports(): View
    {
        return view('leave.reports');
    }

    public function printRequest(int $leaveId): View
    {
        $leave = EmployeeLeave::query()
            ->with(['leaveType', 'employee.department', 'employee.position', 'statusLookup', 'logs'])
            ->findOrFail($leaveId);

        $user = auth()->user();
        if ($user?->can('self-service.leave')
            && ! $user?->can('leave.view')
            && ! $user?->can('leave.request')
            && ! $user?->can('leave.approve')
        ) {
            abort_unless((string) $leave->emp_id === (string) $user->emp_id, 403);
        }

        return view('leave.print-request', [
            'leave' => $leave,
            'statusName' => LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null),
            'backUrl' => $user?->can('leave.view') || $user?->can('leave.request') || $user?->can('leave.approve')
                ? route('leave.requests')
                : route('self-service.leave'),
        ]);
    }

    public function printCard(string $empId): View
    {
        $employee = Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $empId)
            ->firstOrFail();

        $leaves = EmployeeLeave::query()
            ->with(['leaveType', 'statusLookup'])
            ->where('emp_id', $empId)
            ->whereNotNull('start_date')
            ->whereNotIn('status', \App\Services\Hris\LeaveService::LEDGER_STATUS_IDS)
            ->orderByDesc('start_date')
            ->orderByDesc('leave_id')
            ->get();

        return view('leave.print-card', [
            'employee' => $employee,
            'leaves' => $leaves,
            'backUrl' => route('leave.card', ['empId' => $empId]),
        ]);
    }
}
