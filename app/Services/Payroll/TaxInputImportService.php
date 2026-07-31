<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TaxInputImportService
{
    public const FIELDS = [
        'previous_basic' => 'Basic Previous',
        'previous_hazard' => 'Hazard Previous',
        'previous_subsistence' => 'Subsistence Previous',
        'previous_mandatory_deductions' => 'Mandatory Deduction Previous',
        'previous_tax_withheld' => 'Tax Withheld Previous',
        'withholding_tax_adjustment' => 'Tax Adjustment',
    ];

    private const LEGACY_COLUMNS = [
        'JA' => 'previous_basic',
        'JF' => 'previous_hazard',
        'JJ' => 'previous_subsistence',
        'JN' => 'previous_mandatory_deductions',
        'JU' => 'previous_tax_withheld',
        'GC' => 'withholding_tax_adjustment',
    ];

    public function template(?Collection $configuredEmployees = null): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Tax Inputs');
        $headers = ['Employee ID', 'Employee Name', ...array_values(self::FIELDS)];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(42);
        foreach (range('C', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(26);
            $sheet->getStyle("{$column}2:{$column}201")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $employees = $configuredEmployees ?? Employee::query()
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix']);
        $employees = $employees->values();
        $lastRow = max(2, $employees->count() + 1);
        $sheet->setAutoFilter("A1:H{$lastRow}");

        foreach ($employees as $index => $employee) {
            $row = $index + 2;
            $sheet->setCellValueExplicit("A{$row}", (string) $employee->emp_id, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $employee->full_name);
        }

        $reference = $book->createSheet();
        $reference->setTitle('Employee Records');
        $reference->fromArray([['Employee ID', 'Employee Name']], null, 'A1');
        foreach ($employees->values() as $index => $employee) {
            $reference->fromArray([[(string) $employee->emp_id, $employee->full_name]], null, 'A'.($index + 2));
        }
        $reference->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $path = tempnam(sys_get_temp_dir(), 'tax_inputs_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function preview(string $path): array
    {
        $book = IOFactory::load($path);
        $dedicatedSheet = $book->getSheetByName('Tax Inputs') ?? $book->getSheet(0);
        $firstHeader = $this->normalize($dedicatedSheet->getCell('A1')->getFormattedValue());

        $rows = $firstHeader === 'employee id'
            ? $this->dedicatedRows($dedicatedSheet)
            : $this->legacyRows($book);

        return $this->validateRows($rows);
    }

    public function retainedOverrides(array $values): array
    {
        return collect(self::FIELDS)
            ->keys()
            ->mapWithKeys(fn (string $key) => array_key_exists($key, $values) ? [$key => $values[$key]] : [])
            ->all();
    }

    private function dedicatedRows($sheet): array
    {
        $rows = [];
        $fieldColumns = array_combine(array_keys(self::FIELDS), range('C', 'H'));

        for ($row = 2; $row <= min(5000, $sheet->getHighestDataRow()); $row++) {
            $empId = $this->normalizeEmpId($sheet->getCell("A{$row}")->getFormattedValue());
            $name = trim((string) $sheet->getCell("B{$row}")->getFormattedValue());
            $values = [];
            $invalidFields = [];
            foreach ($fieldColumns as $field => $column) {
                $raw = $this->cellValue($sheet, "{$column}{$row}");
                if ($raw === null || $raw === '') {
                    continue;
                }
                if (! is_numeric($raw)) {
                    $invalidFields[] = self::FIELDS[$field];

                    continue;
                }
                $values[$field] = round((float) $raw, 4);
            }
            if ($empId !== '' || $name !== '' || $values !== [] || $invalidFields !== []) {
                $rows[] = compact('row', 'empId', 'name', 'values', 'invalidFields');
            }
        }

        return $rows;
    }

    private function legacyRows(Spreadsheet $book): array
    {
        $rows = [];
        foreach (['hopss_finance-done', 'SUMMARY SALARY (2)', 'SUMMARY SALARY'] as $sheetName) {
            $sheet = $book->getSheetByName($sheetName);
            if (! $sheet) {
                continue;
            }
            for ($row = 5; $row <= $sheet->getHighestDataRow(); $row++) {
                $empId = $this->normalizeEmpId($sheet->getCell("B{$row}")->getFormattedValue());
                if ($empId === '') {
                    continue;
                }
                $name = '';
                $values = [];
                $invalidFields = [];
                foreach (self::LEGACY_COLUMNS as $column => $field) {
                    $raw = $this->cellValue($sheet, "{$column}{$row}");
                    if ($raw === null || $raw === '') {
                        continue;
                    }
                    if (! is_numeric($raw)) {
                        $invalidFields[] = self::FIELDS[$field];

                        continue;
                    }
                    $values[$field] = round((float) $raw, 4);
                }
                if ($values !== [] || $invalidFields !== []) {
                    $rows[] = compact('row', 'empId', 'name', 'values', 'invalidFields');
                }
            }
            if ($rows !== []) {
                break;
            }
        }

        return $rows;
    }

    private function validateRows(array $rows): array
    {
        $employees = Employee::query()
            ->whereIn('emp_id', collect($rows)->pluck('empId')->filter()->unique())
            ->get()
            ->keyBy(fn (Employee $employee) => (string) $employee->emp_id);
        $seen = [];

        return collect($rows)->map(function (array $row) use ($employees, &$seen) {
            $employee = $employees->get($row['empId']);
            $errors = [];
            if ($row['empId'] === '') {
                $errors[] = 'Employee ID is required.';
            } elseif (isset($seen[$row['empId']])) {
                $errors[] = 'Duplicate employee ID.';
            } elseif (! $employee) {
                $errors[] = 'Employee ID was not found in HRIS.';
            }
            $seen[$row['empId']] = true;
            foreach ($row['invalidFields'] as $field) {
                $errors[] = "{$field} must be numeric.";
            }
            foreach ($row['values'] as $field => $value) {
                if ($field !== 'withholding_tax_adjustment' && $value < 0) {
                    $errors[] = self::FIELDS[$field].' cannot be negative.';
                }
            }
            if ($row['values'] === []) {
                $errors[] = 'At least one tax value is required.';
            }

            $canonicalName = $employee?->full_name ?: $row['name'];
            $nameMismatch = $employee && $row['name'] !== ''
                && $this->normalize($row['name']) !== $this->normalize($canonicalName);

            return [
                'row' => $row['row'],
                'emp_id' => $row['empId'],
                'employee_name' => $canonicalName,
                'values' => $this->retainedOverrides($row['values']),
                'name_mismatch' => $nameMismatch,
                'valid' => $errors === [],
                'errors' => $errors,
            ];
        })->values()->all();
    }

    private function normalize(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower((string) $value)) ?? '');
    }

    private function normalizeEmpId(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text !== '' && is_numeric($text)) {
            return str_pad((string) (int) $text, 6, '0', STR_PAD_LEFT);
        }

        return $text;
    }

    private function cellValue($sheet, string $coordinate): mixed
    {
        $cell = $sheet->getCell($coordinate);
        if ($cell->isFormula() && $cell->getOldCalculatedValue() !== null) {
            return $cell->getOldCalculatedValue();
        }

        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            return $cell->getValue();
        }
    }
}
