<?php

namespace App\Livewire\Payroll;

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

    public function render()
    {
        $records = $this->selectedBatchRecords;

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
            'batches' => $this->batches,
            'records' => $records,
            'compensationColumns' => $compensationColumns,
            'loanColumns' => $loanColumns,
        ]);
    }
}
