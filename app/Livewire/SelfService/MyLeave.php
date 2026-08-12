<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveService;
use App\Support\Hris\LeaveDates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class MyLeave extends Component
{
    public string $empId = '';

    public bool $showForm = false;

    public ?int $leaveType = null;

    /** @var string pick|weekdays|calendar */
    public string $dateMode = LeaveDates::MODE_WEEKDAYS;

    public string $startDate = '';

    public string $endDate = '';

    public string $selectedDatesCsv = '';

    public string $daysWpay = '1';

    public string $daysWopay = '0';

    public bool $autoSplitCredits = true;

    public string $applicantNote = '';

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
        $this->recomputeFromMode();
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()?->can('self-service.leave') || auth()->user()?->can('leave.request'), 403);
        $this->showForm = true;
        $this->recomputeFromMode();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function updatedDateMode(): void
    {
        $this->recomputeFromMode();
    }

    public function updatedStartDate(): void
    {
        $this->recomputeFromMode();
    }

    public function updatedEndDate(): void
    {
        $this->recomputeFromMode();
    }

    public function updatedSelectedDatesCsv(): void
    {
        if ($this->dateMode === LeaveDates::MODE_PICK) {
            $this->recomputeFromMode();
        }
    }

    public function submit(LeaveService $leaveService): void
    {
        abort_unless(auth()->user()?->can('self-service.leave') || auth()->user()?->can('leave.request'), 403);

        $data = $this->validate([
            'leaveType' => ['required', 'integer', 'exists:hris.tbl_leave_type,leave_type_id'],
            'dateMode' => ['required', 'in:pick,weekdays,calendar'],
            'startDate' => ['nullable', 'date', 'required_unless:dateMode,pick'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate', 'required_unless:dateMode,pick'],
            'selectedDatesCsv' => ['nullable', 'string', 'required_if:dateMode,pick'],
            'daysWpay' => ['nullable', 'numeric', 'min:0'],
            'daysWopay' => ['nullable', 'numeric', 'min:0'],
            'autoSplitCredits' => ['boolean'],
            'applicantNote' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = [
            'emp_id' => $this->empId,
            'leave_type' => (int) $data['leaveType'],
            'date_mode' => $data['dateMode'],
            'start_date' => $data['startDate'] ?: null,
            'end_date' => $data['endDate'] ?: null,
            'selected_dates' => $data['selectedDatesCsv'] ?: null,
            'auto_split_credits' => (bool) $data['autoSplitCredits'],
            'applicant_note' => $data['applicantNote'] ?: null,
        ];

        if (! $payload['auto_split_credits']) {
            $payload['days_wpay'] = $data['daysWpay'];
            $payload['days_wopay'] = $data['daysWopay'];
        }

        $leaveService->apply($payload, $this->empId);

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
            'previewDayCount' => count(LeaveDates::normalize($this->selectedDatesCsv)),
        ]);
    }

    private function recomputeFromMode(): void
    {
        try {
            $selected = LeaveDates::resolveSelection(
                $this->dateMode,
                $this->startDate !== '' ? $this->startDate : null,
                $this->endDate !== '' ? $this->endDate : null,
                $this->selectedDatesCsv !== '' ? $this->selectedDatesCsv : null,
            );
        } catch (\Throwable) {
            return;
        }

        if ($selected === []) {
            return;
        }

        $this->selectedDatesCsv = LeaveDates::toCsv($selected);
        $this->startDate = $selected[0];
        $this->endDate = $selected[array_key_last($selected)];

        if ($this->autoSplitCredits) {
            $this->daysWpay = (string) count($selected);
            $this->daysWopay = '0';
        }
    }
}
