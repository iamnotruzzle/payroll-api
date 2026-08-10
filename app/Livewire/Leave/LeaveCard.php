<?php

namespace App\Livewire\Leave;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeLeaveLog;
use App\Services\Hris\LeaveCreditComputationService;
use App\Services\Hris\LeaveService;
use Livewire\Component;

class LeaveCard extends Component
{
    public string $empId = '';

    public string $search = '';

    public function mount(?string $empId = null): void
    {
        $this->empId = (string) ($empId ?: request()->query('empId', ''));
    }

    public function selectEmployee(string $empId): void
    {
        $this->empId = $empId;
        $this->search = '';
    }

    public function render(LeaveCreditComputationService $computer)
    {
        abort_unless(auth()->user()?->can('leave.view') || auth()->user()?->can('leave.credits'), 403);

        $employee = $this->empId !== ''
            ? Employee::query()->with(['department', 'position', 'employmentStatus'])->where('emp_id', $this->empId)->first()
            : null;

        $leaves = $employee
            ? EmployeeLeave::query()
                ->with(['leaveType', 'statusLookup'])
                ->where('emp_id', $employee->emp_id)
                ->whereNotNull('start_date')
                ->whereNotIn('status', LeaveService::LEDGER_STATUS_IDS)
                ->orderByDesc('start_date')
                ->orderByDesc('leave_id')
                ->limit(100)
                ->get()
            : collect();

        $logs = $employee
            ? EmployeeLeaveLog::query()
                ->where('emp_id', $employee->emp_id)
                ->orderByDesc('log_id')
                ->limit(50)
                ->get()
            : collect();

        $computed = $employee ? $computer->computeForEmployee($employee) : null;

        $matches = collect();
        if ($this->search !== '' && $this->empId === '') {
            $search = trim($this->search);
            $matches = Employee::query()
                ->with('department')
                ->where(function ($query) use ($search) {
                    $query->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                })
                ->orderBy('lastname')
                ->limit(20)
                ->get();
        }

        return view('livewire.leave.leave-card', [
            'employee' => $employee,
            'leaves' => $leaves,
            'logs' => $logs,
            'matches' => $matches,
            'computed' => $computed,
        ]);
    }
}
