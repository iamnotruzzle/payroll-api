<?php

namespace App\Livewire\Leave;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveService;
use App\Support\Hris\LeaveStatuses;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveRequests extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public int $perPage = 20;

    public bool $drawerOpen = false;

    public ?int $editingId = null;

    public string $empId = '';

    public ?int $leaveType = null;

    public string $filingDate = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $daysWpay = '';

    public string $daysWopay = '0';

    public string $leaveSpent = '';

    public string $commutation = '';

    public string $remarks = '';

    public string $employeeSearch = '';

    public function mount(): void
    {
        $this->filingDate = CarbonImmutable::today()->toDateString();
        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->recomputeDays();
    }

    public function updatedEndDate(): void
    {
        $this->recomputeDays();
    }

    public function create(): void
    {
        abort_unless($this->canRequest(), 403);

        $this->resetForm();
        $this->drawerOpen = true;
    }

    public function edit(int $leaveId): void
    {
        abort_unless(auth()->user()?->can('leave.view') || $this->canRequest(), 403);

        $leave = EmployeeLeave::query()->findOrFail($leaveId);
        abort_unless(app(LeaveService::class)->isPending($leave), 403);

        $this->editingId = $leave->leave_id;
        $this->empId = (string) $leave->emp_id;
        $this->leaveType = (int) $leave->leave_type;
        $this->filingDate = optional($leave->filing_date)?->toDateString() ?: CarbonImmutable::today()->toDateString();
        $this->startDate = optional($leave->start_date)?->toDateString() ?: '';
        $this->endDate = optional($leave->end_date)?->toDateString() ?: '';
        $this->daysWpay = (string) ($leave->days_wpay ?? '');
        $this->daysWopay = (string) ($leave->days_wopay ?? '0');
        $this->leaveSpent = (string) ($leave->leave_spent ?? '');
        $this->commutation = (string) ($leave->commutation ?? '');
        $this->remarks = (string) ($leave->remarks ?? '');
        $this->drawerOpen = true;
        $this->resetValidation();
    }

    public function save(LeaveService $leaveService): void
    {
        abort_unless($this->canRequest(), 403);

        $data = $this->validate([
            'empId' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
            'leaveType' => ['required', 'integer', 'exists:hris.tbl_leave_type,leave_type_id'],
            'filingDate' => ['required', 'date'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'daysWpay' => ['nullable', 'numeric', 'min:0'],
            'daysWopay' => ['nullable', 'numeric', 'min:0'],
            'leaveSpent' => ['nullable', 'string', 'max:20'],
            'commutation' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'emp_id' => $data['empId'],
            'leave_type' => (int) $data['leaveType'],
            'filing_date' => $data['filingDate'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'days_wpay' => $data['daysWpay'],
            'days_wopay' => $data['daysWopay'],
            'leave_spent' => $data['leaveSpent'] ?: null,
            'commutation' => $data['commutation'] ?: null,
            'remarks' => $data['remarks'] ?: null,
        ];

        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');

        if ($this->editingId) {
            $leave = EmployeeLeave::query()->findOrFail($this->editingId);
            $leaveService->update($leave, $payload, $actor);
            session()->flash('status', 'Leave request updated.');
        } else {
            $leaveService->apply($payload, $actor);
            session()->flash('status', 'Leave request filed.');
        }

        $this->drawerOpen = false;
        $this->resetForm();
    }

    public function cancelRequest(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless($this->canRequest() || auth()->user()?->can('leave.approve'), 403);

        $leave = EmployeeLeave::query()->findOrFail($leaveId);
        $actor = (string) (auth()->user()?->emp_id ?? auth()->user()?->username ?? 'system');
        $leaveService->cancel($leave, $actor);

        session()->flash('status', 'Leave request cancelled.');
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->resetForm();
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('leave.view')
            || auth()->user()?->can('leave.request')
            || auth()->user()?->can('leave.approve'),
            403
        );

        $query = EmployeeLeave::query()
            ->with(['leaveType', 'employee.department', 'statusLookup'])
            ->whereNotNull('start_date')
            ->whereNotIn('status', LeaveService::LEDGER_STATUS_IDS)
            ->when($this->search !== '', function ($builder) {
                $search = trim($this->search);
                $builder->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%")
                                ->orWhere('middlename', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter !== 'all', function ($builder) {
                $ids = LeaveStatuses::idsFor($this->statusFilter);
                if ($ids !== []) {
                    $builder->whereIn('status', $ids);
                }
            })
            ->orderByDesc('filing_date')
            ->orderByDesc('leave_id');

        return view('livewire.leave.leave-requests', [
            'leaves' => $query->paginate($this->perPage),
            'leaveTypes' => LeaveType::query()
                ->when(
                    \Illuminate\Support\Facades\Schema::connection('hris')->hasColumn('tbl_leave_type', 'to_display'),
                    fn ($q) => $q->where(function ($inner) {
                        $inner->where('to_display', 1)->orWhereNull('to_display');
                    })
                )
                ->orderBy('leave_name')
                ->get(),
            'employees' => $this->employeeOptions(),
            'canRequest' => $this->canRequest(),
            'leaveService' => app(LeaveService::class),
        ]);
    }

    private function canRequest(): bool
    {
        return (bool) auth()->user()?->can('leave.request');
    }

    private function recomputeDays(): void
    {
        if ($this->startDate === '' || $this->endDate === '') {
            return;
        }

        try {
            $start = CarbonImmutable::parse($this->startDate);
            $end = CarbonImmutable::parse($this->endDate);
            if ($end->lt($start)) {
                return;
            }
            $this->daysWpay = (string) ($start->diffInDays($end) + 1);
            if ($this->daysWopay === '') {
                $this->daysWopay = '0';
            }
        } catch (\Throwable) {
            // ignore incomplete dates while typing
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->empId = '';
        $this->leaveType = null;
        $this->filingDate = CarbonImmutable::today()->toDateString();
        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
        $this->daysWpay = '1';
        $this->daysWopay = '0';
        $this->leaveSpent = '';
        $this->commutation = '';
        $this->remarks = '';
        $this->employeeSearch = '';
        $this->resetValidation();
    }

    private function employeeOptions()
    {
        $search = trim($this->employeeSearch);

        return Employee::query()
            ->select(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'department_id', 'is_active'])
            ->with('department')
            ->where('is_active', 'Y')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(40)
            ->get();
    }
}
