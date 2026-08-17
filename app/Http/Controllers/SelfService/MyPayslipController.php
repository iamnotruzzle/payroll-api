<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollBatchRecord;
use App\Services\Payroll\PayslipPrintService;
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

    public function print(int $recordId, PayslipPrintService $payslipPrint): View
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($empId !== '', 404);

        $record = PayrollBatchRecord::query()
            ->with('batch')
            ->whereKey($recordId)
            ->where('emp_id', $empId)
            ->firstOrFail();

        return view('self-service.payslip-print', [
            'record' => $record,
            'batch' => $record->batch,
            'pdfBase64' => base64_encode($payslipPrint->binary($record)),
            'backUrl' => route('self-service.payslip'),
        ]);
    }
}
