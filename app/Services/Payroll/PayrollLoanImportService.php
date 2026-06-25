<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollLoanImport;
use App\Models\Payroll\PayrollLoanImportItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollLoanImportService
{
    private const TEMPLATE_FIRST_ROW = 5;
    private const TEMPLATE_LAST_ROW = 204;

    public const COLUMNS = [
        ['key' => 'entity', 'label' => 'Entity', 'width' => 14, 'required' => true],
        ['key' => 'due_month', 'label' => 'Due Month', 'width' => 14, 'required' => true],
        ['key' => 'employee_id', 'label' => 'Employee ID', 'width' => 16, 'required' => false],
        ['key' => 'employee_name', 'label' => 'Employee Name', 'width' => 30, 'required' => true],
        ['key' => 'loan_account_no', 'label' => 'Reference/Account No.', 'width' => 24, 'required' => true],
        ['key' => 'loan_type', 'label' => 'Deduction Type', 'width' => 18, 'required' => false],
        ['key' => 'monthly_amortization', 'label' => 'Monthly Amortization', 'width' => 20, 'required' => true],
        ['key' => 'amount_due', 'label' => 'Amount Due', 'width' => 16, 'required' => true],
        ['key' => 'outstanding_balance', 'label' => 'Outstanding Balance', 'width' => 20, 'required' => false],
        ['key' => 'principal_due', 'label' => 'Principal Due', 'width' => 16, 'required' => false],
        ['key' => 'interest_due', 'label' => 'Interest Due', 'width' => 16, 'required' => false],
        ['key' => 'penalty_due', 'label' => 'Penalty Due', 'width' => 16, 'required' => false],
        ['key' => 'remarks', 'label' => 'Remarks', 'width' => 28, 'required' => false],
    ];

    private const HEADER_ALIASES = [
        'bank' => 'entity',
        'source entity' => 'entity',
        'due month yyyy mm' => 'due_month',
        'billing month' => 'due_month',
        'employee no' => 'employee_id',
        'emp id' => 'employee_id',
        'borrower' => 'employee_name',
        'borrower name' => 'employee_name',
        'name of borrower' => 'employee_name',
        'name' => 'employee_name',
        'pn number' => 'loan_account_no',
        'loan account no' => 'loan_account_no',
        'reference account no' => 'loan_account_no',
        'promissory note no' => 'loan_account_no',
        'contractrefno' => 'loan_account_no',
        'contract ref no' => 'loan_account_no',
        'applno' => 'loan_account_no',
        'loan type' => 'loan_type',
        'deduction type' => 'loan_type',
        'loan amount' => 'outstanding_balance',
        'monthly amo' => 'monthly_amortization',
        'monthly amortization' => 'monthly_amortization',
        'momamoft' => 'monthly_amortization',
        'amount dqe' => 'amount_due',
        'amount due' => 'amount_due',
        'pdi due' => 'amount_due',
        'outstanding balance' => 'outstanding_balance',
        'outbalan e' => 'outstanding_balance',
        'opb' => 'outstanding_balance',
        'regint' => 'interest_due',
        'addinterest' => 'interest_due',
        'pencharge' => 'penalty_due',
        'penalty' => 'penalty_due',
    ];

    private const EXTRACTED_LOAN_DETAIL_HEADERS = ['REMARKS', 'AMORT', 'TERMS'];

    private const EXTRACTED_LOAN_FALLBACK_HEADERS = [
        'B' => 'PAGIBIG II',
        'F' => 'EMERGENCY LOAN',
        'J' => 'COMPUTER LOAN',
        'N' => 'CONSO',
        'R' => 'REGULAR POLICY LOAN',
        'V' => 'GSIS MPL',
        'Z' => 'UOLI PREM.',
        'AD' => 'GFAL',
        'AH' => 'OPTIONAL POLICY',
        'AL' => 'HOUSING LOAN',
        'AP' => 'UCPB(W1)',
        'AT' => 'GSIS MPL_LITE',
        'AX' => 'PAGIBIG MPL',
        'BB' => 'UCPB(W2)',
        'BF' => 'PAGIBIG CALAMITY',
        'BJ' => 'DBP',
        'BN' => 'LBP',
        'BR' => 'COCO',
        'BV' => 'PAGIBIG II (2)',
        'BZ' => 'PAGIBIG II (3)',
        'CD' => 'MS',
        'CH' => 'SSS',
        'CL' => 'GSEL',
        'CP' => 'GBEL',
    ];

    public function buildTemplate(string $mode = 'loans'): string
    {
        $additionalPremiumMode = $this->isAdditionalPremiumMode($mode);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($additionalPremiumMode ? 'Additional Premium Import' : 'Loan Due Import');
        $sheet->freezePane('A5');
        $spreadsheet->getSecurity()->setLockStructure(true);
        $spreadsheet->getSecurity()->setWorkbookPassword('mmmhmc');

        $templateColumns = $this->templateColumns();
        $headers = array_column($templateColumns, 'label');
        $sheet->fromArray([['Entity', null, null]], null, 'A1');
        $sheet->fromArray($headers, null, 'A4');
        $entityCodes = $this->entityCodesForMode($mode);
        $sheet->setCellValue('B1', $entityCodes[0] ?? 'GSIS');

        $lastColumn = Coordinate::stringFromColumnIndex(count($templateColumns));
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);
        $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(30);
        $sheet->setAutoFilter("A4:{$lastColumn}4");

        foreach ($templateColumns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }

        $employees = $this->employeeReferenceRows();
        $employeeSheet = $this->addEmployeeReferenceSheet($spreadsheet, $employees);
        $employeeLastRow = max(2, count($employees) + 1);
        $spreadsheet->addNamedRange(new NamedRange('EmployeeNames', $employeeSheet, '$A$2:$A$'.$employeeLastRow));
        $this->addLoanTypeReferenceSheet($spreadsheet, $entityCodes);

        $entityValidation = $sheet->getCell('B1')->getDataValidation();
        $entityValidation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(false)
            ->setShowDropDown(false)
            ->setFormula1('"'.implode(',', $entityCodes).'"')
            ->setErrorTitle('Invalid entity')
            ->setError('Choose one of the supported entities.');

        for ($row = self::TEMPLATE_FIRST_ROW; $row <= self::TEMPLATE_LAST_ROW; $row++) {
            $dateValidation = $sheet->getCell("A{$row}")->getDataValidation();
            $dateValidation->setType(DataValidation::TYPE_DATE)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
                ->setFormula1('DATE(2020,1,1)')
                ->setAllowBlank(false)
                ->setErrorTitle('Invalid due month')
                ->setError('Use the first day of the due month, for example 2026-05-01.');

            $employeeValidation = $sheet->getCell("D{$row}")->getDataValidation();
            $employeeValidation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(false)
                ->setFormula1('=EmployeeNames')
                ->setErrorTitle('Invalid employee')
                ->setError('Choose an employee from the HRIS employee list.');

            $sheet->setCellValue(
                "B{$row}",
                '=IFERROR(VLOOKUP(D'.$row.',\'Employee Records\'!$A$2:$B$'.$employeeLastRow.',2,FALSE),"")'
            );
            $sheet->setCellValue(
                "D{$row}",
                '=IF($C'.$row.'="","",IFERROR(INDEX(EmployeeNames,MATCH($C'.$row.'&"*",EmployeeNames,0)),"NO MATCH"))'
            );

            $loanTypeValidation = $sheet->getCell("F{$row}")->getDataValidation();
            $loanTypeValidation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(false)
                ->setFormula1('=INDIRECT(IF($B$1="","LoanTypes_All","LoanTypes_"&SUBSTITUTE(SUBSTITUTE($B$1,"-","_")," ","_")))')
                ->setErrorTitle('Invalid loan type')
                ->setError('Choose a loan type supported by the selected entity.');

            foreach (['G', 'H', 'I', 'J', 'K', 'L'] as $letter) {
                $amountValidation = $sheet->getCell("{$letter}{$row}")->getDataValidation();
                $amountValidation->setType(DataValidation::TYPE_DECIMAL)
                    ->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
                    ->setFormula1('0')
                    ->setAllowBlank(in_array($letter, ['I', 'J', 'K', 'L'], true))
                    ->setErrorTitle('Invalid amount')
                    ->setError('Amounts must be zero or greater.');
            }
        }

        $sheet->getStyle('A'.self::TEMPLATE_FIRST_ROW.':A'.self::TEMPLATE_LAST_ROW)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('G'.self::TEMPLATE_FIRST_ROW.':L'.self::TEMPLATE_LAST_ROW)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A1:{$lastColumn}".self::TEMPLATE_LAST_ROW)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getCell('C1')->setValue(null);
        $sheet->getStyle('C1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_NONE],
            'font' => ['bold' => false, 'color' => ['argb' => 'FF000000']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
        ]);

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Field', 'Required', 'Notes'],
            ['Entity', 'Yes', 'Choose once in cell B1. All rows in the workbook use this entity.'],
            ['Due Month', 'Yes', 'Use the first day of the payroll month, for example 2026-05-01.'],
            ['Employee ID', 'Auto', 'Auto-fills from the selected employee search. Keep the formula intact.'],
            ['Employee Search', 'No', 'Type the start of the employee lastname here to auto-fill Employee Name on the same row.'],
            ['Employee Name', 'Yes', 'Auto-fills from Employee Search, or choose manually from the full dropdown.'],
            ['Reference/Account No.', 'Yes', 'PN number, contract reference, application number, account reference, or deduction reference.'],
            ['Deduction Type', 'No', 'Dropdown changes based on the entity selected in B1. B1 defaults to the first active entity.'],
            ['Monthly Amortization', 'Yes', 'Scheduled monthly amortization from the source billing.'],
            ['Amount Due', 'Yes', 'Deduction amount for the selected payroll month.'],
            ['Outstanding/Principal/Interest/Penalty', 'No', 'Optional audit fields retained from bank/entity files.'],
        ], null, 'A1');
        $instructions->getStyle('A1:C1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
        ]);
        $instructions->getColumnDimension('A')->setWidth(28);
        $instructions->getColumnDimension('B')->setWidth(14);
        $instructions->getColumnDimension('C')->setWidth(82);

        $prefix = $additionalPremiumMode ? 'additional_premium_import_template_' : 'loan_due_import_template_';
        $path = sys_get_temp_dir().'/'.$prefix.now()->format('Ymd_His').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function addLoanTypeReferenceSheet(Spreadsheet $spreadsheet, array $entityCodes): void
    {
        $reference = app(PayrollLoanReferenceService::class);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Deduction Type Records');
        $sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('mmmhmc');
        $maxRows = 1;
        $allTypes = collect($entityCodes)
            ->flatMap(fn (string $entityCode) => $reference->typeNamesForEntity($entityCode))
            ->unique()
            ->values()
            ->all();

        foreach ($entityCodes as $index => $entityCode) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $types = $reference->typeNamesForEntity($entityCode);
            $sheet->setCellValue("{$column}1", $entityCode);
            if ($types) {
                $sheet->fromArray(array_map(fn ($type) => [$type], $types), null, "{$column}2");
                $maxRows = max($maxRows, count($types) + 1);
                $spreadsheet->addNamedRange(new NamedRange(
                    'LoanTypes_'.$this->excelNameSuffix($entityCode),
                    $sheet,
                    '$'.$column.'$2:$'.$column.'$'.(count($types) + 1)
                ));
            }
        }

        $allTypesColumn = Coordinate::stringFromColumnIndex(count($entityCodes) + 1);
        $sheet->setCellValue("{$allTypesColumn}1", 'ALL');
        if ($allTypes) {
            $sheet->fromArray(array_map(fn ($type) => [$type], $allTypes), null, "{$allTypesColumn}2");
            $maxRows = max($maxRows, count($allTypes) + 1);
            $spreadsheet->addNamedRange(new NamedRange(
                'LoanTypes_All',
                $sheet,
                '$'.$allTypesColumn.'$2:$'.$allTypesColumn.'$'.(count($allTypes) + 1)
            ));
        }

        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($entityCodes) + 1));
        $sheet->getStyle("A1:{$lastColumn}{$maxRows}")->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
        ]);
        for ($columnIndex = 1; $columnIndex <= count($entityCodes) + 1; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(28);
        }
    }

    private function excelNameSuffix(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', strtoupper($value));
    }

    private function templateColumns(): array
    {
        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (array $column) => $column['key'] !== 'entity'
        ));
        array_splice($columns, 2, 0, [[
            'key' => 'employee_search',
            'label' => 'Employee Search',
            'width' => 24,
            'required' => false,
        ]]);

        return count($columns) > 1
            ? $columns
            : $this->fallbackExtractedLoanColumns($sheet, $columns);
    }

    private function fallbackExtractedLoanColumns($sheet, array $detectedColumns): array
    {
        $columnsByIndex = collect($detectedColumns)->keyBy('col')->all();

        foreach (self::EXTRACTED_LOAN_FALLBACK_HEADERS as $letter => $fallbackHeader) {
            $col = Coordinate::columnIndexFromString($letter);
            $header = trim((string) $sheet->getCell([$col, 1])->getFormattedValue()) ?: $fallbackHeader;

            if (! $this->looksLikeExtractedLoanAmountColumn($sheet, $col)) {
                continue;
            }

            $columnsByIndex[$col] ??= [
                'col' => $col,
                'loan_type' => $header,
                'entity' => $this->entityForExtractedLoanHeader($header),
                'remarks_col' => $col + 1,
                'amort_col' => $col + 2,
                'terms_col' => $col + 3,
            ];
        }

        ksort($columnsByIndex);

        return array_values($columnsByIndex);
    }

    private function addEmployeeReferenceSheet(Spreadsheet $spreadsheet, array $employees)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Employee Records');
        $sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $sheet->freezePane('A2');
        $sheet->fromArray([
            ['Employee Name', 'Employee ID', 'Lastname', 'Firstname', 'Middlename'],
        ], null, 'A1');

        if ($employees) {
            $sheet->fromArray($employees, null, 'A2');
        }

        $lastRow = max(2, count($employees) + 1);
        $sheet->getStyle("A1:E{$lastRow}")->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('mmmhmc');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A1:E{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(24);
        $sheet->setAutoFilter("A1:E{$lastRow}");

        return $sheet;
    }

    private function employeeReferenceRows(): array
    {
        return Employee::query()
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'lastname', 'firstname', 'middlename'])
            ->map(function (Employee $employee) {
                $lastname = trim((string) $employee->lastname);
                $firstname = trim((string) $employee->firstname);
                $middlename = trim((string) $employee->middlename);
                $displayName = trim($lastname.', '.$firstname.' '.$middlename);

                return [
                    $displayName,
                    $employee->emp_id,
                    $lastname,
                    $firstname,
                    $middlename,
                ];
            })
            ->values()
            ->all();
    }

    public function import(string $path, string $originalFilename, ?string $storedPath, ?string $importedBy, string $mode = 'loans'): PayrollLoanImport
    {
        return $this->savePreview(
            $this->preview($path, $originalFilename, $mode),
            $originalFilename,
            $storedPath,
            $importedBy,
        );
    }

    public function preview(string $path, ?string $originalFilename = null, string $mode = 'loans'): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheetNames = $this->worksheetNames($reader, $path);

        if (! $this->isAdditionalPremiumMode($mode) && $extractedLoansSheetName = $this->findWorksheetName($sheetNames, 'extracted loans')) {
            $reader->setLoadSheetsOnly([$extractedLoansSheetName]);
            $spreadsheet = $reader->load($path);

            return $this->previewExtractedLoans($spreadsheet->getSheet(0), $originalFilename);
        }

        if ($sheetNames !== []) {
            $reader->setLoadSheetsOnly([$sheetNames[0]]);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);

        $headerRow = $this->findHeaderRow($sheet);
        $headerMap = $this->headerMap($sheet, $headerRow);
        $rows = $this->extractRows($sheet, $headerRow, $headerMap);
        $sourceEntity = $this->isAdditionalPremiumMode($mode)
            ? $this->additionalPremiumEntityCode()
            : ($this->metadataEntity($sheet, $mode) ?: $this->firstNonEmpty($rows, 'entity') ?: 'OTHER');
        if ($this->isAdditionalPremiumMode($mode)) {
            $rows = array_map(fn (array $row) => array_merge($row, ['entity' => $sourceEntity]), $rows);
        }
        $billingPeriod = $this->firstNonEmpty($rows, 'due_month');
        $items = $this->validateRows($rows, $sourceEntity, $mode);

        $validRows = collect($items)->where('validation_status', 'valid')->count();

        return [
            'source_entity' => strtoupper($sourceEntity),
            'billing_period' => $this->parseMonth($billingPeriod)?->toDateString()
                ?? $this->parseMonthFromFilename($originalFilename)?->toDateString(),
            'total_rows' => count($items),
            'valid_rows' => $validRows,
            'invalid_rows' => count($items) - $validRows,
            'loan_type_counts' => $this->loanTypeCounts($items),
            'items' => $items,
        ];
    }

    public function savePreview(array $preview, string $originalFilename, ?string $storedPath, ?string $importedBy): PayrollLoanImport
    {
        return DB::connection('payroll')->transaction(function () use ($preview, $originalFilename, $storedPath, $importedBy) {
            $import = PayrollLoanImport::create([
                'source_entity' => $preview['source_entity'] ?? 'OTHER',
                'billing_period' => $preview['billing_period'] ?? $this->parseMonthFromFilename($originalFilename)?->toDateString(),
                'original_filename' => $originalFilename,
                'stored_path' => $storedPath,
                'imported_by' => $importedBy,
                'imported_at' => now(),
                'status' => 'validated',
            ]);

            $valid = 0;
            foreach ($preview['items'] ?? [] as $item) {
                $item['import_id'] = $import->id;
                $import->items()->create($item);
                $valid += $item['validation_status'] === 'valid' ? 1 : 0;
            }

            $totalRows = count($preview['items'] ?? []);
            $import->update([
                'total_rows' => $totalRows,
                'valid_rows' => $valid,
                'invalid_rows' => $totalRows - $valid,
                'status' => $valid === $totalRows ? 'ready' : 'needs_review',
            ]);

            return $import->load('items');
        });
    }

    private function worksheetNames($reader, string $path): array
    {
        try {
            return $reader->listWorksheetNames($path) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function findWorksheetName(array $sheetNames, string $target): ?string
    {
        foreach ($sheetNames as $sheetName) {
            if (strtolower(trim((string) $sheetName)) === strtolower($target)) {
                return $sheetName;
            }
        }

        return null;
    }

    private function previewExtractedLoans($sheet, ?string $originalFilename = null): array
    {
        $billingPeriod = $this->parseMonthFromFilename($originalFilename)
            ?? CarbonImmutable::today()->startOfMonth();
        $loanColumns = $this->extractedLoanColumns($sheet);
        $rows = $this->extractExtractedLoanRows($sheet, $loanColumns, $billingPeriod);
        $items = $this->validateRows($rows);
        $validRows = collect($items)->where('validation_status', 'valid')->count();

        return [
            'source_entity' => 'EXTRACTED LOANS',
            'billing_period' => $billingPeriod->toDateString(),
            'total_rows' => count($items),
            'valid_rows' => $validRows,
            'invalid_rows' => count($items) - $validRows,
            'loan_type_counts' => $this->loanTypeCounts($items),
            'detected_loan_columns' => collect($loanColumns)
                ->map(fn (array $column) => [
                    'column' => Coordinate::stringFromColumnIndex($column['col']),
                    'loan_type' => $column['loan_type'],
                ])
                ->values()
                ->all(),
            'items' => $items,
        ];
    }

    private function loanTypeCounts(array $items): array
    {
        return collect($items)
            ->groupBy(fn (array $item) => (string) ($item['loan_type'] ?: 'Unspecified'))
            ->map(fn (Collection $items) => $items->count())
            ->sortDesc()
            ->all();
    }

    private function extractedLoanColumns($sheet): array
    {
        $columns = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($col = 2; $col <= $highestColumn; $col++) {
            $header = trim((string) $sheet->getCell([$col, 1])->getFormattedValue());
            $normalized = $this->normalizeExtractedHeader($header);

            if ($header === ''
                || in_array($normalized, self::EXTRACTED_LOAN_DETAIL_HEADERS, true)
                || in_array($normalized, ['ID NO', 'DIVISION'], true)) {
                continue;
            }

            $nextHeader = $this->normalizeExtractedHeader($sheet->getCell([$col + 1, 1])->getFormattedValue());
            $secondHeader = $this->normalizeExtractedHeader($sheet->getCell([$col + 2, 1])->getFormattedValue());
            $thirdHeader = $this->normalizeExtractedHeader($sheet->getCell([$col + 3, 1])->getFormattedValue());

            if (! $this->looksLikeExtractedLoanAmountColumn($sheet, $col)
                || ($nextHeader !== 'REMARKS' && ! in_array($secondHeader, ['AMORT', 'AMORTIZATION'], true) && $thirdHeader !== 'TERMS')) {
                continue;
            }

            $columns[] = [
                'col' => $col,
                'loan_type' => $header,
                'entity' => $this->entityForExtractedLoanHeader($header),
                'remarks_col' => $col + 1,
                'amort_col' => $col + 2,
                'terms_col' => $col + 3,
            ];

            $col += 3;
        }

        return $columns;
    }

    private function normalizeExtractedHeader(mixed $value): string
    {
        $header = strtoupper(trim((string) $value));
        $header = preg_replace('/\s+/', ' ', $header);
        $header = preg_replace('/^(REMARKS|AMORT|AMORTIZATION|TERMS)\d+$/', '$1', $header);

        return $header ?: '';
    }

    private function looksLikeExtractedLoanAmountColumn($sheet, int $col): bool
    {
        $highestRow = $sheet->getHighestDataRow();
        $numericValues = 0;

        for ($row = 3; $row <= $highestRow; $row++) {
            $amount = $this->cellAmount($sheet, $col, $row);
            if ($amount !== null && $amount > 0) {
                $numericValues++;
            }

            if ($numericValues >= 1) {
                return true;
            }
        }

        return false;
    }

    private function extractExtractedLoanRows($sheet, array $loanColumns, CarbonImmutable $billingPeriod): array
    {
        $rows = [];
        $seen = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = 3; $rowNumber <= $highestRow; $rowNumber++) {
            $employeeId = trim((string) $sheet->getCell([1, $rowNumber])->getFormattedValue());
            if ($employeeId === '') {
                continue;
            }

            $employee = Employee::query()
                ->where('emp_id', $employeeId)
                ->where('is_active', 'Y')
                ->first(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'prefix', 'suffix']);
            $employeeName = $employee?->full_name ?: '';

            foreach ($loanColumns as $column) {
                $amountDue = $this->cellAmount($sheet, $column['col'], $rowNumber);
                if ($amountDue === null || $amountDue <= 0) {
                    continue;
                }

                $reference = trim((string) $sheet->getCell([$column['remarks_col'], $rowNumber])->getFormattedValue());
                $amortization = $this->cellAmount($sheet, $column['amort_col'], $rowNumber);
                $terms = trim((string) $sheet->getCell([$column['terms_col'], $rowNumber])->getFormattedValue());
                $dedupeKey = implode('|', [
                    strtoupper($column['entity']),
                    $billingPeriod->toDateString(),
                    strtoupper($employeeId),
                    strtoupper($reference !== '' ? $reference : $column['loan_type']),
                ]);

                if (isset($seen[$dedupeKey])) {
                    continue;
                }

                $seen[$dedupeKey] = true;
                $remarks = trim(implode(' ', array_filter([
                    $reference !== '' ? "Reference: {$reference}" : null,
                    $amortization !== null ? 'Amort: '.$this->formatAmountForRemarks($amortization) : null,
                    $terms !== '' ? "Terms: {$terms}" : null,
                ])));

                $rows[] = [
                    'row_number' => $rowNumber,
                    'entity' => $column['entity'],
                    'due_month' => $billingPeriod->toDateString(),
                    'employee_id' => $employeeId,
                    'employee_name' => $employeeName,
                    'loan_account_no' => $reference !== '' ? $reference : $column['loan_type'],
                    'loan_type' => $column['loan_type'],
                    'monthly_amortization' => $amortization ?? $amountDue,
                    'amount_due' => $amountDue,
                    'outstanding_balance' => null,
                    'principal_due' => null,
                    'interest_due' => null,
                    'penalty_due' => null,
                    'remarks' => $remarks ?: null,
                ];
            }
        }

        return $rows;
    }

    private function findHeaderRow($sheet): int
    {
        $highestRow = min(20, $sheet->getHighestDataRow());
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $matches = 0;
            for ($col = 1; $col <= $highestColumn; $col++) {
                $key = $this->columnKey($sheet->getCell([$col, $row])->getFormattedValue());
                $matches += $key ? 1 : 0;
            }
            if ($matches >= 4) {
                return $row;
            }
        }

        return 1;
    }

    private function headerMap($sheet, int $headerRow): array
    {
        $map = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($col = 1; $col <= $highestColumn; $col++) {
            $key = $this->columnKey($sheet->getCell([$col, $headerRow])->getFormattedValue());
            if ($key) {
                $map[$key] = $col;
            }
        }

        return $map;
    }

    private function extractRows($sheet, int $headerRow, array $headerMap): array
    {
        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $row = ['row_number' => $rowNumber];
            foreach (array_column(self::COLUMNS, 'key') as $key) {
                $col = $headerMap[$key] ?? null;
                $row[$key] = $col ? $this->cellValue($sheet, $col, $rowNumber, $key) : null;
            }

            if ($this->rowHasData($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function validateRow(array $row, ?string $sourceEntity = null, string $mode = 'loans'): array
    {
        $errors = [];
        $entityCodes = $this->entityCodesForMode($mode);
        $entity = strtoupper(trim((string) ($row['entity'] ?: $sourceEntity ?: '')));
        if (! in_array($entity, $entityCodes, true)) {
            $errors[] = 'Entity must be one of: '.implode(', ', $entityCodes).'.';
        }

        $dueMonth = $this->parseMonth($row['due_month'] ?? null);
        if (! $dueMonth) {
            $errors[] = 'Due Month is required and must be a valid date/month.';
        }

        $employeeName = trim((string) ($row['employee_name'] ?? ''));
        $matchedEmpId = $this->matchEmployee($row['employee_id'] ?? null, $employeeName);
        if ($employeeName === '' && $matchedEmpId) {
            $employeeName = Employee::query()
                ->where('emp_id', $matchedEmpId)
                ->first(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'prefix', 'suffix'])
                ?->full_name ?? '';
        }

        if ($employeeName === '') {
            $errors[] = 'Employee Name is required.';
        }

        $loanAccountNo = trim((string) ($row['loan_account_no'] ?? ''));
        if ($loanAccountNo === '') {
            $errors[] = 'Reference/Account No. is required.';
        }

        $monthlyAmortization = $this->amount($row['monthly_amortization'] ?? null);
        if ($monthlyAmortization === null) {
            $errors[] = 'Monthly Amortization must be numeric.';
        }

        $amountDue = $this->amount($row['amount_due'] ?? null);
        if ($amountDue === null || $amountDue <= 0) {
            $errors[] = 'Amount Due must be greater than zero.';
        }

        if (! $matchedEmpId) {
            $errors[] = 'No active HRIS employee matched by Employee ID or name.';
        }

        return [
            'row_number' => (int) $row['row_number'],
            'entity' => $entity ?: 'OTHER',
            'due_month' => $dueMonth?->toDateString() ?? CarbonImmutable::today()->startOfMonth()->toDateString(),
            'employee_id' => trim((string) ($row['employee_id'] ?? '')) ?: null,
            'matched_emp_id' => $matchedEmpId,
            'employee_name' => $employeeName ?: '(blank)',
            'loan_account_no' => $loanAccountNo ?: '(blank)',
            'loan_type' => trim((string) ($row['loan_type'] ?? '')) ?: null,
            'monthly_amortization' => $monthlyAmortization ?? 0,
            'amount_due' => $amountDue ?? 0,
            'outstanding_balance' => $this->amount($row['outstanding_balance'] ?? null),
            'principal_due' => $this->amount($row['principal_due'] ?? null),
            'interest_due' => $this->amount($row['interest_due'] ?? null),
            'penalty_due' => $this->amount($row['penalty_due'] ?? null),
            'remarks' => trim((string) ($row['remarks'] ?? '')) ?: null,
            'validation_status' => $errors ? 'invalid' : 'valid',
            'validation_errors' => $errors ?: null,
        ];
    }

    private function validateRows(array $rows, ?string $sourceEntity = null, string $mode = 'loans'): array
    {
        $items = collect($rows)
            ->map(fn (array $row) => $this->validateRow($row, $sourceEntity, $mode))
            ->values();

        $duplicates = $items
            ->groupBy(fn (array $item) => $this->duplicateKey($item))
            ->filter(fn (Collection $group, string $key) => $key !== '|||' && $group->count() > 1);

        $existingKeys = $this->existingDuplicateKeys($items);

        if ($duplicates->isEmpty() && $existingKeys->isEmpty()) {
            return $items->all();
        }

        return $items
            ->map(function (array $item) use ($duplicates, $existingKeys) {
                $key = $this->duplicateKey($item);

                if (! $duplicates->has($key) && ! $existingKeys->contains($key)) {
                    return $item;
                }

                $errors = $item['validation_errors'] ?? [];
                if ($duplicates->has($key)) {
                    $errors[] = 'Duplicate loan/account number for the same employee, entity, and due month in this file.';
                }
                if ($existingKeys->contains($key)) {
                    $errors[] = 'This employee loan/account number was already imported for the same entity and due month.';
                }
                $item['validation_status'] = 'invalid';
                $item['validation_errors'] = array_values(array_unique($errors));

                return $item;
            })
            ->all();
    }

    private function existingDuplicateKeys(Collection $items): Collection
    {
        $candidateItems = $items
            ->filter(fn (array $item) => $this->duplicateKey($item) !== '|||')
            ->values();

        if ($candidateItems->isEmpty()) {
            return collect();
        }

        $entities = $candidateItems->pluck('entity')->filter()->unique()->values()->all();
        $dueMonths = $candidateItems->pluck('due_month')->filter()->unique()->values()->all();
        $loanNumbers = $candidateItems->pluck('loan_account_no')->filter()->unique()->values()->all();
        $employeeIds = $candidateItems
            ->map(fn (array $item) => $item['matched_emp_id'] ?: ($item['employee_id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! $entities || ! $dueMonths || ! $loanNumbers || ! $employeeIds) {
            return collect();
        }

        return PayrollLoanImportItem::query()
            ->where('validation_status', 'valid')
            ->whereIn('entity', $entities)
            ->whereIn('due_month', $dueMonths)
            ->whereIn('loan_account_no', $loanNumbers)
            ->where(function ($query) use ($employeeIds) {
                $query->whereIn('matched_emp_id', $employeeIds)
                    ->orWhereIn('employee_id', $employeeIds);
            })
            ->get(['entity', 'due_month', 'matched_emp_id', 'employee_id', 'loan_account_no'])
            ->map(fn (PayrollLoanImportItem $item) => $this->duplicateKey([
                'entity' => $item->entity,
                'due_month' => $item->due_month?->toDateString(),
                'matched_emp_id' => $item->matched_emp_id,
                'employee_id' => $item->employee_id,
                'loan_account_no' => $item->loan_account_no,
            ]))
            ->unique()
            ->values();
    }

    private function duplicateKey(array $item): string
    {
        return implode('|', [
            strtoupper($item['entity'] ?? ''),
            $item['due_month'] ?? '',
            strtoupper(trim((string) ($item['matched_emp_id'] ?: ($item['employee_id'] ?? '')))),
            strtoupper(trim((string) ($item['loan_account_no'] ?? ''))),
        ]);
    }

    private function matchEmployee(?string $employeeId, string $employeeName): ?string
    {
        $employeeId = trim((string) $employeeId);
        if ($employeeId !== '') {
            $employee = Employee::query()
                ->where('emp_id', $employeeId)
                ->where('is_active', 'Y')
                ->first(['emp_id']);

            if ($employee) {
                return $employee->emp_id;
            }
        }

        $normalized = $this->normalizeName($employeeName);
        if ($normalized === '') {
            return null;
        }

        return Employee::query()
            ->where('is_active', 'Y')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'prefix', 'suffix'])
            ->first(fn (Employee $employee) => $this->normalizeName($employee->full_name) === $normalized)
            ?->emp_id;
    }

    private function columnKey(mixed $value): ?string
    {
        $header = preg_replace('/[^a-z0-9]+/', ' ', strtolower(trim((string) $value)));
        $header = trim(preg_replace('/\s+/', ' ', $header));

        foreach (self::COLUMNS as $column) {
            $label = preg_replace('/[^a-z0-9]+/', ' ', strtolower($column['label']));
            if ($header === trim($label)) {
                return $column['key'];
            }
        }

        return self::HEADER_ALIASES[$header] ?? null;
    }

    private function cellValue($sheet, int $col, int $row, string $key): mixed
    {
        $cell = $sheet->getCell([$col, $row]);

        if ($key === 'due_month' && ExcelDate::isDateTime($cell) && is_numeric($cell->getValue())) {
            return ExcelDate::excelToDateTimeObject($cell->getValue())->format('Y-m-d');
        }

        $rawValue = trim((string) $cell->getValue());
        $value = trim((string) $cell->getFormattedValue());

        if (str_starts_with($rawValue, '=') && in_array(strtoupper($value), ['', '0', 'NO MATCH'], true)) {
            return '';
        }

        return str_starts_with($value, '=') ? '' : $value;
    }

    private function parseMonth(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y-m', 'm/d/Y', 'm/d/y', 'M Y', 'F Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $value)->startOfMonth();
            } catch (\Throwable) {
            }
        }

        try {
            return CarbonImmutable::parse($value)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMonthFromFilename(?string $filename): ?CarbonImmutable
    {
        $filename = trim((string) $filename);
        if ($filename === '') {
            return null;
        }

        if (preg_match('/\b([A-Za-z]+)\s+((?:19|20)\d{2})\b/', $filename, $matches)) {
            return $this->parseMonth($matches[1].' '.$matches[2]);
        }

        if (preg_match('/\b((?:19|20)\d{2})[-_ ](0?[1-9]|1[0-2])\b/', $filename, $matches)) {
            return $this->parseMonth($matches[1].'-'.str_pad($matches[2], 2, '0', STR_PAD_LEFT));
        }

        return null;
    }

    private function amount(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace([',', 'PHP', '₱', ' '], '', strtoupper($value));
        if (! is_numeric($normalized)) {
            return null;
        }

        return round(max(0, (float) $normalized), 2);
    }

    private function cellAmount($sheet, int $col, int $row): ?float
    {
        $cell = $sheet->getCell([$col, $row]);
        $value = $cell->getCalculatedValue();

        if ($value === null || $value === '') {
            $value = $cell->getFormattedValue();
        }

        return $this->amount($value);
    }

    private function entityForExtractedLoanHeader(string $header): string
    {
        $normalized = strtoupper($header);

        return match (true) {
            str_contains($normalized, 'PAGIBIG'), str_contains($normalized, 'PAG-IBIG') => 'PAG-IBIG',
            str_contains($normalized, 'UCPB') => 'UCPB',
            str_contains($normalized, 'DBP') => 'DBP',
            str_contains($normalized, 'LBP') => 'LBP',
            str_contains($normalized, 'COCO') => 'COCO',
            str_contains($normalized, 'SSS') => 'OTHER',
            str_contains($normalized, 'MS') => 'OTHER',
            str_contains($normalized, 'GSEL') => 'OTHER',
            str_contains($normalized, 'GBEL') => 'OTHER',
            default => 'GSIS',
        };
    }

    private function formatAmountForRemarks(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function normalizeName(string $name): string
    {
        $name = str_replace(',', ' ', strtoupper($name));
        $name = preg_replace('/\b(JR|SR|III|IV|II)\b\.?/', '', $name);
        $parts = array_filter(explode(' ', preg_replace('/[^A-Z0-9]+/', ' ', $name)));
        sort($parts);

        return implode(' ', $parts);
    }

    private function rowHasData(array $row): bool
    {
        foreach (array_column(self::COLUMNS, 'key') as $key) {
            if ($key === 'entity') {
                continue;
            }

            $value = strtoupper(trim((string) ($row[$key] ?? '')));
            if ($value !== '' && $value !== 'NO MATCH' && ! str_starts_with($value, '=')) {
                return true;
            }
        }

        return false;
    }

    private function firstNonEmpty(array $rows, string $key): ?string
    {
        foreach ($rows as $row) {
            if (! empty($row[$key])) {
                return (string) $row[$key];
            }
        }

        return null;
    }

    private function metadataEntity($sheet, string $mode = 'loans'): ?string
    {
        foreach (['B1', 'B2', 'A2'] as $cell) {
            $value = strtoupper(trim((string) $sheet->getCell($cell)->getFormattedValue()));
            if (in_array($value, $this->entityCodesForMode($mode), true)) {
                return $value;
            }
        }

        return null;
    }

    private function entityCodesForMode(string $mode): array
    {
        $reference = app(PayrollLoanReferenceService::class);

        return $this->isAdditionalPremiumMode($mode)
            ? $reference->additionalPremiumEntityCodes()
            : $reference->entityCodes(false);
    }

    private function additionalPremiumEntityCode(): string
    {
        return app(PayrollLoanReferenceService::class)->additionalPremiumEntityCodes()[0] ?? 'ADDITIONAL_PREMIUM';
    }

    private function isAdditionalPremiumMode(string $mode): bool
    {
        return $mode === 'additional_premiums';
    }
}
