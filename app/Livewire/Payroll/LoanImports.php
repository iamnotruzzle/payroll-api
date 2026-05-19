<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\PayrollLoanImport;
use App\Services\Payroll\PayrollLoanImportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class LoanImports extends Component
{
    use WithFileUploads;

    public $loanFile;

    public ?int $selectedImportId = null;

    public function render()
    {
        $imports = PayrollLoanImport::query()
            ->withCount('items')
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $selected = $this->selectedImportId
            ? PayrollLoanImport::with('items')->find($this->selectedImportId)
            : $imports->first()?->load('items');

        return view('livewire.payroll.loan-imports', [
            'imports' => $imports,
            'selected' => $selected,
        ]);
    }

    public function import(): void
    {
        $data = $this->validate([
            'loanFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file = $data['loanFile'];
        $storedPath = $file->store('payroll/loan-imports');
        $service = app(PayrollLoanImportService::class);
        $import = $service->import(
            Storage::path($storedPath),
            $file->getClientOriginalName(),
            $storedPath,
            auth()->user()?->emp_id,
        );

        $this->selectedImportId = $import->id;
        $this->loanFile = null;

        session()->flash(
            'status',
            "Imported {$import->total_rows} row(s): {$import->valid_rows} ready, {$import->invalid_rows} needing review."
        );
    }

    public function exportTemplate()
    {
        $path = app(PayrollLoanImportService::class)->buildTemplate();

        return response()->download($path, 'payroll_loan_due_import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function selectImport(int $id): void
    {
        $this->selectedImportId = $id;
    }
}
