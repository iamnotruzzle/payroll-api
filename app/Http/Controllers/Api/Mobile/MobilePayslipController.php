<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Models\Payroll\PayrollBatchRecord;
use App\Services\Payroll\PayslipPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MobilePayslipController extends MobileController
{
    public function index(): JsonResponse
    {
        $records = PayrollBatchRecord::query()
            ->with('batch')
            ->where('emp_id', $this->empId())
            ->latest('id')
            ->limit(24)
            ->get()
            ->map(fn (PayrollBatchRecord $record) => $this->listPayload($record));

        return response()->json(['data' => $records]);
    }

    public function show(int $recordId): JsonResponse
    {
        $record = $this->ownedRecord($recordId);
        $snapshot = $record->snapshot_json ?? [];

        return response()->json([
            'payslip' => array_merge($this->listPayload($record), [
                'employee' => $snapshot['employee'] ?? [],
                'earnings' => $snapshot['earnings'] ?? [],
                'statutory_deductions' => $snapshot['statutory_deductions'] ?? [],
                'program_deductions' => $snapshot['program_deductions'] ?? [],
                'additional_premiums' => $snapshot['additional_premiums'] ?? [],
                'loan_deductions' => $snapshot['loan_deductions'] ?? [],
                'tax' => $snapshot['tax'] ?? [],
                'totals' => $snapshot['totals'] ?? [],
            ]),
        ]);
    }

    public function print(int $recordId, PayslipPrintService $payslipPrint): View
    {
        $record = $this->ownedRecord($recordId);
        return view('self-service.payslip-print', [
            'record' => $record,
            'batch' => $record->batch,
            'pdfBase64' => base64_encode($payslipPrint->binary($record)),
            'backUrl' => '#',
        ]);
    }

    private function ownedRecord(int $recordId): PayrollBatchRecord
    {
        return PayrollBatchRecord::query()
            ->with('batch')
            ->whereKey($recordId)
            ->where('emp_id', $this->empId())
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(PayrollBatchRecord $record): array
    {
        return [
            'id' => $record->id,
            'payroll_period' => $record->batch?->payroll_period,
            'payroll_type' => $record->batch?->payroll_type,
            'gross' => $record->gross,
            'net' => $record->net,
            'fifteenth' => $record->fifteenth,
            'thirtieth' => $record->thirtieth,
            'snapshot_created_at' => optional($record->batch?->snapshot_created_at)?->toIso8601String(),
            'print_url' => url('/api/mobile/payslips/'.$record->id.'/print'),
        ];
    }
}
