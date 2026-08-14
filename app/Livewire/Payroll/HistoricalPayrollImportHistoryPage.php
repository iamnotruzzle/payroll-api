<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\HistoricalPayrollImport;
use Livewire\Component;
use Livewire\WithPagination;

class HistoricalPayrollImportHistoryPage extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('payroll.generation.hr') || auth()->user()?->can('payroll.generation.accounting'), 403);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $imports = HistoricalPayrollImport::query()
            ->when($this->statusFilter === 'staged', fn ($query) => $query->where('status', 'staged'))
            ->when($this->statusFilter === 'finalized', fn ($query) => $query->where('status', 'applied'))
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($search) {
                    $query->where('original_filename', 'like', $search)
                        ->orWhere('payroll_period', 'like', $search)
                        ->orWhere('payroll_type_code', 'like', $search);
                });
            })
            ->latest('updated_at')
            ->paginate(15);

        return view('livewire.payroll.historical-payroll-import-history-page', compact('imports'));
    }
}
