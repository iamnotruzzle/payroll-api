<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollBatchRecord;
use App\Services\Payroll\DailyTimeRecordPrintService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollPageController extends Controller
{
    public function dtr()
    {
        return redirect()->route('payroll.dtr-encoding');
    }

    public function dtrEncoding()
    {
        return view('payroll.dtr-encoding');
    }

    public function dailyAttendance()
    {
        return view('payroll.daily-attendance');
    }

    public function attendanceReport()
    {
        return view('payroll.attendance-report');
    }

    public function dtrPrintable(Request $request, DailyTimeRecordPrintService $dtrPrintService)
    {
        $data = $request->validate([
            'emp_id' => ['required', 'string', 'exists:hris.tbl_employee,emp_id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $payload = $dtrPrintService->buildPrintPayload(
            $data['emp_id'],
            (int) $data['month'],
            (int) $data['year']
        );

        abort_unless(
            $payload['employee']->department_id === auth()->user()?->employee?->department_id,
            404
        );

        return $dtrPrintService->pdfResponse($payload);
    }

    public function dtrPrintableBulk(Request $request, DailyTimeRecordPrintService $dtrPrintService)
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2100'],
            'employee_type' => ['nullable', 'string'],
        ]);

        $departmentId = auth()->user()?->employee?->department_id;
        abort_unless($departmentId !== null, 404);

        $employeeType = Employee::normalizeEmployeeTypes(
            $data['employee_type'] ?? Employee::EMPLOYEE_TYPE_PLANTILLA
        );

        $employees = Employee::query()
            ->where('department_id', $departmentId)
            ->where('is_active', 'Y')
            ->employeeType($employeeType)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id']);

        $payloads = $employees
            ->map(fn (Employee $employee) => $dtrPrintService->buildPrintPayload(
                (string) $employee->emp_id,
                (int) $data['month'],
                (int) $data['year']
            ))
            ->all();

        return $dtrPrintService->pdfBulkResponse($payloads);
    }

    public function historyPayslipPrint(int $recordId): View
    {
        $record = PayrollBatchRecord::query()
            ->with('batch')
            ->whereKey($recordId)
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
            'backUrl' => route('payroll.history'),
        ]);
    }

    public function dtrCorrectionRequests()
    {
        return view('payroll.dtr-correction-requests');
    }

    public function dtrCorrectionApprovers()
    {
        return view('payroll.dtr-correction-approvers');
    }

    public function fingerprintRegistration()
    {
        return view('payroll.fingerprint-registration');
    }

    public function mra()
    {
        return view('payroll.mra');
    }

    public function generationConfiguration()
    {
        return view('payroll.generation-configuration');
    }

    public function generation()
    {
        return view('payroll.generation');
    }

    public function hazardGeneration()
    {
        return view('payroll.generation-hazard');
    }

    public function medicareGeneration()
    {
        return view('payroll.generation-medicare');
    }

    public function loanImports()
    {
        return view('payroll.loan-imports');
    }

    public function loanReferences()
    {
        return view('payroll.loan-references');
    }

    public function additionalPremiums()
    {
        return view('payroll.additional-premiums');
    }

    public function compensations()
    {
        return view('payroll.compensations');
    }

    public function adjustmentTypes()
    {
        return view('payroll.adjustment-types');
    }

    public function deductionPrograms()
    {
        return view('payroll.deduction-programs');
    }

    public function statutoryContributions()
    {
        return view('payroll.statutory-contributions');
    }

    public function holidays()
    {
        return view('payroll.holidays');
    }

    public function history()
    {
        return view('payroll.history');
    }

    public function userManual()
    {
        return view('payroll.user-manual');
    }
}
