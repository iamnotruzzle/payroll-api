<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class MyLeave extends Component
{
    public string $empId = '';

    public bool $showForm = false;

    public ?int $leaveType = null;

    public string $startDate = '';

    public string $endDate = '';

    public string $daysWpay = '1';

    public string $daysWopay = '0';

    public string $remarks = '';

    public function mount(?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.leave')
            || auth()->user()?->can('leave.request')
            || auth()->user()?->can('leave.view'),
            403
        );

        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);

        $this->startDate = CarbonImmutable::today()->toDateString();
        $this->endDate = CarbonImmutable::today()->toDateString();
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()?->can('self-service.leave') || auth()->user()?->can('leave.request'), 403);
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function updatedStartDate(): void
    {
        $this->recomputeDays();
    }

    public function updatedEndDate(): void
    {
        $this->recomputeDays();
    }

    public function submit(LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('self-service.leave') || auth()->user()?->can('leave.request'), 403);

        $data = $this->validate([
            'leaveType' => ['required', 'integer', 'exists:hris.tbl_leave_type,leave_type_id'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'daysWpay' => ['nullable', 'numeric', 'min:0'],
            'daysWopay' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $leaveService->apply([
            'emp_id' => $this->empId,
            'leave_type' => (int) $data['leaveType'],
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'],
            'days_wpay' => $data['daysWpay'],
            'days_wopay' => $data['daysWopay'],
            'remarks' => $data['remarks'] ?: null,
        ], $this->empId);

        $this->showForm = false;
        session()->flash('status', 'Your leave request was filed.');
    }

    public function cancelRequest(int $leaveId, LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('self-service.leave') || auth()->user()?->can('leave.request'), 403);

        $leave = EmployeeLeave::query()
            ->where('emp_id', $this->empId)
            ->whereKey($leaveId)
            ->firstOrFail();

        $leaveService->cancel($leave, $this->empId, 'Cancelled by employee.');
        session()->flash('status', 'Leave request cancelled.');
    }

    public function render(LeaveService $leaveService)
    {
        $employee = Employee::query()->with(['department', 'position'])->where('emp_id', $this->empId)->firstOrFail();

        $leaves = EmployeeLeave::query()
            ->with(['leaveType', 'statusLookup'])
            ->where('emp_id', $this->empId)
            ->whereNotNull('start_date')
            ->whereNotIn('status', LeaveService::LEDGER_STATUS_IDS)
            ->orderByDesc('filing_date')
            ->orderByDesc('leave_id')
            ->limit(40)
            ->get();

        $leaveTypes = LeaveType::query()
            ->when(
                Schema::connection('hris')->hasColumn('tbl_leave_type', 'to_display'),
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('to_display', 1)->orWhereNull('to_display');
                })
            )
            ->orderBy('leave_name')
            ->get();

        return view('livewire.self-service.my-leave', [
            'employee' => $employee,
            'leaves' => $leaves,
            'leaveTypes' => $leaveTypes,
            'leaveService' => $leaveService,
            'canFile' => (bool) (auth()->user()?->can('self-service.leave') || auth()->user()?->can('leave.request')),
        ]);
    }

    private function recomputeDays(): void
    {
        try {
            $start = CarbonImmutable::parse($this->startDate);
            $end = CarbonImmutable::parse($this->endDate);
            if ($end->gte($start)) {
                $this->daysWpay = (string) ($start->diffInDays($end) + 1);
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
