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

    public bool $showRequestForm = false;

    public function mount(): void
    {
        $this->dtrDate = CarbonImmutable::today()->toDateString();
    }

    public function render()
    {
        $employeeId = $this->employeeId();
        $service = app(DtrCorrectionRequestService::class);

        return view('livewire.payroll.dtr-correction-requests', [
            'configuredApprover' => $employeeId ? $service->configuredApproverFor($employeeId) : null,
            'myRequests' => PayrollDtrCorrectionRequest::query()
                ->with(['approver'])
                ->where('emp_id', $employeeId)
                ->orderByDesc('requested_at')
                ->limit(20)
                ->get(),
            'pendingApprovals' => PayrollDtrCorrectionRequest::query()
                ->with(['employee', 'requestedBy'])
                ->where('approver_emp_id', $employeeId)
                ->where('status', PayrollDtrCorrectionRequest::STATUS_PENDING)
                ->orderBy('requested_at')
                ->get(),
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
            $this->approvalRemarks[$requestId] ?? null
        );

        unset($this->approvalRemarks[$requestId]);
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
}
