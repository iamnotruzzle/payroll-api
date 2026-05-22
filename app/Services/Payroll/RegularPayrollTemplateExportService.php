<?php

namespace App\Services\Payroll;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RegularPayrollTemplateExportService
{
    private const TEMPLATE_SHEET = 'Regular';

    private const FIRST_DATA_ROW = 7;

    private const TEMPLATE_LAST_DATA_ROW = 39;

    private const SIGNATURE_ROW = 40;

    private const LAST_DATA_COLUMN = 'FM';

    private const LOAN_AMOUNT_COLUMNS = [
        'gsis_emergency' => 'BK',
        'gsis_computer' => 'BP',
        'gsis_conso' => 'BU',
        'gsis_policy' => 'BZ',
        'gsis_optional' => 'CE',
        'gsis_housing' => 'CJ',
        'gsis_gfal' => 'CO',
        'gsis_gsel' => 'CT',
        'gsis_mpl' => 'CY',
        'gsis_mpl_lite' => 'DD',
        'pagibig_mpl' => 'DJ',
        'pagibig_calamity' => 'DO',
        'pagibig_mp2' => 'DT',
        'dbp' => 'EF',
        'lbp' => 'EG',
        'ucpb' => 'EH',
        'mmmh_coop' => 'EI',
        'death_aid' => 'EK',
        'penalty_bac' => 'EL',
        'mmsu' => 'EM',
    ];

    public function export(Collection $rows, Collection $compensations, Collection $deductionPrograms, string $period): string
    {
        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('No payroll rows found.');
        }

