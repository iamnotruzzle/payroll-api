<?php

namespace App\Services\Schedule;

use App\Models\Hris\Department;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\SchedulePrintSetting;
use App\Models\Schedule\ScheduleSignatory;
use App\Models\Schedule\ShiftCode;
use Carbon\CarbonImmutable;
use FPDF;
use Illuminate\Support\Collection;

class SchedulePdfService
{
    /**
     * @return array{binary: string, filename: string, rows: int}
     */
    public function generate(MonthlySchedule $schedule, ?int $unitId = null): array
    {
        $schedule->loadMissing('assignments.employee.position', 'assignments.shiftCode', 'assignments.unit');

        $department = Department::find($schedule->department_id);
        $settings = SchedulePrintSetting::where('department_id', $schedule->department_id)->first();
        $signatories = ScheduleSignatory::where('department_id', $schedule->department_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('purpose')
            ->get();

        $startDate = CarbonImmutable::create($schedule->year, $schedule->month, 1);
        $days = collect(range(1, $startDate->daysInMonth))
            ->map(fn (int $day) => $startDate->setDay($day))
            ->values();

        $assignments = $schedule->assignments;
        if ($unitId !== null) {
            $assignments = $assignments->where('unit_id', $unitId);
        }

        $rows = $assignments
            ->groupBy('employee_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'employee_name' => $this->employeeName($first->employee),
                    'position' => $this->abbreviatePosition($first->employee?->position?->position_title),
                    'assignments' => $group
                        ->keyBy(fn ($assignment) => $assignment->schedule_date->toDateString())
                        ->map(fn ($assignment) => $assignment->shiftCode?->code ?: '-')
                        ->all(),
                ];
            })
            ->sortBy('employee_name')
            ->values();

        $legend = ShiftCode::where(function ($query) use ($schedule) {
            $query->whereNull('department_id')->orWhere('department_id', $schedule->department_id);
        })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $binary = $this->renderPdf(
            $schedule,
            $department?->department ?? 'Department',
            $settings,
            $signatories,
            $days,
            $rows,
            $legend,
        );

        $filename = sprintf(
            'schedule-%s-%04d-%02d%s.pdf',
            preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($department?->department ?? 'dept'))),
            $schedule->year,
            $schedule->month,
            $unitId ? '-unit-'.$unitId : ''
        );

        return [
            'binary' => $binary,
            'filename' => $filename,
            'rows' => $rows->count(),
        ];
    }

    private function renderPdf(
        MonthlySchedule $schedule,
        string $departmentName,
        ?SchedulePrintSetting $settings,
        Collection $signatories,
        Collection $days,
        Collection $rows,
        Collection $legend,
    ): string {
        $pdf = new FPDF('L', 'mm', 'Legal');
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 11);

        $org = $this->pdfText($settings?->organization_name ?: 'MARIANO MARCOS MEMORIAL HOSPITAL AND MEDICAL CENTER');
        $heading = $this->pdfText($settings?->schedule_heading ?: 'MONTHLY SCHEDULE OF DUTIES');
        $area = $this->pdfText(($settings?->area_label ?: 'AREA').': '.$departmentName);

        $pdf->Cell(0, 5, $org, 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, $this->pdfText(mb_strtoupper($departmentName)), 0, 1, 'C');
        $pdf->Cell(0, 5, $heading, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, $area.'    Period: '.sprintf('%04d-%02d', $schedule->year, $schedule->month).'    Status: '.ucfirst($schedule->status), 0, 1, 'C');
        $pdf->Ln(2);

        $nameW = 42;
        $posW = 18;
        $usable = 330 - $nameW - $posW;
        $dayW = max(5.2, $usable / max(1, $days->count()));

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetFillColor(226, 232, 240);
        $pdf->Cell($nameW, 8, 'Employee', 1, 0, 'C', true);
        $pdf->Cell($posW, 8, 'Pos', 1, 0, 'C', true);
        foreach ($days as $day) {
            $pdf->Cell($dayW, 8, $day->format('j').' '.$day->format('D'), 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 5.5);
        foreach ($rows as $row) {
            $pdf->Cell($nameW, 5, $this->pdfText(mb_substr($row['employee_name'], 0, 32)), 1, 0, 'L');
            $pdf->Cell($posW, 5, $this->pdfText(mb_substr((string) $row['position'], 0, 10)), 1, 0, 'C');
            foreach ($days as $day) {
                $code = $row['assignments'][$day->toDateString()] ?? '-';
                $pdf->Cell($dayW, 5, $this->pdfText((string) $code), 1, 0, 'C');
            }
            $pdf->Ln();
        }

        if ($rows->isEmpty()) {
            $pdf->Cell($nameW + $posW + ($dayW * $days->count()), 8, 'No assignments for this selection.', 1, 1, 'C');
        }

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(0, 4, 'Legend', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 6);
        $legendLine = $legend->map(fn (ShiftCode $shift) => $shift->code.': '.$shift->name)->implode('  |  ');
        $pdf->MultiCell(0, 3.5, $this->pdfText($legendLine));

        if ($signatories->isNotEmpty()) {
            $pdf->Ln(4);
            $colW = 80;
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $i = 0;
            foreach ($signatories as $signatory) {
                $col = $i % 3;
                $row = intdiv($i, 3);
                $pdf->SetXY($x + ($col * $colW), $y + ($row * 22));
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->Cell($colW - 4, 4, $this->pdfText($signatory->purpose), 0, 2, 'L');
                $pdf->Ln(8);
                $pdf->SetX($x + ($col * $colW));
                $pdf->SetFont('Arial', 'U', 8);
                $pdf->Cell($colW - 4, 4, $this->pdfText($signatory->person_name), 0, 2, 'L');
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetX($x + ($col * $colW));
                $pdf->Cell($colW - 4, 4, $this->pdfText((string) $signatory->designation), 0, 2, 'L');
                $i++;
            }
        }

        return $pdf->Output('S');
    }

    private function employeeName($employee): string
    {
        if (! $employee) {
            return 'Unknown employee';
        }

        return implode(' ', array_filter([
            $employee->lastname.',',
            $employee->firstname,
            $employee->middlename,
        ]));
    }

    private function abbreviatePosition(?string $position): string
    {
        if (! $position) {
            return '';
        }

        $stopWords = ['of', 'and', 'the', 'for'];

        return collect(preg_split('/\s+/', trim($position)))
            ->filter()
            ->reject(fn (string $word) => in_array(mb_strtolower($word), $stopWords, true))
            ->map(function (string $word) {
                $clean = preg_replace('/[^A-Za-z0-9]/', '', $word);
                if ($clean === '') {
                    return null;
                }
                if (preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/i', $clean)) {
                    return mb_strtoupper($clean);
                }

                return mb_strtoupper(mb_substr($clean, 0, 1));
            })
            ->filter()
            ->reduce(function (string $carry, string $part) {
                return preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/', $part)
                    ? trim($carry.' '.$part)
                    : $carry.$part;
            }, '');
    }

    private function pdfText(?string $text): string
    {
        return mb_convert_encoding((string) $text, 'ISO-8859-1', 'UTF-8');
    }
}
