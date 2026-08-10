<?php

namespace App\Livewire\Leave;

use App\Models\Hris\EmployeeLeave;
use App\Services\Hris\LeaveService;
use App\Support\Hris\LeaveStatuses;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveApprovals extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 20;

    /** @var array<int, string> */
    public array $remarks = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function approve(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('leave.approve'), 403);

        $leave = EmployeeLeave::query()->findOrFail($leaveId);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $leaveService->approve($leave, $actor, $this->remarks[$leaveId] ?? null);

        session()->flash('status', 'Leave request approved.');
    }

    public function disapprove(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('leave.approve'), 403);

        $leave = EmployeeLeave::query()->findOrFail($leaveId);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $leaveService->disapprove($leave, $actor, $this->remarks[$leaveId] ?? null);

        session()->flash('status', 'Leave request disapproved.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('leave.approve') || auth()->user()?->can('leave.view'), 403);

        $pendingIds = LeaveStatuses::idsFor(LeaveStatuses::PENDING);

        $leaves = EmployeeLeave::query()
            ->with(['leaveType', 'employee.department', 'statusLookup'])
            ->when($pendingIds !== [], fn ($q) => $q->whereIn('status', $pendingIds))
            ->whereDoesntHave('logs', fn ($q) => $q->whereIn('action', [LeaveService::ACTION_CANCELLED, LeaveService::ACTION_DISAPPROVED]))
            ->when($this->search !== '', function ($builder) {
                $search = trim($this->search);
                $builder->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('filing_date')
            ->orderBy('leave_id')
            ->paginate($this->perPage);

        foreach ($leaves as $leave) {
            $this->remarks[$leave->leave_id] ??= '';
        }

        return view('livewire.leave.leave-approvals', [
            'leaves' => $leaves,
            'canApprove' => (bool) auth()->user()?->can('leave.approve'),
        ]);
    }
}
