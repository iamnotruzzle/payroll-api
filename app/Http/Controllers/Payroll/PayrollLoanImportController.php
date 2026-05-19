<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollLoanImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollLoanImportController extends Controller
{
    public function __construct(private PayrollLoanImportService $service) {}

    public function template(): BinaryFileResponse
    {
        $path = $this->service->buildTemplate();

        return response()->download($path, 'payroll_loan_due_import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
