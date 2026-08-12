<?php

namespace App\Livewire\SelfService;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollBatchRecord;
use Livewire\Component;
use Livewire\WithPagination;

class MyPayslip extends Component
{
    use WithPagination;

    public string $empId = '';

    public string $period = '';

    public function mount(?string $empId = null): void
    {
        abort_unless(
            auth()->user()?->can('self-service.payslip')
            || auth()->user()?->can('self-service.access'),
            403
        );

        // Payslips come from local payroll_batch_records snapshots only.
        // Legacy POST payroll/consume stays on the external/legacy HRIS app — not used here.
        $this->empId = (string) ($empId ?: auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);
        abort_unless($this->empId === (string) (auth()->user()?->emp_id ?? ''), 403);
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->period = '';
        $this->resetPage();
    }

    public function render()
    {
        $employee = Employee::query()
            ->with(['department', 'position'])
            ->where('emp_id', $this->empId)
            ->firstOrFail();

        $records = PayrollBatchRecord::query()
            ->with('batch')
            ->where('emp_id', $this->empId)
            ->whereHas('batch', function ($query) {
                $query->when(
                    $this->period !== '',
                    fn ($q) => $q->where('payroll_period', $this->period)
                );
            })
            ->latest('id')
            ->paginate(12);

        return view('livewire.self-service.my-payslip', [
            'employee' => $employee,
            'records' => $records,
        ]);
    }
}
