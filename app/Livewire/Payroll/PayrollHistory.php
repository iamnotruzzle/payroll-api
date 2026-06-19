<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveType;
use Livewire\Component;
use Livewire\WithPagination;

use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;

class PayrollHistory extends Component
{
    use WithPagination;

    public ?string $period = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $selectedBatchId = null;

    public string $search = '';

    public function selectBatch(int $batchId): void
    {
        $this->selectedBatchId = $batchId;
    }

    public function getSelectedBatchRecordsProperty()
    {
        if (! $this->selectedBatchId) {
            return collect();
        }

        return PayrollBatchRecord::query()
            ->where('payroll_batch_id', $this->selectedBatchId)
            ->get();
    }

    public function getSelectedBatchProperty(): ?PayrollBatch
    {
        if (! $this->selectedBatchId) {
            return null;
        }

        return PayrollBatch::query()->find($this->selectedBatchId);
    }

    public function getBatchesProperty()
    {
        return PayrollBatch::query()

            ->when(
                $this->period,
                fn($q) =>
                $q->where('payroll_period', $this->period)
            )

            ->when(
                $this->dateFrom,
                fn($q) =>
                $q->whereDate('snapshot_created_at', '>=', $this->dateFrom)
            )

            ->when(
                $this->dateTo,
                fn($q) =>
                $q->whereDate('snapshot_created_at', '<=', $this->dateTo)
            )

            ->when(
                $this->search,
                fn($q) =>
                $q->where(function ($query) {
                    $query
                        ->where('payroll_period', 'like', '%' . $this->search . '%')
                        ->orWhere('payroll_type', 'like', '%' . $this->search . '%');
                })
            )

            ->latest('snapshot_created_at')

            ->paginate(10);
    }

    public function generationConfigurationFor(PayrollBatch $batch): array
    {
        $leaveTypeIds = $batch->included_leave_type_ids;

        return [
            'payroll_type' => $batch->payroll_type,
            'payroll_type_code' => $batch->payroll_type_code ?: null,
            'working_days' => $batch->working_days,
            'gsis_days' => $batch->gsis_days,
            'employee_type' => $this->employeeTypeLabel($batch->employee_type),
            'leave_type_ids_recorded' => is_array($leaveTypeIds),
            'leave_types' => $this->includedLeaveTypeLabels(is_array($leaveTypeIds) ? $leaveTypeIds : null),
        ];
    }

    public function render()
    {
        $batches = $this->batches;
        $records = $this->selectedBatchRecords;
        $selectedBatch = $this->selectedBatch;
        $selectedBatchConfiguration = $selectedBatch
            ? $this->generationConfigurationFor($selectedBatch)
            : null;
        $batchConfigurations = $batches
            ->getCollection()
            ->mapWithKeys(fn (PayrollBatch $batch) => [
                $batch->id => $this->generationConfigurationFor($batch),
            ])
            ->all();

        $compensationColumns = [];

        $loanColumns = [];

        foreach ($records as $record) {

            $snapshot = $record->snapshot_json ?? [];

            foreach (($snapshot['earnings']['compensations'] ?? []) as $key => $comp) {

                $compensationColumns[$key] = $comp['name'];
            }

            foreach (($snapshot['loan_deductions']['columns'] ?? []) as $key => $value) {

                $loanColumns[$key] = strtoupper(str_replace('_', ' ', $key));
            }
        }

        return view('livewire.payroll.payroll-history', [
            'batches' => $batches,
            'records' => $records,
            'selectedBatch' => $selectedBatch,
            'selectedBatchConfiguration' => $selectedBatchConfiguration,
            'batchConfigurations' => $batchConfigurations,
            'compensationColumns' => $compensationColumns,
            'loanColumns' => $loanColumns,
        ]);
    }

    private function employeeTypeLabel(?string $employeeType): string
    {
        if (! $employeeType) {
            return 'Not recorded';
        }

        return Employee::employeeTypeOptions()[$employeeType] ?? ucfirst($employeeType);
    }

    private function includedLeaveTypeLabels(?array $leaveTypeIds): array
    {
        if ($leaveTypeIds === null) {
            return ['Not recorded'];
        }

        $ids = collect($leaveTypeIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return ['None'];
        }

        $labels = LeaveType::query()
            ->whereIn('leave_type_id', $ids->all())
            ->pluck('leave_name', 'leave_type_id');

        return $ids
            ->map(fn (int $id) => $labels[$id] ?? "Leave #{$id}")
            ->all();
    }
}