        $spreadsheet = $this->loadTemplate();
        $sheet = $spreadsheet->getSheetByName(self::TEMPLATE_SHEET) ?? $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::TEMPLATE_SHEET);

        $this->prepareDataRows($sheet, $rows->count());
        $this->fillRows($sheet, $rows, $compensations, $deductionPrograms);
        $this->fillCertificationTotals($sheet, $rows->count());

        $spreadsheet->getProperties()
            ->setTitle('MMMHMC Regular Payroll '.$period)
            ->setSubject('General payroll final review output')
            ->setCreator(config('app.name', 'Payroll API'));

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mmmhmc_regular_payroll_'.$period.'_'.now()->format('Ymd_His').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function loadTemplate(): Spreadsheet
    {
        $path = (string) config('payroll.regular_template_path');

        if (! is_file($path)) {
            throw new InvalidArgumentException("Regular payroll template not found at {$path}.");
        }

        return IOFactory::load($path);
    }

    private function prepareDataRows(Worksheet $sheet, int $rowCount): void
    {
        $reservedRows = self::TEMPLATE_LAST_DATA_ROW - self::FIRST_DATA_ROW + 1;
        if ($rowCount > $reservedRows) {
            $sheet->insertNewRowBefore(self::SIGNATURE_ROW, $rowCount - $reservedRows);
        }

        $lastRow = self::FIRST_DATA_ROW + max($reservedRows, $rowCount) - 1;
        $blankRow = array_fill(0, Coordinate::columnIndexFromString(self::LAST_DATA_COLUMN), null);

        for ($row = self::FIRST_DATA_ROW; $row <= $lastRow; $row++) {
            if ($row !== self::FIRST_DATA_ROW) {
                $sheet->duplicateStyle(
                    $sheet->getStyle('A'.self::FIRST_DATA_ROW.':'.self::LAST_DATA_COLUMN.self::FIRST_DATA_ROW),
                    "A{$row}:".self::LAST_DATA_COLUMN.$row
                );
                $sheet->getRowDimension($row)->setRowHeight(
                    $sheet->getRowDimension(self::FIRST_DATA_ROW)->getRowHeight()
                );
            }

            $sheet->fromArray($blankRow, null, "A{$row}", true);
        }
    }

    private function fillRows(Worksheet $sheet, Collection $rows, Collection $compensations, Collection $deductionPrograms): void
    {
        foreach ($rows->values() as $index => $row) {
            $excelRow = self::FIRST_DATA_ROW + $index;
            $leave = $row['leave_deduction'] ?? [];
            $statutory = $row['statutory_deductions'] ?? [];
            $tax = $row['tax'] ?? [];
            $loans = $row['loan_deductions']['columns'] ?? [];
            $programs = collect($row['program_deductions']['items'] ?? []);

            $this->setCells($sheet, $excelRow, [
                'A' => $index + 1,
                'B' => $row['emp_id'] ?? null,
                'C' => $row['division'] ?? null,
                'D' => $row['department'] ?? null,
                'G' => $row['tin_no'] ?? null,
                'H' => $row['fund_type'] ?? null,
                'I' => $row['gsis_no'] ?? null,
                'J' => $row['phic_no'] ?? null,
                'K' => $row['hdmf_no'] ?? null,
                'L' => $row['employee_name'] ?? null,
                'M' => $row['position'] ?? null,
                'N' => $row['salary_grade'] ?? null,
                'O' => $row['step'] ?? null,
                'P' => implode(', ', $leave['periods'] ?? []),
                'Q' => $this->nonZero($row['deduction_days'] ?? 0),
                'T' => implode(', ', $leave['periods'] ?? []),
                'U' => $this->nonZero($leave['working_days'] ?? 0),
                'V' => $this->nonZero($leave['calendar_days'] ?? 0),
                'AB' => $this->money($row['basic_salary'] ?? 0),
                'AC' => $this->compensationAmount($row, ['subsistence']),
                'AD' => $this->compensationAmount($row, ['laundry']),
                'AE' => $this->compensationAmount($row, ['pera', 'personal economic relief']),
                'AG' => 0,
                'AH' => 0,
                'AI' => 0,
                'AJ' => 0,
                'AL' => "=ROUND(SUM(AB{$excelRow}:AE{$excelRow})+SUM(AG{$excelRow}:AJ{$excelRow}),2)",
                'AN' => $this->money($statutory['life_retirement'] ?? 0),
                'AQ' => $this->money($statutory['phic'] ?? 0),
                'AS' => $this->money($statutory['mandatory_pagibig'] ?? 0),
                'AU' => $this->programAmount($programs, ['ea', 'employees association', 'monthly dues']),
                'AW' => 0,
                'AX' => 0,
                'AY' => 0,
                'AZ' => 0,
                'BA' => 0,
                'BB' => 0,
                'BC' => 0,
                'BD' => 0,
                'BE' => "=ROUND(SUM(AN{$excelRow}:AU{$excelRow})+SUM(AW{$excelRow}:BD{$excelRow}),2)",
                'EK' => $this->programAmount($programs, ['death aid']),
                'EL' => $this->programAmount($programs, ['penalty bac', 'bac']),
                'EM' => $this->programAmount($programs, ['mmsu']),
                'EO' => "=ROUND(SUM(BK{$excelRow},BP{$excelRow},BU{$excelRow},BZ{$excelRow},CE{$excelRow},CJ{$excelRow},CO{$excelRow},CT{$excelRow},CY{$excelRow},DD{$excelRow})+SUM(DJ{$excelRow}+DO{$excelRow}+DT{$excelRow}+DY{$excelRow}+ED{$excelRow})+SUM(EF{$excelRow},EG{$excelRow},EH{$excelRow},EI{$excelRow})+SUM(EK{$excelRow},EL{$excelRow},EM{$excelRow}),2)",
                'ET' => $this->money($tax['monthly_mandatory_deductions'] ?? 0),
                'EU' => $this->money($tax['monthly_net_income'] ?? 0),
                'EV' => $this->money($tax['monthly_tax_due'] ?? 0),
                'EX' => $this->money($row['net_after_loan_deductions'] ?? 0),
                'EY' => $this->money($row['fifteenth'] ?? 0),
                'EZ' => $this->money($row['thirtieth'] ?? 0),
                'FE' => $this->money($row['basic_salary'] ?? 0),
                'FF' => $this->hazardPercent($row),
                'FG' => $this->compensationAmount($row, ['hazard']),
                'FI' => $this->compensationAmount($row, ['hazard']),
                'FK' => $this->money($tax['monthly_tax_due'] ?? 0),
                'FM' => $this->compensationAmount($row, ['hazard']),
            ]);

            foreach (self::LOAN_AMOUNT_COLUMNS as $key => $column) {
                $amount = $loans[$key] ?? 0;
                if ((float) $amount !== 0.0) {
                    $sheet->setCellValue("{$column}{$excelRow}", round((float) $amount, 2));
                }
            }
        }
    }

    private function fillCertificationTotals(Worksheet $sheet, int $rowCount): void
    {
        $lastPayrollRow = self::FIRST_DATA_ROW + $rowCount - 1;

        $sheet->setCellValue(
            'FA17',
            '=TEXT(SUM(EX'.self::FIRST_DATA_ROW.":EX{$lastPayrollRow}),\"P ###,###,###.##;;@\")"
        );
    }

    private function setCells(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $sheet->setCellValue("{$column}{$row}", $value);
        }
    }

    private function compensationAmount(array $row, array $needles): float
    {
        foreach (($row['compensations'] ?? []) as $compensation) {
            $name = strtolower((string) ($compensation['name'] ?? ''));

            foreach ($needles as $needle) {
                if (str_contains($name, strtolower($needle))) {
                    return $this->money($compensation['amount'] ?? 0);
                }
            }
        }

        return 0.0;
    }

    private function programAmount(Collection $programs, array $needles): float
    {
        return round($programs
            ->filter(function (array $program) use ($needles) {
                $name = strtolower((string) ($program['name'] ?? ''));

                foreach ($needles as $needle) {
                    if (str_contains($name, strtolower($needle))) {
                        return true;
                    }
                }

                return false;
            })
            ->sum(fn (array $program) => (float) ($program['amount'] ?? 0)), 2);
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?: 0), 2);
    }

    private function nonZero(mixed $value): ?float
    {
        $number = round((float) ($value ?: 0), 3);

        return $number === 0.0 ? null : $number;
    }

    private function hazardPercent(array $row): ?float
    {
        $basicSalary = (float) ($row['basic_salary'] ?? 0);
        if ($basicSalary <= 0) {
            return null;
        }

        $hazard = $this->compensationAmount($row, ['hazard']);

        return $hazard > 0 ? round($hazard / $basicSalary, 4) : null;
    }
}
