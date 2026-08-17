<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollBatchRecord;
use App\Services\Hris\EmploymentAsOfService;
use FPDF;
use Illuminate\Http\Response;

class PayslipPrintService
{
    public function __construct(private readonly EmploymentAsOfService $employmentAsOf) {}

    /** @return array<string, mixed> */
    public function payload(PayrollBatchRecord $record, string $backUrl): array
    {
        $record->loadMissing('batch');
        $period = $record->batch?->payroll_period;
        $payrollDate = $this->employmentAsOf->payrollDate($period, $record->batch?->snapshot_created_at);
        $snapshot = $record->snapshot_json ?? [];

        $records = PayrollBatchRecord::query()
            ->with('batch')
            ->where('emp_id', $record->emp_id)
            ->whereHas('batch', fn ($query) => $query->where('payroll_period', $period))
            ->get()
            ->sortBy(fn (PayrollBatchRecord $item) => $this->typeOrder($item))
            ->values();

        return [
            'record' => $record,
            'batch' => $record->batch,
            'records' => $records,
            'employee' => $this->employmentAsOf->resolve((string) $record->emp_id, $payrollDate, $snapshot['employee'] ?? []),
            'payrollDate' => $payrollDate,
            'backUrl' => $backUrl,
        ];
    }

    public function binary(PayrollBatchRecord $record): string
    {
        $payload = $this->payload($record, '');
        $pdf = new FPDF('P', 'mm', 'Letter');
        $pdf->SetTitle('Pay Slip - '.$payload['payrollDate']->format('Y-m'), true);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $this->draw($pdf, $payload);

        return $pdf->Output('S');
    }

