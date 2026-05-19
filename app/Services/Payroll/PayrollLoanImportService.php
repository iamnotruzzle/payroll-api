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

    public function buildTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Loan Due Import');
        $sheet->freezePane('A5');
        $spreadsheet->getSecurity()->setLockStructure(true);
        $spreadsheet->getSecurity()->setWorkbookPassword('mmmhmc');

        $templateColumns = $this->templateColumns();
        $headers = array_column($templateColumns, 'label');
        $sheet->fromArray([['Entity', null, null]], null, 'A1');
        $sheet->fromArray($headers, null, 'A4');

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
        $this->addLoanTypeReferenceSheet($spreadsheet);

        $entityValidation = $sheet->getCell('B1')->getDataValidation();
        $entityCodes = app(PayrollLoanReferenceService::class)->entityCodes();
        $entityValidation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(false)
            ->setShowDropDown(true)
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
                ->setShowDropDown(true)
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
                ->setShowDropDown(true)
                ->setFormula1('=INDIRECT("LoanTypes_"&SUBSTITUTE(SUBSTITUTE($B$1,"-","_")," ","_"))')
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
            ['Deduction Type', 'No', 'Dropdown changes based on the entity selected in B1.'],
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

        $path = sys_get_temp_dir().'/loan_due_import_template_'.now()->format('Ymd_His').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function addLoanTypeReferenceSheet(Spreadsheet $spreadsheet): void
    {
        $reference = app(PayrollLoanReferenceService::class);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Deduction Type Records');
        $sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('mmmhmc');
        $maxRows = 1;
        foreach ($reference->entityCodes() as $index => $entityCode) {
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

        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($reference->entityCodes())));
        $sheet->getStyle("A1:{$lastColumn}{$maxRows}")->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
        ]);
        for ($columnIndex = 1; $columnIndex <= count($reference->entityCodes()); $columnIndex++) {
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

        return $columns;
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

    public function import(string $path, string $originalFilename, ?string $storedPath, ?string $importedBy): PayrollLoanImport
    {
        return $this->savePreview(
            $this->preview($path),
            $originalFilename,
            $storedPath,
            $importedBy,
        );
    }

    public function preview(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);

        $headerRow = $this->findHeaderRow($sheet);
        $headerMap = $this->headerMap($sheet, $headerRow);
        $rows = $this->extractRows($sheet, $headerRow, $headerMap);
        $sourceEntity = $this->metadataEntity($sheet) ?: $this->firstNonEmpty($rows, 'entity') ?: 'OTHER';
        $billingPeriod = $this->firstNonEmpty($rows, 'due_month');
        $items = $this->validateRows($rows, $sourceEntity);

        $validRows = collect($items)->where('validation_status', 'valid')->count();

        return [
            'source_entity' => strtoupper($sourceEntity),
            'billing_period' => $this->parseMonth($billingPeriod)?->toDateString(),
            'total_rows' => count($items),
            'valid_rows' => $validRows,
            'invalid_rows' => count($items) - $validRows,
            'items' => $items,
        ];
    }

    public function savePreview(array $preview, string $originalFilename, ?string $storedPath, ?string $importedBy): PayrollLoanImport
    {
        return DB::connection('payroll')->transaction(function () use ($preview, $originalFilename, $storedPath, $importedBy) {
            $import = PayrollLoanImport::create([
                'source_entity' => $preview['source_entity'] ?? 'OTHER',
                'billing_period' => $preview['billing_period'] ?? null,
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

    private function validateRow(array $row, ?string $sourceEntity = null): array
    {
        $errors = [];
        $entityCodes = app(PayrollLoanReferenceService::class)->entityCodes();
        $entity = strtoupper(trim((string) ($row['entity'] ?: $sourceEntity ?: '')));
        if (! in_array($entity, $entityCodes, true)) {
            $errors[] = 'Entity must be one of: '.implode(', ', $entityCodes).'.';
        }

        $dueMonth = $this->parseMonth($row['due_month'] ?? null);
        if (! $dueMonth) {
            $errors[] = 'Due Month is required and must be a valid date/month.';
        }

        $employeeName = trim((string) ($row['employee_name'] ?? ''));
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

        $matchedEmpId = $this->matchEmployee($row['employee_id'] ?? null, $employeeName);
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

    private function validateRows(array $rows, ?string $sourceEntity = null): array
    {
        $items = collect($rows)
            ->map(fn (array $row) => $this->validateRow($row, $sourceEntity))
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

        return trim((string) $cell->getFormattedValue());
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
            if (trim((string) ($row[$key] ?? '')) !== '') {
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

    private function metadataEntity($sheet): ?string
    {
        foreach (['B1', 'B2', 'A2'] as $cell) {
            $value = strtoupper(trim((string) $sheet->getCell($cell)->getFormattedValue()));
            if (in_array($value, app(PayrollLoanReferenceService::class)->entityCodes(), true)) {
                return $value;
            }
        }

        return null;
    }
}
