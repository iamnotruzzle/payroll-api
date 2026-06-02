<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use App\Models\Hris\EmployeeLeave;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use FPDF;

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

    public function dtrPrintable(Request $request)
    {
        $data = $request->validate([
            'emp_id' => ['required', 'string', 'exists:mysql.tbl_employee,emp_id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:1900,2100'],
        ]);

        $employee = Employee::with(['department', 'position'])
            ->where('emp_id', $data['emp_id'])
            ->firstOrFail();

        abort_unless($employee->department_id === auth()->user()?->employee?->department_id, 404);

        $period = CarbonImmutable::create((int) $data['year'], (int) $data['month'], 1);
        $from = $period->startOfMonth();
        $to = $period->endOfMonth();

        $dtrs = EmployeeDtr::query()
            ->where('emp_id', $employee->emp_id)
            ->whereBetween('dtr_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($dtr) => $dtr->dtr_date->toDateString());

        $adjustments = PayrollDtrAdjustment::query()
            ->where('emp_id', $employee->emp_id)
            ->whereBetween('dtr_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn ($adjustment) => $adjustment->dtr_date->toDateString());

        $labels = PayrollDtrLabel::query()
            ->where('emp_id', $employee->emp_id)
            ->whereBetween('dtr_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($label) => $label->dtr_date->toDateString());
        $labelOptions = PayrollDtrLabelOption::query()
            ->get()
            ->keyBy('code');
        $holidays = PayrollHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
        $leaveDates = $this->dtrLeaveDates($employee->emp_id, $from, $to);

        $rows = collect(range(1, 31))->map(function (int $day) use ($period, $to, $dtrs, $adjustments, $labels, $labelOptions, $holidays, $leaveDates) {
            if ($day > $to->day) {
                return [
                    'day' => $day,
                    'timein_am' => '',
                    'timeout_am' => '',
                    'timein_pm' => '',
                    'timeout_pm' => '',
                    'undertime_hours' => '',
                    'undertime_minutes' => '',
                    'label' => '',
                ];
            }

            $dateValue = $period->setDay($day);
            $date = $dateValue->toDateString();
            $dtr = $dtrs->get($date);
            $minutes = (int) $adjustments->get($date, collect())->sum('minutes');
            $label = $labels->get($date);
            $holiday = $holidays->get($date);
            $leave = $leaveDates[$date] ?? null;
            $weekendLabel = $dateValue->isWeekend() ? $dateValue->format('l') : null;
            $timeinAm = $this->dtrTime($dtr?->timein_am);
            $timeoutAm = $this->dtrTime($dtr?->timeout_am);
            $timeinPm = $this->dtrTime($dtr?->timein_pm);
            $timeoutPm = $dtr?->timeout_nextday
                ? CarbonImmutable::parse($dtr->timeout_nextday)->format('h:i A')
                : $this->dtrTime($dtr?->timeout_pm);
            $hasPunches = filled($timeinAm) || filled($timeoutAm) || filled($timeinPm) || filled($timeoutPm);

            return [
                'day' => $day,
                'timein_am' => $timeinAm,
                'timeout_am' => $timeoutAm,
                'timein_pm' => $timeinPm,
                'timeout_pm' => $timeoutPm,
                'undertime_hours' => $minutes > 0 ? (string) intdiv($minutes, 60) : '',
                'undertime_minutes' => $minutes > 0 ? (string) ($minutes % 60) : '',
                'label' => ! $hasPunches ? $this->dtrLabelText($label, $labelOptions, $leave, $holiday, $weekendLabel) : '',
            ];
        });

        $pdf = new FPDF();
        $pdf->SetTitle('Daily Time Record', true);
        $pdf->AddPage();

        $data = [
            'employee' => $employee,
            'period' => $period,
            'rows' => $rows,
            'regularHours' => '',
            'saturdayHours' => '',
            'verifierName' => 'MARIA LOURDES K. OTAYZA, MD, MHA, CESO V, FPOGS',
            'verifierDesignation' => 'Medical Center Chief II',
        ];

        $this->drawDtrCopy($pdf, 5, false, $data);
        $this->drawDtrCopy($pdf, 110, true, $data);

        return response($pdf->Output('S'), 200)
            ->header('Content-Type', 'application/pdf');
    }

    public function dtrCorrectionRequests()
    {
        return view('payroll.dtr-correction-requests');
    }

    public function dtrCorrectionApprovers()
    {
        return view('payroll.dtr-correction-approvers');
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

    private function dtrTime(?string $time): string
    {
        return filled($time) ? CarbonImmutable::parse($time)->format('h:i A') : '';
    }

    private function dtrLabelText(?PayrollDtrLabel $label, $labelOptions, ?array $leave, ?PayrollHoliday $holiday, ?string $weekendLabel): string
    {
        if ($holiday) {
            return trim((string) ($holiday->name ?: $holiday->label_code ?: 'Holiday'));
        }

        if ($weekendLabel) {
            return $weekendLabel;
        }

        if ($leave) {
            return trim((string) $leave['name']);
        }

        if ($label) {
            $code = (string) $label->label;
            $fallback = (string) ($labelOptions->get($code)?->name ?? str_replace('_', ' ', $code));

            return trim((string) (
                $label->remarks
                ?: $fallback
            ));
        }

        return '';
    }

    private function dtrLeaveDates(string $employeeId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = [];
        $leaves = EmployeeLeave::query()
            ->with('leaveType')
            ->where('emp_id', $employeeId)
            ->where('status', 0)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        foreach ($leaves as $leave) {
            $leaveName = $leave->leave_type_name
                ?: $leave->leaveType?->leave_name
                ?: 'Leave';

            for ($date = CarbonImmutable::parse($leave->start_date); $date->lessThanOrEqualTo(CarbonImmutable::parse($leave->end_date)); $date = $date->addDay()) {
                if ($date->betweenIncluded($from, $to)) {
                    $dates[$date->toDateString()] = [
                        'code' => ((float) $leave->days_wopay > 0 && (float) $leave->days_wpay <= 0)
                            ? 'LEAVE_WITHOUT_PAY'
                            : 'LEAVE_WITH_PAY',
                        'name' => $leaveName,
                    ];
                }
            }
        }

        return $dates;
    }

    private function drawDtrCopy(FPDF $pdf, float $xInitial, bool $rightCopy, array $data): void
    {
        $employee = $data['employee'];
        $period = $data['period'];
        $rows = $data['rows'];
        $titleWidth = $rightCopy ? 105 : 35;
        $footerX = $rightCopy ? 110 : 10;
        $indent = $rightCopy ? 0 : 100;
        $tableY = 45;
        $bodyY = 57;
        $rowHeight = 5;
        $cellWidth = 13;
        $columns = [7, $cellWidth, $cellWidth, $cellWidth, $cellWidth, $cellWidth, $cellWidth];
        $employeeName = trim(implode(' ', array_filter([
            trim($employee->lastname.', '.$employee->firstname.' '.($employee->middlename ? mb_substr($employee->middlename, 0, 1).'.' : '')),
            $employee->extension ?? null,
        ])));

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetY(5);
        $pdf->SetX($xInitial);
        $pdf->Cell($titleWidth, 0, 'Civil Service No. 48', 0, 2, 'L');
        $pdf->Cell($titleWidth, 5, '', 0, 2, 'L');
        $pdf->SetX($titleWidth);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($titleWidth, 5, 'DAILY TIME RECORD', 0, 2, 'C');
        $pdf->SetFont('Arial', '', 6);
        $pdf->Cell($titleWidth, 2, '-----o0o-----', 0, 2, 'C');
        $pdf->Cell($titleWidth, 8, '', 0, 2, 'C');
        $pdf->SetFontSize(8);
        $pdf->Cell($titleWidth, 0, $this->pdfText($employeeName), 0, 2, 'C');
        $pdf->Cell($titleWidth - 10, 0, '____________________________________________________', 0, 2, 'C');
        $pdf->SetFontSize(6);
        $pdf->Cell($titleWidth, 6, '(Name)', 0, 2, 'C');

        $pdf->SetX($xInitial);
        $pdf->Cell($titleWidth, 0, $this->pdfText('For the month of '.$period->format('F').' '.$period->format('Y')), 0, 2, 'L');
        $pdf->Cell($titleWidth, 5, 'Official hours for arrival'.str_repeat("\t", 6).' Regular days ____________________', 0, 2, 'L');
        $pdf->Cell($titleWidth, 0, str_repeat("\t", 8).'and departure'.str_repeat("\t", 12).'Saturdays _______________________', 0, 2, 'L');

        $pdf->SetFillColor(232, 232, 232);
        $pdf->SetY($tableY);
        $pdf->SetX($xInitial);
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell($columns[0], 12, 'Day', 1, 0, 'C', true);
        $pdf->Cell($cellWidth * 2, 6, 'A.M', 1, 0, 'C', true);
        $pdf->Cell($cellWidth * 2, 6, 'P.M', 1, 0, 'C', true);
        $pdf->Cell($cellWidth * 2, 6, 'Undertime', 1, 0, 'C', true);
        $pdf->SetXY($xInitial + $columns[0], $bodyY - 6);
        foreach (['Arrival', 'Departure', 'Arrival', 'Departure', 'Hours', 'Minutes'] as $index => $label) {
            $pdf->Cell($columns[$index + 1], 6, $label, 1, 0, 'C', true);
        }

        $pdf->SetFont('Arial', '', 6);
        $y = $bodyY;
        foreach ($rows as $row) {
            $pdf->SetXY($xInitial, $y);
            $pdf->Cell($columns[0], $rowHeight, $row['day'], 1, 0, 'L');
            if (filled($row['label'])) {
                $pdf->Cell(array_sum(array_slice($columns, 1)), $rowHeight, $this->pdfText($row['label']), 1, 0, 'C');
            } else {
                $pdf->Cell($columns[1], $rowHeight, $row['timein_am'], 1, 0, 'C');
                $pdf->Cell($columns[2], $rowHeight, $row['timeout_am'], 1, 0, 'C');
                $pdf->Cell($columns[3], $rowHeight, $row['timein_pm'], 1, 0, 'C');
                $pdf->Cell($columns[4], $rowHeight, $row['timeout_pm'], 1, 0, 'C');
                $pdf->Cell($columns[5], $rowHeight, $row['undertime_hours'], 1, 0, 'C');
                $pdf->Cell($columns[6], $rowHeight, $row['undertime_minutes'], 1, 0, 'C');
            }
            $y += $rowHeight;
        }

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetXY($xInitial, $y);
        $pdf->Cell($cellWidth + 46, $rowHeight, 'Total', 1, 0, 'R');
        $pdf->Cell($columns[5], $rowHeight, '', 1, 0, 'C');
        $pdf->Cell($columns[6], $rowHeight, '', 1, 0, 'C');

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetY(212);
        $pdf->SetX($footerX);
        $pdf->Cell($footerX, 10, '', 0, 2, 'L');
        $pdf->Cell($footerX, 0, str_repeat("\t", 10).'I certify on my honor that the above is true and correct report of ', 0, 2, 'L');
        $pdf->Cell($footerX, 5, 'the hours work performed, record of which was made daily at the time ', 0, 2, '');
        $pdf->Cell($footerX, 0, 'of arrival and at time of departure from office.', 0, 2, 'L');
        $pdf->Cell($footerX, 10, '', 0, 2, 'L');
        $pdf->Cell($footerX + ($indent - 30), 5, '_______________________________________________________', 0, 2, 'C');
        $pdf->Cell($footerX, 1, str_repeat("\t", 40).'Signature of Employee', 0, 2, 'L');
        $pdf->Cell($footerX, 20, 'VERIFIED as to the prescribed office hours.', 0, 2, 'L');
        $pdf->SetFont('Arial', 'U', 8);
        $pdf->Cell($footerX, 10, $this->pdfText($data['verifierName']), 0, 2, 'L');
        $pdf->SetY(266);
        $pdf->SetX($footerX);
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell($footerX, 10, $this->pdfText($data['verifierDesignation']), 0, 2, 'L');
    }

    private function pdfText(?string $text): string
    {
        return mb_convert_encoding((string) $text, 'ISO-8859-1', 'UTF-8');
    }
}
