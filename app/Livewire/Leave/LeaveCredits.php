<?php

namespace App\Livewire\Leave;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Services\Hris\LeaveCreditComputationService;
use App\Services\Hris\LeaveService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveCredits extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 20;

    public bool $drawerOpen = false;

    public bool $showComputed = true;

    public string $empId = '';

    public string $vacationLeaveCredits = '';

    public string $sickLeaveCredits = '';

    public string $remarks = '';

    /** @var array<string, mixed>|null */
    public ?array $computedDetail = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(string $empId, LeaveCreditComputationService $computer): void
    {
        abort_unless(auth()->user()?->can('leave.credits'), 403);

        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();
        $this->empId = $employee->emp_id;
        $this->vacationLeaveCredits = (string) ($employee->vacation_leave_credits ?? 0);
        $this->sickLeaveCredits = (string) ($employee->sick_leave_credits ?? 0);
        $this->remarks = '';
        $this->computedDetail = $computer->computeForEmployee($employee);
        $this->drawerOpen = true;
        $this->resetValidation();
    }

    public function useComputedBalances(): void
    {
        abort_unless(auth()->user()?->can('leave.credits'), 403);

        if (! $this->computedDetail) {
            return;
        }

        $this->vacationLeaveCredits = (string) ($this->computedDetail['vl']['computed'] ?? 0);
        $this->sickLeaveCredits = (string) ($this->computedDetail['sl']['computed'] ?? 0);
        $this->remarks = trim(($this->remarks !== '' ? $this->remarks.' ' : '').'Filled from hire-date recompute preview.');
    }

    public function save(LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('leave.credits'), 403);

        $data = $this->validate([
            'empId' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
            'vacationLeaveCredits' => ['required', 'numeric', 'min:0'],
            'sickLeaveCredits' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $leaveService->updateCredits($data['empId'], [
            'vacation_leave_credits' => $data['vacationLeaveCredits'],
            'sick_leave_credits' => $data['sickLeaveCredits'],
            'remarks' => $data['remarks'] ?? null,
        ], $actor);

        $this->drawerOpen = false;
        $this->computedDetail = null;
        session()->flash('status', 'Leave credits updated.');
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->computedDetail = null;
    }

    public function render(LeaveCreditComputationService $computer)
    {
        abort_unless(auth()->user()?->can('leave.credits') || auth()->user()?->can('leave.view'), 403);

        $employees = Employee::query()
            ->with(['department', 'employmentStatus'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->paginate($this->perPage);

        $undertimeByEmp = collect();
        if (Schema::connection('payroll')->hasTable('payroll_leave_credit_adjustments')) {
            $undertimeByEmp = PayrollLeaveCreditAdjustment::query()
                ->whereIn('emp_id', $employees->pluck('emp_id'))
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('emp_id');
        }

        $computedByEmp = collect();
        if ($this->showComputed) {
            $computedByEmp = $employees->getCollection()->mapWithKeys(
                fn (Employee $employee) => [$employee->emp_id => $computer->computeForEmployee($employee)]
            );
        }

        return view('livewire.leave.leave-credits', [
            'employees' => $employees,
            'undertimeByEmp' => $undertimeByEmp,
            'computedByEmp' => $computedByEmp,
            'canEdit' => (bool) auth()->user()?->can('leave.credits'),
        ]);
    }
}