    public function response(PayrollBatchRecord $record): Response
    {
        return response($this->binary($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="payslip-'.$record->emp_id.'-'.$record->batch?->payroll_period.'.pdf"',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function draw(FPDF $pdf, array $payload): void
    {
        $x = 12.1;
        $width = 191.7;
        $employee = $payload['employee'];
        $records = $payload['records'];
        $money = static fn ($value) => number_format((float) ($value ?? 0), 2);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.22);
        $pdf->SetFont('Times', 'B', 11);
        $pdf->SetXY($x, 11.8);
        $pdf->Cell($width, 4.6, $this->pdfText('Mariano Marcos Memorial Hospital and Medical Center'), 0, 0, 'C');
        $pdf->Line($x, 16.3, $x + $width, 16.3);

        $this->headerPair($pdf, 16.6, 'Name:', $employee['employee_name'] ?? $payload['record']->emp_id, 'Position:', $employee['position'] ?? '');
        $this->headerPair($pdf, 20.8, 'Department:', $employee['department'] ?? '', 'Division:', $employee['division'] ?? '');

        $pdf->Rect($x, 27.7, $width, 8.7);
        $pdf->SetFont('Times', '', 11);
        $pdf->SetXY($x, 28.0);
        $pdf->Cell($width, 4.0, 'PAY SLIP', 0, 0, 'C');
        $period = $payload['payrollDate'];
        $periodLabel = $period->format('F').' 1-'.$period->day.', '.$period->year;
        $pdf->SetXY($x, 32.0);
        $pdf->Cell($width, 3.5, $this->pdfText($periodLabel), 0, 0, 'C');

        $y = 39.7;
        $hasRegular = $records->contains(fn (PayrollBatchRecord $item) => $this->isRegular($item));
        foreach ($records as $index => $record) {
            $regular = $this->isRegular($record) || ($index === 0 && ! $hasRegular);
            $section = $this->section($record);
            $pdf->Line($x, $y, $x + $width, $y);
            $y += 0.1;

            foreach ($section['earnings'] as $earningIndex => $item) {
                $label = ! $regular && $earningIndex === 0 ? $this->typeLabel($record) : $item['name'];
                $this->itemRow($pdf, $y, $item['remarks'], $label, $money($item['amount']));
                $y += 4.2;
            }

            if ($regular) {
                $this->bar($pdf, $y, 'GROSS PAY', 'Php '.$money($section['gross']), false);
                $y += 4.2;
            }

            $pdf->SetFont('Times', 'I', 8);
            $pdf->SetXY($x, $y + 0.2);
            $pdf->Cell($width, 3.6, 'Less: DEDUCTIONS', 0, 0, 'L');
            $y += 4.2;

            foreach ($section['deductions'] as $item) {
                $this->itemRow($pdf, $y, $item['remarks'], $item['name'], $money($item['amount']));
                $y += 4.2;
            }

            if ($regular) {
                $this->bar($pdf, $y, 'TOTAL DEDUCTIONS', 'Php ('.$money($section['total_deductions']).')', false);
                $y += 4.8;
                $pdf->SetFillColor(170, 170, 170);
                $pdf->Rect($x, $y, $width, 8.4, 'F');
                $pdf->SetFont('Times', '', 10.5);
                $pdf->SetXY($x + 1.6, $y + 2.0);
                $pdf->Cell(82.0, 4.0, 'NET PAY', 0, 0, 'L');
                $pdf->SetFont('Times', 'I', 10.5);
                $pdf->SetXY($x + 86.0, $y + 0.1);
                $pdf->Cell(20, 4.0, '15th', 0, 0, 'C');
                $pdf->SetXY($x + 86.0, $y + 4.0);
                $pdf->Cell(20, 4.0, '30th', 0, 0, 'C');
                $pdf->SetFont('Times', '', 10.5);
                $pdf->SetXY($x + 106.0, $y + 0.1);
                $pdf->Cell(30, 4.0, $money($section['fifteenth']), 0, 0, 'C');
                $pdf->SetXY($x + 106.0, $y + 4.0);
                $pdf->Cell(30, 4.0, $money($section['thirtieth']), 0, 0, 'C');
                $pdf->SetFont('Times', 'BU', 10.5);
                $pdf->SetXY($x + 142.0, $y + 2.0);
                $pdf->Cell(47.0, 4.0, 'Php '.$money($section['net']), 0, 0, 'R');
                $y += 11.8;
            } else {
                $this->bar($pdf, $y, 'NET PAY', 'Php '.$money($section['net']), true);
                $y += 7.4;
            }
        }

        $pdf->Rect($x, $y, $width, 4.8);
        $pdf->SetFont('Times', 'I', 8);
        $pdf->SetXY($x + 0.5, $y + 0.6);
        $pdf->Cell($width / 2, 3.4, '*NOTE: Net take home pay must not be lower than Php 5,000.00', 0, 0, 'L');
        $pdf->SetXY($x + ($width / 2), $y + 0.6);
        $pdf->Cell(($width / 2) - 0.5, 3.4, '*This is auto-generated, for issues and concerns please contact HRMO', 0, 0, 'R');
    }

    private function headerPair(FPDF $pdf, float $y, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue): void
    {
        $pdf->SetFont('Times', '', 10.5);
        foreach ([[11.1, 29, $leftLabel], [40.2, 65, $leftValue], [106.2, 29, $rightLabel], [135.3, 68.5, $rightValue]] as [$x, $width, $text]) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 3.8, $this->pdfText($text), 0, 0, 'L');
        }
    }

    private function itemRow(FPDF $pdf, float $y, mixed $remarks, string $label, string $amount): void
    {
        $pdf->SetFont('Times', '', 10.5);
        $pdf->SetXY(11.1, $y);
        $pdf->Cell(12, 3.8, $this->pdfText((string) ($remarks ?? '')), 0, 0, 'L');
        $pdf->SetXY(30.4, $y);
        $pdf->Cell(113.5, 3.8, $this->pdfText($label), 0, 0, 'L');
        $pdf->SetXY(143.9, $y);
        $pdf->Cell(20.5, 3.8, $amount, 0, 0, 'R');
    }

    private function bar(FPDF $pdf, float $y, string $label, string $amount, bool $fullWidth): void
    {
        $pdf->SetFillColor(170, 170, 170);
        $barWidth = $fullWidth ? 191.7 : 153.3;
        $pdf->Rect(12.1, $y, $barWidth, 4.2, 'F');
        $pdf->SetFont('Times', '', 10.5);
        $pdf->SetXY(12.1, $y + 0.2);
        $pdf->Cell(151.8, 3.8, $label, 0, 0, 'R');
        $pdf->SetFont('Times', $fullWidth ? 'BU' : 'U', 10.5);
        $pdf->SetXY(165.4, $y + 0.2);
        $pdf->Cell(38.3, 3.8, $amount, 0, 0, 'R');
    }

