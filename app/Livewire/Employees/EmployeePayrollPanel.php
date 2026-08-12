<?php

namespace App\Livewire\Employees;

use App\Models\Payroll\PayrollBatchRecord;
use App\Models\Payroll\PayrollDeductionProgramMember;
use App\Models\Payroll\PayrollLoanImportItem;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class EmployeePayrollPanel extends Component
{
    public string $empId;

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('payroll.view')
            || auth()->user()?->can('payroll.generate')
            || auth()->user()?->can('payroll.approve'),
            403
        );
        $this->empId = $empId;
    }

    public function render()
    {
        $records = PayrollBatchRecord::query()
            ->with('batch')
            ->where('emp_id', $this->empId)
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $loans = collect();
        if (Schema::connection('payroll')->hasTable('payroll_loan_import_items')) {
            $loans = PayrollLoanImportItem::query()
                ->where(function ($q) {
                    $q->where('matched_emp_id', $this->empId)
                        ->orWhere('employee_id', $this->empId);
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        $deductionMembers = collect();
        if (Schema::connection('payroll')->hasTable('payroll_deduction_program_members')) {
            $deductionMembers = PayrollDeductionProgramMember::query()
                ->where('emp_id', $this->empId)
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        return view('livewire.employees.employee-payroll-panel', [
            'records' => $records,
            'loans' => $loans,
            'deductionMembers' => $deductionMembers,
        ]);
    }
}
