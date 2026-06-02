<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollDtrCorrectionRequest;
use App\Services\Payroll\DtrCorrectionRequestService;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class DtrCorrectionRequests extends Component
{
    use WithFileUploads;

    public string $dtrDate;

    public string $requestType = PayrollDtrCorrectionRequest::TYPE_TIME_IN;

    public ?string $requestedTimeIn = null;

    public ?string $requestedTimeOut = null;

    public bool $requestedTimeoutNextday = false;

    public string $reason = '';

    public ?TemporaryUploadedFile $attachment = null;

    public array $approvalRemarks = [];

    public array $approvalRequestTypes = [];

    public array $approvalRequestedTimeIn = [];

    public array $approvalRequestedTimeOut = [];

    public array $approvalRequestedTimeoutNextday = [];

    public bool $showRequestForm = false;

    public ?int $editingRequestId = null;

    public string $editDtrDate = '';

    public string $editRequestType = PayrollDtrCorrectionRequest::TYPE_TIME_IN;

    public ?string $editRequestedTimeIn = null;

    public ?string $editRequestedTimeOut = null;

    public bool $editRequestedTimeoutNextday = false;

    public string $editReason = '';

    public function mount(): void
    {
        $this->dtrDate = CarbonImmutable::today()->toDateString();
    }

    public function render()
    {
        $employeeId = $this->employeeId();
        $service = app(DtrCorrectionRequestService::class);
        $pendingApprovals = PayrollDtrCorrectionRequest::query()
            ->with(['employee', 'requestedBy'])
            ->where('approver_emp_id', $employeeId)
            ->where('status', PayrollDtrCorrectionRequest::STATUS_PENDING)
            ->orderBy('requested_at')
            ->get();

        $this->syncApprovalDrafts($pendingApprovals);

        return view('livewire.payroll.dtr-correction-requests', [
            'configuredApprover' => $employeeId ? $service->configuredApproverFor($employeeId) : null,
            'myRequests' => PayrollDtrCorrectionRequest::query()
                ->with(['approver'])
                ->where('emp_id', $employeeId)
                ->orderByDesc('requested_at')
                ->limit(20)
                ->get(),
            'pendingApprovals' => $pendingApprovals,
            'requestTypes' => [
                PayrollDtrCorrectionRequest::TYPE_TIME_IN => 'Time In',
                PayrollDtrCorrectionRequest::TYPE_TIME_OUT => 'Time Out',
                PayrollDtrCorrectionRequest::TYPE_BOTH => 'Time In and Time Out',
            ],
        ]);
    }

    public function submit(): void
    {
        $data = $this->validate([
            'dtrDate' => ['required', 'date'],
            'requestType' => ['required', 'in:TIME_IN,TIME_OUT,BOTH'],
            'requestedTimeIn' => ['nullable', 'date_format:H:i'],
            'requestedTimeOut' => ['nullable', 'date_format:H:i'],
            'requestedTimeoutNextday' => ['boolean'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        $attachment = $this->storeAttachment();

        app(DtrCorrectionRequestService::class)->create([
            'emp_id' => $this->employeeId(),
            'dtr_date' => $data['dtrDate'],
            'request_type' => $data['requestType'],
            'requested_time_in' => $data['requestedTimeIn'],
            'requested_time_out' => $data['requestedTimeOut'],
            'requested_timeout_nextday' => $data['requestedTimeoutNextday'],
            'reason' => $data['reason'],
            ...$attachment,
        ], $this->employeeId());

        $this->reset(['requestedTimeIn', 'requestedTimeOut', 'requestedTimeoutNextday', 'reason', 'attachment']);
        $this->requestType = PayrollDtrCorrectionRequest::TYPE_TIME_IN;
        $this->showRequestForm = false;
        session()->flash('status', 'DTR correction request submitted.');
    }

    public function startEdit(int $requestId): void
    {
        $request = PayrollDtrCorrectionRequest::query()
            ->where('emp_id', $this->employeeId())
            ->where('status', PayrollDtrCorrectionRequest::STATUS_PENDING)
            ->findOrFail($requestId);

        $this->resetValidation();
        $this->editingRequestId = $request->id;
        $this->editDtrDate = $request->dtr_date->toDateString();
        $this->editRequestType = $request->request_type;
        $this->editRequestedTimeIn = $this->timeInputValue($request->requested_time_in);
        $this->editRequestedTimeOut = $this->timeInputValue($request->requested_time_out);
        $this->editRequestedTimeoutNextday = (bool) $request->requested_timeout_nextday;
        $this->editReason = $request->reason;
    }

    public function saveEdit(): void
    {
        $data = $this->validate([
            'editDtrDate' => ['required', 'date'],
            'editRequestType' => ['required', 'in:TIME_IN,TIME_OUT,BOTH'],
            'editRequestedTimeIn' => ['nullable', 'date_format:H:i'],
            'editRequestedTimeOut' => ['nullable', 'date_format:H:i'],
            'editRequestedTimeoutNextday' => ['boolean'],
            'editReason' => ['required', 'string', 'max:2000'],
        ]);

        $request = PayrollDtrCorrectionRequest::query()
            ->where('emp_id', $this->employeeId())
            ->where('status', PayrollDtrCorrectionRequest::STATUS_PENDING)
            ->findOrFail($this->editingRequestId);

        app(DtrCorrectionRequestService::class)->update($request, $this->employeeId(), [
            'dtr_date' => $data['editDtrDate'],
            'request_type' => $data['editRequestType'],
            'requested_time_in' => $data['editRequestedTimeIn'],
            'requested_time_out' => $data['editRequestedTimeOut'],
            'requested_timeout_nextday' => $data['editRequestedTimeoutNextday'],
            'reason' => $data['editReason'],
        ]);

        $this->cancelEdit();
        session()->flash('status', 'DTR correction request updated.');
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->reset(['editingRequestId', 'editDtrDate', 'editRequestedTimeIn', 'editRequestedTimeOut', 'editRequestedTimeoutNextday', 'editReason']);
        $this->editRequestType = PayrollDtrCorrectionRequest::TYPE_TIME_IN;
    }

    public function cancelRequest(int $requestId): void
    {
        $request = PayrollDtrCorrectionRequest::query()
            ->where('emp_id', $this->employeeId())
            ->where('status', PayrollDtrCorrectionRequest::STATUS_PENDING)
            ->findOrFail($requestId);

        app(DtrCorrectionRequestService::class)->cancel($request, $this->employeeId());
        session()->flash('status', 'DTR correction request cancelled.');
    }

    public function openRequestForm(): void
    {
        $this->resetValidation();
        $this->showRequestForm = true;
    }

    public function closeRequestForm(): void
    {
        $this->resetValidation();
        $this->showRequestForm = false;
    }

    public function approve(int $requestId): void
    {
        $request = PayrollDtrCorrectionRequest::findOrFail($requestId);

        app(DtrCorrectionRequestService::class)->approve(
            $request,
            $this->employeeId(),
            $this->approvalRemarks[$requestId] ?? null,
            [
                'request_type' => $this->approvalRequestTypes[$requestId] ?? $request->request_type,
                'requested_time_in' => $this->approvalRequestedTimeIn[$requestId] ?? null,
                'requested_time_out' => $this->approvalRequestedTimeOut[$requestId] ?? null,
                'requested_timeout_nextday' => $this->approvalRequestedTimeoutNextday[$requestId] ?? false,
            ]
        );

        unset($this->approvalRemarks[$requestId]);
        unset($this->approvalRequestTypes[$requestId], $this->approvalRequestedTimeIn[$requestId], $this->approvalRequestedTimeOut[$requestId], $this->approvalRequestedTimeoutNextday[$requestId]);
        session()->flash('status', 'DTR correction approved and applied.');
    }

    public function reject(int $requestId): void
    {
        $request = PayrollDtrCorrectionRequest::findOrFail($requestId);

        app(DtrCorrectionRequestService::class)->reject(
            $request,
            $this->employeeId(),
            $this->approvalRemarks[$requestId] ?? null
        );

        unset($this->approvalRemarks[$requestId]);
        session()->flash('status', 'DTR correction request rejected.');
    }

    public function showTimeIn(): bool
    {
        return in_array($this->requestType, [PayrollDtrCorrectionRequest::TYPE_TIME_IN, PayrollDtrCorrectionRequest::TYPE_BOTH], true);
    }

    public function showTimeOut(): bool
    {
        return in_array($this->requestType, [PayrollDtrCorrectionRequest::TYPE_TIME_OUT, PayrollDtrCorrectionRequest::TYPE_BOTH], true);
    }

    public function showEditTimeIn(): bool
    {
        return in_array($this->editRequestType, [PayrollDtrCorrectionRequest::TYPE_TIME_IN, PayrollDtrCorrectionRequest::TYPE_BOTH], true);
    }

    public function showEditTimeOut(): bool
    {
        return in_array($this->editRequestType, [PayrollDtrCorrectionRequest::TYPE_TIME_OUT, PayrollDtrCorrectionRequest::TYPE_BOTH], true);
    }

    public function approvalShowsTimeIn(int $requestId): bool
    {
        return in_array($this->approvalRequestTypes[$requestId] ?? null, [PayrollDtrCorrectionRequest::TYPE_TIME_IN, PayrollDtrCorrectionRequest::TYPE_BOTH], true);
    }

    public function approvalShowsTimeOut(int $requestId): bool
    {
        return in_array($this->approvalRequestTypes[$requestId] ?? null, [PayrollDtrCorrectionRequest::TYPE_TIME_OUT, PayrollDtrCorrectionRequest::TYPE_BOTH], true);
    }

    private function employeeId(): string
    {
        return (string) auth()->user()?->emp_id;
    }

    private function storeAttachment(): array
    {
        if (! $this->attachment) {
            return [];
        }

        return [
            'attachment_path' => $this->attachment->store('dtr-correction-requests', 'public'),
            'attachment_original_name' => $this->attachment->getClientOriginalName(),
            'attachment_mime_type' => $this->attachment->getMimeType(),
            'attachment_size' => $this->attachment->getSize(),
        ];
    }

    private function syncApprovalDrafts($pendingApprovals): void
    {
        foreach ($pendingApprovals as $request) {
            if (! isset($this->approvalRequestTypes[$request->id])) {
                $this->approvalRequestTypes[$request->id] = $request->request_type;
            }

            if (! array_key_exists($request->id, $this->approvalRequestedTimeIn)) {
                $this->approvalRequestedTimeIn[$request->id] = $this->timeInputValue($request->requested_time_in);
            }

            if (! array_key_exists($request->id, $this->approvalRequestedTimeOut)) {
                $this->approvalRequestedTimeOut[$request->id] = $this->timeInputValue($request->requested_time_out);
            }

            if (! array_key_exists($request->id, $this->approvalRequestedTimeoutNextday)) {
                $this->approvalRequestedTimeoutNextday[$request->id] = (bool) $request->requested_timeout_nextday;
            }
        }
    }

    private function timeInputValue(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
