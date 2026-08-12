<?php

namespace App\Livewire\Employees;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeLeaveCreditLedger;
use App\Models\Hris\EmployeeLeaveLog;
use App\Services\Hris\LeaveService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EmployeeLeavePanel extends Component
{
    public string $empId;

    /** @var array<int, string> */
    public array $remarks = [];

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('leave.view')
            || auth()->user()?->can('leave.request')
            || auth()->user()?->can('leave.approve')
            || auth()->user()?->can('leave.credits'),
            403
        );
        $this->empId = $empId;
    }

    public function approve(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('leave.approve'), 403);

        $leave = $this->leaveForEmployee($leaveId);
        $leaveService->approve($leave, $this->actor(), $this->remarks[$leaveId] ?? null);

        session()->flash('status', 'Leave request approved.');
    }

    public function disapprove(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('leave.approve'), 403);

        $leave = $this->leaveForEmployee($leaveId);
        $leaveService->disapprove($leave, $this->actor(), $this->remarks[$leaveId] ?? null);

        session()->flash('status', 'Leave request disapproved.');
    }

    public function cancel(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless(
            auth()->user()?->can('leave.request') || auth()->user()?->can('leave.approve'),
            403
        );

        $leave = $this->leaveForEmployee($leaveId);
        $leaveService->cancel($leave, $this->actor(), $this->remarks[$leaveId] ?? null);

        session()->flash('status', 'Leave request cancelled.');
    }

    public function render(LeaveService $leaveService)
    {
        $employee = Employee::query()
            ->select(['emp_id', 'vacation_leave_credits', 'sick_leave_credits', 'date_gain_lc'])
            ->where('emp_id', $this->empId)
            ->firstOrFail();

        // Avoid correlated EXISTS on unindexed tbl_leave_log.leave_id (was ~9s+ on large logs).
        $leaves = EmployeeLeave::query()
            ->select([
                'leave_id',
                'emp_id',
                'leave_type',
                'filing_date',
                'start_date',
                'end_date',
                'days_wpay',
                'days_wopay',
                'status',
            ])
            ->with([
                'leaveType:leave_type_id,leave_name',
                'statusLookup:status_id,status_name',
            ])
            ->where('emp_id', $this->empId)
            ->whereNotNull('start_date')
            ->whereNotIn('status', LeaveService::LEDGER_STATUS_IDS)
            ->orderByDesc('filing_date')
            ->orderByDesc('leave_id')
            ->limit(15)
            ->get();

        $terminalLeaveIds = [];
        if ($leaves->isNotEmpty()) {
            $terminalLeaveIds = EmployeeLeaveLog::query()
                ->whereIn('leave_id', $leaves->pluck('leave_id'))
                ->whereIn('action', [
                    LeaveService::ACTION_CANCELLED,
                    LeaveService::ACTION_DISAPPROVED,
                ])
                ->distinct()
                ->pluck('leave_id')
                ->flip()
                ->all();
        }

        foreach ($leaves as $leave) {
            $leave->setAttribute('has_terminal_log', isset($terminalLeaveIds[$leave->leave_id]));
            $this->remarks[$leave->leave_id] ??= '';
        }

        $logs = EmployeeLeaveLog::query()
            ->where('emp_id', $this->empId)
            ->orderByDesc('log_id')
            ->limit(10)
            ->get(['log_id', 'leave_id', 'emp_id', 'action', 'credits', 'vlc', 'slc', 'remarks', 'created_at']);

        $ledgerRows = collect();
        if ($this->creditLedgerTableExists()) {
            $ledgerRows = EmployeeLeaveCreditLedger::query()
                ->where('emp_id', $this->empId)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        return view('livewire.employees.employee-leave-panel', [
            'employee' => $employee,
            'leaves' => $leaves,
            'logs' => $logs,
            'ledgerRows' => $ledgerRows,
            'leaveService' => $leaveService,
            'canApprove' => (bool) auth()->user()?->can('leave.approve'),
            'canCancel' => (bool) (auth()->user()?->can('leave.request') || auth()->user()?->can('leave.approve')),
        ]);
    }

    private function creditLedgerTableExists(): bool
    {
        return (bool) cache()->remember('hris.has_employee_leave_credit_ledger', 60, function () {
            return Schema::connection('hris')->hasTable('employee_leave_credit_ledger');
        });
    }

    private function leaveForEmployee(int $leaveId): EmployeeLeave
    {
        return EmployeeLeave::query()
            ->where('emp_id', $this->empId)
            ->where('leave_id', $leaveId)
            ->firstOrFail();
    }

    private function actor(): string
    {
        return (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
    }
}
