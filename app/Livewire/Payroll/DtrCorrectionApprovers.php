<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollDtrCorrectionApprover;
use App\Services\Payroll\DtrCorrectionRequestService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DtrCorrectionApprovers extends Component
{
    public array $approverSelections = [];

    public array $selectedEmployees = [];

    public ?string $bulkApproverEmpId = null;

    public function mount(): void
    {
        $this->loadSelections();
    }

    public function render()
    {
        return view('livewire.payroll.dtr-correction-approvers', [
            'department' => auth()->user()?->employee?->department,
            'employees' => $this->employees(),
            'approverOptions' => $this->employees(),
        ]);
    }

    public function saveApprover(string $employeeId): void
    {
        $approverId = $this->approverSelections[$employeeId] ?? null;

        $this->validate([
            "approverSelections.{$employeeId}" => ['required', 'string'],
        ]);

        app(DtrCorrectionRequestService::class)->saveApproverConfiguration(
            $employeeId,
            $approverId,
            $this->employeeId(),
        );

        $this->loadSelections();
        session()->flash('status', 'DTR correction approver saved.');
    }

    public function saveBulkApprover(): void
    {
        $selectedEmployeeIds = $this->selectedEmployeeIds();

        $this->validate([
            'bulkApproverEmpId' => ['required', 'string'],
        ]);

        if ($selectedEmployeeIds === []) {
            throw ValidationException::withMessages([
                'selectedEmployees' => 'Select at least one employee to update.',
            ]);
        }

        if (in_array($this->bulkApproverEmpId, $selectedEmployeeIds, true)) {
            throw ValidationException::withMessages([
                'bulkApproverEmpId' => 'The approver cannot be included in the selected employees.',
            ]);
        }

        $service = app(DtrCorrectionRequestService::class);
        foreach ($selectedEmployeeIds as $employeeId) {
            $service->saveApproverConfiguration(
                $employeeId,
                $this->bulkApproverEmpId,
                $this->employeeId(),
            );
        }

        $this->clearSelection();
        $this->bulkApproverEmpId = null;
        $this->loadSelections();
        session()->flash('status', count($selectedEmployeeIds).' DTR correction approvers updated.');
    }

    public function selectAll(): void
    {
        foreach ($this->employees() as $employee) {
            $this->selectedEmployees[$employee->emp_id] = true;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedEmployees = [];
    }

    private function loadSelections(): void
    {
        $this->approverSelections = PayrollDtrCorrectionApprover::query()
            ->where('department_id', $this->departmentId())
            ->pluck('approver_emp_id', 'emp_id')
            ->all();
    }

    private function employees()
    {
        return Employee::query()
            ->with('position')
            ->where('department_id', $this->departmentId())
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'position_id', 'department_id']);
    }

    private function selectedEmployeeIds(): array
    {
        return collect($this->selectedEmployees)
            ->filter()
            ->keys()
            ->values()
            ->all();
    }

    private function departmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }

    private function employeeId(): string
    {
        return (string) auth()->user()?->emp_id;
    }
}
