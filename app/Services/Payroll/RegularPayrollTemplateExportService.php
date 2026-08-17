<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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

    private const HEADER_ROWS = [1, 2, 3, 4, 5, 6];

    private const RESERVED_DATA_COLUMNS = [
        'A', 'B', 'C', 'D', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'T', 'U', 'V', 'AB', 'AC', 'AD', 'AE', 'AG', 'AH', 'AI',
        'AJ', 'AK', 'AL', 'AN', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW',
        'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'BE', 'EK', 'EL', 'EM',
        'EN', 'EO', 'ET', 'EU', 'EV', 'EX', 'EY', 'EZ', 'FE', 'FF', 'FG', 'FI',
        'FK', 'FM',
    ];

    private const LOAN_AMOUNT_COLUMNS = [
        'gsis_emergency' => 'BK',
        'gsis_computer' => 'BP',
        'gsis_conso' => 'BU',
        'gsis_policy' => 'BZ',
        'gsis_uoli' => 'CE',
        'gsis_optional' => 'CJ',
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
        'coco' => 'EI',
        'mmmh_coop' => 'EI',
        'other_loans' => 'EK',
        'death_aid' => 'EL',
        'ea_monthly_dues' => 'EM',
        'penalty_bac' => 'EL',
        'mmsu' => 'EM',
    ];

    private const ADDITIONAL_PREMIUM_COLUMN = 'EN';

    private const SNAPSHOT_LOAN_AMOUNT_COLUMNS = [
        'gsis_emergency' => 'BY',
        'gsis_computer' => 'CC',
        'gsis_conso' => 'CG',
        'gsis_policy' => 'CK',
        'gsis_optional' => 'CO',
        'gsis_uoli' => 'CO',
        'gsis_housing' => 'CS',
        'gsis_gfal' => 'CW',
        'gsis_gsel' => 'DA',
        'gsis_gbel' => 'DE',
        'gsis_mpl' => 'DI',
        'gsis_mpl_lite' => 'DM',
        'pagibig_mpl' => 'DR',
        'pagibig_calamity' => 'DV',
        'pagibig_mp2' => 'DZ',
        'pagibig_mp2_a' => 'DZ',
        'pagibig_mp2_b' => 'ED',
        'pagibig_mp2_c' => 'EH',
        'pagibig_mp2_d' => 'EL',
        'dbp' => 'EQ',
        'lbp' => 'EU',
        'ucpb' => 'EY',
        'ucpb_w1' => 'EY',
        'ucpb_w2' => 'FC',
        'coco' => 'FH',
        'mmmh_coop' => 'FH',
        'other_loans' => 'FG',
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
        $this->applyCurrentReviewHeaders($sheet);
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

    public function exportSnapshot(PayrollBatch $batch): string
    {
        $records = $batch->records()->orderBy('id')->get();
        if ($records->isEmpty()) {
            throw new InvalidArgumentException('This payroll snapshot has no employee records to export.');
        }

        $employees = collect();
        if (Schema::connection('hris')->hasTable('tbl_employee')) {
            $employees = Employee::query()
                ->with(['department:department_id,department,division_id', 'department.division:division_id,division'])
                ->whereIn('emp_id', $records->pluck('emp_id')->filter()->all())
                ->get(['emp_id', 'department_id', 'tin_no', 'gsis_no', 'phic_no', 'pagibig_no'])
                ->keyBy('emp_id');
        }

        $rows = $records->map(function ($record) use ($employees) {
            $snapshot = $record->snapshot_json ?? [];
            $employeeSnapshot = $snapshot['employee'] ?? [];
            $payBasis = $snapshot['pay_basis'] ?? [];
            $earnings = $snapshot['earnings'] ?? [];
            $totals = $snapshot['totals'] ?? [];
            $employee = $employees->get($record->emp_id);

            return [
                'emp_id' => $employeeSnapshot['emp_id'] ?? $record->emp_id,
                'division' => $employee?->department?->division?->division,
                'department' => $employeeSnapshot['department'] ?? $employee?->department?->department,
                'tin_no' => $employee?->tin_no,
                'fund_type' => null,
                'gsis_no' => $employee?->gsis_no,
                'phic_no' => $employee?->phic_no,
                'hdmf_no' => $employee?->pagibig_no,
                'employee_name' => $employeeSnapshot['employee_name'] ?? $record->emp_id,
                'position' => $employeeSnapshot['position'] ?? null,
                'salary_grade' => $employeeSnapshot['salary_grade'] ?? $payBasis['salary_grade'] ?? null,
                'step' => $employeeSnapshot['step'] ?? $payBasis['step'] ?? null,
                'deduction_days' => $payBasis['deduction_days'] ?? 0,
                'leave_deduction' => $payBasis['leave_deduction'] ?? [],
                'basic_salary' => $earnings['basic_salary'] ?? 0,
                'compensations' => $earnings['compensations'] ?? [],
                'compensation_adjustments' => $earnings['adjustments'] ?? [],
                'statutory_deductions' => $snapshot['statutory_deductions'] ?? [],
                'statutory_government_shares' => $snapshot['statutory_government_shares'] ?? [],
                'mandatory_deduction_adjustments' => $snapshot['mandatory_deduction_adjustments'] ?? [],
                'mandatory_program_deductions' => $snapshot['mandatory_program_deductions'] ?? ['items' => [], 'total' => 0],
                'tax' => $snapshot['tax'] ?? [],
                'loan_deductions' => $snapshot['loan_deductions'] ?? ['columns' => [], 'total' => 0],
                'program_deductions' => $snapshot['program_deductions'] ?? ['items' => [], 'total' => 0],
                'additional_premiums' => $snapshot['additional_premiums'] ?? ['items' => [], 'total' => 0],
                'gross' => $earnings['gross'] ?? $totals['gross'] ?? $record->gross,
                'net_compensation' => $earnings['net_compensation'] ?? $totals['net_compensation'] ?? 0,
                'total_mandatory_deductions' => $totals['total_mandatory_deductions'] ?? 0,
                'net_before_other_deductions' => $totals['net_before_other_deductions'] ?? 0,
                'total_other_deductions' => $totals['total_other_deductions'] ?? 0,
                'net_after_tax' => $totals['net_after_tax'] ?? 0,
                'net_after_program_deductions' => $totals['net_after_program_deductions'] ?? 0,
                'net_after_additional_premiums' => $totals['net_after_additional_premiums'] ?? 0,
                'net_after_loan_deductions' => $totals['net_after_loan_deductions'] ?? $record->net,
                'fifteenth' => $totals['fifteenth'] ?? $record->fifteenth,
                'thirtieth' => $totals['thirtieth'] ?? $record->thirtieth,
            ];
        });

        return $this->exportWorkbookAlignedSnapshot($rows, $batch->payroll_period);
    }

    private function exportWorkbookAlignedSnapshot(Collection $rows, string $period): string
    {
        $layout = $this->snapshotLayout();
        $path = $layout['template_path'];
        if (! is_file($path)) {
            throw new InvalidArgumentException("Payroll snapshot template not found at {$path}.");
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName($layout['sheet']) ?? $spreadsheet->getActiveSheet();
        $sheet->setTitle($layout['sheet']);
        $this->prepareSnapshotRows($sheet, $rows->count(), $layout['first_data_row']);
        $this->fillSnapshotRows($sheet, $rows, $layout['first_data_row']);

        $lastRow = $layout['first_data_row'] + $rows->count() - 1;
        if ($layout['print_area_last_column'] !== null) {
            $sheet->getPageSetup()->setPrintArea("A1:{$layout['print_area_last_column']}{$lastRow}");
        }
        $sheet->setSelectedCell('A1');
        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($sheet));
        $spreadsheet->getProperties()
            ->setTitle('MMMHMC Payroll Snapshot '.$period)
            ->setSubject('Workbook-aligned finalized payroll snapshot')
            ->setCreator(config('app.name', 'Payroll API'));

        $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'MMMHMC_PAYROLL_SNAPSHOT_'.$period.'_'.now()->format('Ymd_His').'.xlsx';
        (new Xlsx($spreadsheet))->save($target);

        return $target;
    }

    private function snapshotLayout(): array
    {
        return [
            'template_path' => (string) config('payroll.snapshot_template_path'),
            'sheet' => 'allied',
            'first_data_row' => 5,
            'print_area_last_column' => null,
        ];
    }

    private function prepareSnapshotRows(Worksheet $sheet, int $rowCount, int $firstDataRow): void
    {
        $height = $sheet->getRowDimension($firstDataRow)->getRowHeight();
        $lastDataRow = $firstDataRow + $rowCount - 1;

        foreach ($this->snapshotStyledColumns() as $column) {
            $style = $sheet->getStyle("{$column}{$firstDataRow}")->exportArray();
            $sheet->getStyle("{$column}{$firstDataRow}:{$column}{$lastDataRow}")->applyFromArray($style);
        }

        for ($offset = 0; $offset < $rowCount; $offset++) {
            $row = $firstDataRow + $offset;
            $sheet->getRowDimension($row)->setRowHeight($height);
            $sheet->getRowDimension($row)->setVisible(true);
        }
    }

    private function snapshotStyledColumns(): array
    {
        $mapped = preg_split('/\s+/', trim(<<<'COLUMNS'
            A B C D G H I J K P Q R S T U V W X Y Z AE AF AG AI AJ AL AM AO AP AR AS
            AU AV AW AX AY AZ BB BC BD BE BF BG BH BI BJ BL BM BN BO BP BQ BR BS BT
            FJ FK FL FM FO GB GC GD GE GF GG GH GM GN GO GP GQ GS GT GU
            IE IG IH IJ IK IL IM IP IR IS IU IV IW IX IY
            JD JE JF JG JH JI JJ JL JM JN JO JP JQ JR JS JT JU JV JW JX JY JZ KA KB KC KD KE KG
            COLUMNS));

        return array_values(array_unique(array_merge($mapped, array_values(self::SNAPSHOT_LOAN_AMOUNT_COLUMNS))));
    }

    private function fillSnapshotRows(Worksheet $sheet, Collection $rows, int $firstDataRow): void
    {
        foreach ($rows->values() as $index => $row) {
            $excelRow = $firstDataRow + $index;
            $leave = $row['leave_deduction'] ?? [];
            $statutory = $row['statutory_deductions'] ?? [];
            $shares = $row['statutory_government_shares'] ?? [];
            $adjustments = $row['compensation_adjustments'] ?? [];
            $mandatoryAdjustments = $row['mandatory_deduction_adjustments']['items'] ?? [];
            $mandatoryPrograms = collect($row['mandatory_program_deductions']['items'] ?? []);
            $programs = collect($row['program_deductions']['items'] ?? []);
            $tax = $row['tax'] ?? [];
            $basicBreakdown = $tax['tax_on_basic_breakdown'] ?? [];
            $hazardBreakdown = $tax['tax_on_hazard_breakdown'] ?? [];
            $subsistence = $this->compensationAmount($row, ['subsistence']);
            $laundry = $this->compensationAmount($row, ['laundry']);
            $pera = $this->compensationAmount($row, ['pera', 'personal economic relief']);
            $hazard = $this->hazardAmount($row);
            $loans = $row['loan_deductions']['columns'] ?? [];

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
                'P' => $row['employee_name'] ?? null,
                'Q' => $row['position'] ?? null,
                'R' => $row['salary_grade'] ?? null,
                'S' => $row['step'] ?? null,
                'T' => implode(', ', $leave['periods'] ?? []),
                'U' => $this->nonZero($leave['leave_without_pay_days'] ?? $leave['lwop_days'] ?? 0),
                'V' => $this->nonZero($leave['unauthorized_days'] ?? 0),
                'W' => $this->nonZero($leave['tev_days'] ?? 0),
                'X' => implode(', ', $leave['periods'] ?? []),
                'Y' => $this->nonZero($leave['working_days'] ?? 0),
                'Z' => $this->nonZero($leave['calendar_days'] ?? 0),
                'AE' => $this->nonZero($leave['paid_days'] ?? 0),
                'AF' => $this->nonZero($leave['gsis_days'] ?? 0),
                'AG' => $this->money($tax['salary'] ?? $row['basic_salary'] ?? 0),
                'AI' => $this->money($row['basic_salary'] ?? 0),
                'AJ' => $this->money($row['basic_salary'] ?? 0),
                'AL' => $subsistence,
                'AM' => $subsistence,
                'AO' => $laundry,
                'AP' => $laundry,
                'AR' => $pera,
                'AS' => $pera,
                'AU' => $this->money($adjustments['basic_salary'] ?? 0),
                'AV' => $this->money($adjustments['subsistence'] ?? 0),
                'AW' => $this->money($adjustments['laundry'] ?? 0),
                'AX' => $this->money($adjustments['pera'] ?? 0),
                'AY' => $adjustments['remarks'] ?? null,
                'AZ' => $this->money($row['net_compensation'] ?? $row['gross'] ?? 0),
                'BB' => $this->money($statutory['life_retirement'] ?? 0),
                'BC' => $this->money($shares['government_life_retirement'] ?? 0),
                'BD' => $this->money($shares['ec'] ?? 0),
                'BE' => $this->money($statutory['phic'] ?? 0),
                'BF' => $this->money($shares['government_phic'] ?? 0),
                'BG' => $this->money($statutory['mandatory_pagibig'] ?? 0),
                'BH' => $this->programAmount($mandatoryPrograms, ['hdmf ps 2 ms', 'hdmf (ps) 2 ms']) ?: $this->money($statutory['hdmf_ps_2_ms'] ?? 0),
                'BI' => $this->money($shares['government_pagibig'] ?? 0),
                'BJ' => $this->programAmount($mandatoryPrograms, ['ea deduction']) ?: $this->money($statutory['ea_deduction'] ?? 0),
                'BL' => $this->money($mandatoryAdjustments['life_retirement'] ?? 0),
                'BM' => $this->money($mandatoryAdjustments['government_life_retirement'] ?? 0),
                'BN' => $this->money($mandatoryAdjustments['ec'] ?? 0),
                'BO' => $this->money($mandatoryAdjustments['phic'] ?? 0),
                'BP' => $this->money($mandatoryAdjustments['government_phic'] ?? 0),
                'BQ' => $this->money($mandatoryAdjustments['mandatory_pagibig'] ?? 0),
                'BR' => $this->money($mandatoryAdjustments['government_pagibig'] ?? 0),
                'BS' => $this->money($mandatoryAdjustments['ea_deduction'] ?? 0),
                'BT' => $this->money($row['total_mandatory_deductions'] ?? 0),
                'FJ' => $this->programAmount($programs, ['death aid']),
                'FK' => $this->programAmount($programs, ['penalty bac', 'bac']),
                'FL' => $this->programAmount($programs, ['longevity']),
                'FM' => $this->programAmount($programs, ['mmsu']),
                'FO' => $this->money($row['total_other_deductions'] ?? 0),
                'GB' => max(0, $this->money(($row['net_compensation'] ?? 0) - ($tax['monthly_taxable_income'] ?? 0))),
                'GC' => $this->money($tax['monthly_taxable_income'] ?? 0),
                'GD' => $this->money($tax['withholding_tax_gross'] ?? $tax['tax_on_basic'] ?? 0),
                'GE' => $this->money($tax['withholding_tax_adjustment'] ?? $tax['tax_adjustment'] ?? 0),
                'GF' => $this->money($row['net_after_loan_deductions'] ?? 0),
                'GG' => $this->money($row['fifteenth'] ?? 0),
                'GH' => $this->money($row['thirtieth'] ?? 0),
                'GM' => $this->money($tax['salary'] ?? $row['basic_salary'] ?? 0),
                'GN' => $this->hazardPercent($row),
                'GO' => $hazard,
                'GP' => $this->money($tax['hazard_adjustment'] ?? 0),
                'GQ' => $hazard,
                'GS' => $this->money($tax['current_hazard_tax_due'] ?? $tax['tax_on_hazard'] ?? 0),
                'GT' => $this->money($tax['hazard_tax_adjustment'] ?? 0),
                'GU' => $this->money($hazard - ($tax['tax_on_hazard'] ?? 0)),
                'IE' => $this->money($tax['monthly_taxable_income'] ?? 0),
                'IG' => $this->money($basicBreakdown['compensation_level'] ?? $basicBreakdown['base'] ?? 0),
                'IH' => $this->money($basicBreakdown['excess'] ?? 0),
                'IJ' => $this->money($basicBreakdown['fixed_tax'] ?? $basicBreakdown['base_tax'] ?? 0),
                'IK' => $this->money($basicBreakdown['excess_tax'] ?? 0),
                'IL' => $this->money($tax['tax_on_basic'] ?? 0),
                'IM' => $this->money($tax['tax_adjustment'] ?? 0),
                'IP' => $this->money($tax['monthly_taxable_income_with_hazard'] ?? 0),
                'IR' => $this->money($hazardBreakdown['compensation_level'] ?? $hazardBreakdown['base'] ?? 0),
                'IS' => $this->money($hazardBreakdown['excess'] ?? 0),
                'IU' => $this->money($hazardBreakdown['fixed_tax'] ?? $hazardBreakdown['base_tax'] ?? 0),
                'IV' => $this->money($hazardBreakdown['excess_tax'] ?? 0),
                'IW' => $this->money(($tax['tax_on_basic'] ?? 0) + ($tax['tax_on_hazard'] ?? 0)),
                'IX' => $this->money($tax['tax_on_basic'] ?? 0),
                'IY' => $this->money($tax['tax_on_hazard'] ?? 0),
                'JD' => $tax['future_months'] ?? null,
                'JE' => $tax['annualization_leave_without_pay_months'] ?? null,
                'JF' => $tax['hazard_subsistence_deduction_months'] ?? null,
                'JG' => $this->money($tax['previous_basic'] ?? 0), 'JH' => $this->money($tax['current_basic'] ?? 0),
                'JI' => $this->money($tax['future_basic'] ?? 0), 'JJ' => $this->money($tax['total_basic'] ?? 0),
                'JL' => $this->money($tax['previous_hazard'] ?? 0), 'JM' => $this->money($tax['current_hazard'] ?? 0),
                'JN' => $this->money($tax['future_hazard'] ?? 0), 'JO' => $this->money($tax['total_hazard'] ?? 0),
                'JP' => $this->money($tax['previous_subsistence'] ?? 0), 'JQ' => $this->money($tax['current_subsistence'] ?? 0),
                'JR' => $this->money($tax['future_subsistence'] ?? 0), 'JS' => $this->money($tax['total_subsistence'] ?? 0),
                'JT' => $this->money($tax['previous_mandatory_deductions'] ?? 0), 'JU' => $this->money($tax['current_mandatory_deductions'] ?? 0),
                'JV' => $this->money($tax['future_mandatory_deductions'] ?? 0), 'JW' => $this->money($tax['annual_mandatory_deductions'] ?? 0),
                'JX' => $this->money($tax['monthly_withholding_taxable_income'] ?? $tax['monthly_taxable_income'] ?? 0),
                'JY' => $this->money($tax['annual_taxable_income'] ?? 0), 'JZ' => $this->money($tax['annual_tax_due'] ?? 0),
                'KA' => $this->money($tax['previous_tax_withheld'] ?? 0), 'KB' => $this->money($tax['current_tax_withheld'] ?? 0),
                'KC' => $this->money($tax['future_tax_withheld'] ?? 0), 'KD' => $this->money($tax['total_tax_withheld'] ?? 0),
                'KE' => $this->money($tax['under_over_withheld'] ?? 0), 'KG' => $this->money($tax['monthly_tax_due'] ?? 0),
            ]);

            $this->setSnapshotLoanCells($sheet, $excelRow, $loans);
        }
    }

    private function setSnapshotLoanCells(Worksheet $sheet, int $row, array $loans): void
    {
        $amounts = [];
        foreach (self::SNAPSHOT_LOAN_AMOUNT_COLUMNS as $key => $column) {
            $amount = $this->money($loans[$key] ?? 0);
            if ($amount !== 0.0) {
                $amounts[$column] = round(($amounts[$column] ?? 0) + $amount, 2);
            }
        }
        foreach ($amounts as $column => $amount) {
            $sheet->setCellValue("{$column}{$row}", $amount);
        }
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
        $dynamicAdjustmentColumns = $this->dynamicAdjustmentColumns($sheet);

        foreach ($rows->values() as $index => $row) {
            $excelRow = self::FIRST_DATA_ROW + $index;
            $leave = $row['leave_deduction'] ?? [];
            $statutory = $row['statutory_deductions'] ?? [];
            $governmentShares = $row['statutory_government_shares'] ?? [];
            $tax = $row['tax'] ?? [];
            $loans = $row['loan_deductions']['columns'] ?? [];
            $programs = collect($row['program_deductions']['items'] ?? []);
            $mandatoryPrograms = collect($row['mandatory_program_deductions']['items'] ?? []);
            $adjustments = $row['compensation_adjustments'] ?? [];
            $mandatoryAdjustments = $row['mandatory_deduction_adjustments']['items'] ?? [];
            $totalOtherDeductions = $row['total_other_deductions']
                ?? (($row['program_deductions']['total'] ?? 0) + ($row['additional_premiums']['total'] ?? 0) + ($row['loan_deductions']['total'] ?? 0));

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
                'AG' => $this->money($adjustments['basic_salary'] ?? 0),
                'AH' => $this->money($adjustments['subsistence'] ?? 0),
                'AI' => $this->money($adjustments['laundry'] ?? 0),
                'AJ' => $this->money($adjustments['pera'] ?? 0),
                'AK' => $adjustments['remarks'] ?? null,
                'AL' => "=ROUND(SUM(AB{$excelRow}:AE{$excelRow})+SUM(AG{$excelRow}:AJ{$excelRow}),2)",
                'AN' => $this->money($statutory['life_retirement'] ?? 0),
                'AO' => $this->money($governmentShares['government_life_retirement'] ?? 0),
                'AP' => $this->money($governmentShares['ec'] ?? 0),
                'AQ' => $this->money($statutory['phic'] ?? 0),
                'AR' => $this->money($governmentShares['government_phic'] ?? 0),
                'AS' => $this->money($statutory['mandatory_pagibig'] ?? 0),
                'AT' => $this->programAmount($mandatoryPrograms, ['hdmf ps 2 ms', 'hdmf (ps) 2 ms'])
                    ?: $this->money($statutory['hdmf_ps_2_ms'] ?? 0),
                'AU' => $this->money($governmentShares['government_pagibig'] ?? 0),
                'AV' => $this->programAmount($mandatoryPrograms, ['ea deduction'])
                    ?: $this->money($statutory['ea_deduction'] ?? 0),
                'AW' => $this->money($mandatoryAdjustments['life_retirement'] ?? 0),
                'AX' => $this->money($mandatoryAdjustments['government_life_retirement'] ?? 0),
                'AY' => $this->money($mandatoryAdjustments['ec'] ?? 0),
                'AZ' => $this->money($mandatoryAdjustments['phic'] ?? 0),
                'BA' => $this->money($mandatoryAdjustments['government_phic'] ?? 0),
                'BB' => $this->money($mandatoryAdjustments['mandatory_pagibig'] ?? 0),
                'BC' => $this->money($mandatoryAdjustments['government_pagibig'] ?? 0),
                'BD' => $this->money($mandatoryAdjustments['ea_deduction'] ?? 0),
                'BE' => $this->money($row['total_mandatory_deductions'] ?? 0),
                'EK' => $this->programAmount($programs, ['death aid']),
                'EL' => $this->programAmount($programs, ['penalty bac', 'bac']),
                'EM' => $this->programAmount($programs, ['mmsu']),
                self::ADDITIONAL_PREMIUM_COLUMN => $this->money($row['additional_premiums']['total'] ?? 0),
                'EO' => $this->money($totalOtherDeductions),
                'ET' => $this->money($tax['monthly_mandatory_deductions'] ?? 0),
                'EU' => $this->money($tax['monthly_net_income'] ?? 0),
                'EV' => $this->money($tax['monthly_tax_due'] ?? 0),
                'EX' => $this->money($row['net_after_loan_deductions'] ?? 0),
                'EY' => $this->money($row['fifteenth'] ?? 0),
                'EZ' => $this->money($row['thirtieth'] ?? 0),
                'FE' => $this->money($row['basic_salary'] ?? 0),
                'FF' => $this->hazardPercent($row),
                'FG' => $this->hazardAmount($row),
                'FI' => $this->hazardAmount($row),
                'FK' => $this->money($tax['monthly_tax_due'] ?? 0),
                'FM' => $this->hazardAmount($row),
            ]);

            $this->setLoanAmountCells($sheet, $excelRow, $loans);
            $this->setDynamicAdjustmentCells($sheet, $excelRow, $adjustments, $dynamicAdjustmentColumns);
        }
    }

    private function applyCurrentReviewHeaders(Worksheet $sheet): void
    {
        $labels = collect(app(PayrollLoanReferenceService::class)->columnGroups())
            ->flatMap(fn (array $columns) => $columns)
            ->all();

        foreach ($labels as $key => $label) {
            $column = self::LOAN_AMOUNT_COLUMNS[$key] ?? null;
            if ($column !== null) {
                $sheet->setCellValue("{$column}3", strtoupper((string) $label));
            }
        }

        $sheet->setCellValue(self::ADDITIONAL_PREMIUM_COLUMN.'3', 'ADDITIONAL PREMIUM');
    }

    private function setLoanAmountCells(Worksheet $sheet, int $row, array $loans): void
    {
        $amountsByColumn = [];

        foreach (self::LOAN_AMOUNT_COLUMNS as $key => $column) {
            $amount = $this->money($loans[$key] ?? 0);
            if ($amount === 0.0) {
                continue;
            }

            $amountsByColumn[$column] = round(($amountsByColumn[$column] ?? 0) + $amount, 2);
        }

        foreach ($amountsByColumn as $column => $amount) {
            $sheet->setCellValue("{$column}{$row}", $amount);
        }
    }

    private function dynamicAdjustmentColumns(Worksheet $sheet): array
    {
        $reserved = array_fill_keys(array_merge(
            self::RESERVED_DATA_COLUMNS,
            array_values(self::LOAN_AMOUNT_COLUMNS),
        ), true);
        $columns = [];
        $lastColumn = Coordinate::columnIndexFromString(self::LAST_DATA_COLUMN);

        for ($columnIndex = 1; $columnIndex <= $lastColumn; $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            if (isset($reserved[$column])) {
                continue;
            }

            foreach (self::HEADER_ROWS as $row) {
                $label = $this->normalizeHeader($sheet->getCell("{$column}{$row}")->getValue());
                if ($label === '') {
                    continue;
                }

                $columns[$label][] = $column;
            }
        }

        return $columns;
    }

    private function setDynamicAdjustmentCells(Worksheet $sheet, int $row, array $adjustments, array $columnsByHeader): void
    {
        foreach (($adjustments['extra_items'] ?? []) as $item) {
            $amount = $this->money($item['signed_amount'] ?? 0);
            if ($amount === 0.0) {
                continue;
            }

            $columns = collect([
                $item['type'] ?? null,
                $item['code'] ?? null,
                $item['key'] ?? null,
                $item['type_id'] ?? null,
            ])
                ->map(fn ($value) => $this->normalizeHeader($value))
                ->filter()
                ->unique()
                ->flatMap(fn (string $label) => $columnsByHeader[$label] ?? [])
                ->unique()
                ->all();

            foreach ($columns as $column) {
                $sheet->setCellValue("{$column}{$row}", $amount);
            }
        }
    }

    private function normalizeHeader(mixed $value): string
    {
        return str((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
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

        $hazard = $this->hazardAmount($row);

        return $hazard > 0 ? round($hazard / $basicSalary, 4) : null;
    }

    private function hazardAmount(array $row): float
    {
        return $this->money($row['tax']['hazard'] ?? 0);
    }
}
