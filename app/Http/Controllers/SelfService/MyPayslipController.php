<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollBatchRecord;
use Illuminate\View\View;

class MyPayslipController extends Controller
{
    public function index(): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        return view('self-service.my-payslip', [
            'empId' => $empId,
        ]);
    }

    public function print(int $recordId): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        $record = PayrollBatchRecord::query()
            ->with('batch')
            ->whereKey($recordId)
            ->where('emp_id', $empId)
            ->firstOrFail();

        $snapshot = $record->snapshot_json ?? [];

        return view('self-service.payslip-print', [
            'record' => $record,
            'batch' => $record->batch,
            'snapshot' => $snapshot,
            'employee' => $snapshot['employee'] ?? [],
            'earnings' => $snapshot['earnings'] ?? [],
            'statutory' => $snapshot['statutory_deductions'] ?? [],
            'programs' => $snapshot['program_deductions'] ?? [],
            'premiums' => $snapshot['additional_premiums'] ?? [],
            'loans' => $snapshot['loan_deductions'] ?? [],
            'tax' => $snapshot['tax'] ?? [],
            'totals' => $snapshot['totals'] ?? [],
            'backUrl' => route('self-service.payslip'),
        ]);
    }
}