    /** @return array<string, mixed> */
    private function section(PayrollBatchRecord $record): array
    {
        $snapshot = $record->snapshot_json ?? [];
        $earningsData = $snapshot['earnings'] ?? [];
        $earnings = collect();
        if ((float) ($earningsData['basic_salary'] ?? 0) !== 0.0) {
            $earnings->push(['name' => 'Salary', 'amount' => $earningsData['basic_salary'], 'remarks' => null]);
        }
        foreach (($earningsData['compensations'] ?? []) as $item) {
            $earnings->push(['name' => $item['name'] ?? 'Compensation', 'amount' => $item['amount'] ?? 0, 'remarks' => $item['remarks'] ?? null]);
        }
        if ($earnings->isEmpty() && (float) $record->gross !== 0.0) {
            $earnings->push(['name' => $this->typeLabel($record), 'amount' => $record->gross, 'remarks' => null]);
        }

        $deductions = collect();
        foreach (($snapshot['statutory_deductions'] ?? []) as $key => $amount) {
            if (is_numeric($amount) && (float) $amount !== 0.0) {
                $deductions->push(['name' => ucwords(str_replace('_', ' ', (string) $key)), 'amount' => $amount, 'remarks' => null]);
            }
        }
        $tax = $snapshot['tax']['withholding_tax'] ?? $snapshot['tax']['monthly_tax_due'] ?? 0;
        if ((float) $tax !== 0.0) {
            $deductions->push(['name' => 'Tax', 'amount' => $tax, 'remarks' => null]);
        }
        foreach ([$snapshot['program_deductions'] ?? [], $snapshot['additional_premiums'] ?? []] as $collection) {
            foreach (($collection['items'] ?? $collection) as $key => $item) {
                $amount = is_array($item) ? ($item['amount'] ?? $item['amount_due'] ?? 0) : $item;
                if (is_numeric($amount) && (float) $amount !== 0.0) {
                    $deductions->push([
                        'name' => is_array($item) ? ($item['name'] ?? $item['loan_type'] ?? 'Deduction') : ucwords(str_replace('_', ' ', (string) $key)),
                        'amount' => $amount,
                        'remarks' => is_array($item) ? ($item['remarks'] ?? $item['loan_account_no'] ?? null) : null,
                    ]);
                }
            }
        }
        foreach (($snapshot['loan_deductions']['columns'] ?? $snapshot['loan_deductions'] ?? []) as $key => $amount) {
            if (is_numeric($amount) && (float) $amount !== 0.0) {
                $deductions->push(['name' => ucwords(str_replace('_', ' ', (string) $key)), 'amount' => $amount, 'remarks' => null]);
            }
        }

        $totals = $snapshot['totals'] ?? [];
        $gross = (float) ($totals['gross'] ?? $earningsData['gross'] ?? $record->gross ?? $earnings->sum('amount'));
        $net = (float) ($totals['net_after_loan_deductions'] ?? $record->net ?? 0);

        return [
            'earnings' => $earnings->values(),
            'deductions' => $deductions->values(),
            'gross' => $gross,
            'net' => $net,
            'total_deductions' => (float) ($totals['total_deductions'] ?? ($gross - $net)),
            'fifteenth' => (float) ($totals['fifteenth'] ?? $record->fifteenth ?? $net / 2),
            'thirtieth' => (float) ($totals['thirtieth'] ?? $record->thirtieth ?? $net / 2),
        ];
    }

    private function isRegular(PayrollBatchRecord $record): bool
    {
        $type = strtolower(($record->batch?->payroll_type_code ?? '').' '.($record->batch?->payroll_type ?? ''));
        return str_contains($type, 'regular') || str_contains($type, 'general');
    }

    private function typeLabel(PayrollBatchRecord $record): string
    {
        return ucfirst(strtolower(str_replace(['_', '-'], ' ', trim((string) ($record->batch?->payroll_type ?? $record->batch?->payroll_type_code ?? 'Payroll')))));
    }

    private function pdfText(?string $text): string
    {
        return mb_convert_encoding((string) $text, 'ISO-8859-1', 'UTF-8');
    }

    private function typeOrder(PayrollBatchRecord $record): int
    {
        $type = strtolower(trim(($record->batch?->payroll_type_code ?? '').' '.($record->batch?->payroll_type ?? '')));

        return match (true) {
            str_contains($type, 'regular'), str_contains($type, 'general') => 10,
            str_contains($type, 'hazard') => 20,
            str_contains($type, 'medicare') => 30,
            default => 40,
        };
    }
}
