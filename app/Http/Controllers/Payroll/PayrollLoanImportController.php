<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollLoanImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollLoanImportController extends Controller
{
    public function __construct(private PayrollLoanImportService $service) {}

    public function template(Request $request): BinaryFileResponse
    {
        $mode = $request->query('mode') === 'additional_premiums' ? 'additional_premiums' : 'loans';
        $path = $this->service->buildTemplate($mode);
        $filename = $mode === 'additional_premiums'
            ? 'payroll_additional_premium_import_template.xlsx'
            : 'payroll_loan_due_import_template.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
