<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProfessionalFeeImportService
{
    public function template(?Collection $configuredEmployees = null): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Professional Fees');
        $sheet->fromArray([['Employee ID', 'Employee Name', 'Gross Professional Fees']], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getStyle('C2:C201')->getNumberFormat()->setFormatCode('#,##0.00');

        $employees = $configuredEmployees ?? collect();
        $employees = $employees->values();
        $lastRow = max(2, $employees->count() + 1);
        $sheet->setAutoFilter("A1:C{$lastRow}");

        foreach ($employees as $index => $employee) {
            $row = $index + 2;
            $sheet->setCellValueExplicit("A{$row}", (string) $employee->emp_id, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $employee->full_name ?? trim(implode(' ', array_filter([
                $employee->lastname,
                $employee->firstname,
            ]))));
        }

        $path = tempnam(sys_get_temp_dir(), 'professional_fees_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function preview(string $path): array
    {
        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName('Professional Fees') ?? $book->getSheet(0);
        $rows = [];

        for ($row = 2; $row <= min(5000, $sheet->getHighestDataRow()); $row++) {
            $empId = $this->normalizeEmpId($sheet->getCell("A{$row}")->getFormattedValue());
            $name = trim((string) $sheet->getCell("B{$row}")->getFormattedValue());
            $raw = $this->cellValue($sheet, "C{$row}");
            $amount = null;
            $invalidAmount = false;

            if ($raw !== null && $raw !== '') {
                if (! is_numeric($raw)) {
                    $invalidAmount = true;
                } else {
                    $amount = round((float) $raw, 2);
                }
            }

            if ($empId === '' && $name === '' && $amount === null && ! $invalidAmount) {
                continue;
            }

            $rows[] = [
                'row' => $row,
                'empId' => $empId,
                'name' => $name,
                'amount' => $amount,
                'invalidAmount' => $invalidAmount,
            ];
        }

        return $this->validateRows($rows);
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

            if ($row['invalidAmount']) {
                $errors[] = 'Gross Professional Fees must be numeric.';
            } elseif ($row['amount'] === null) {
                $errors[] = 'Gross Professional Fees is required.';
            } elseif ($row['amount'] < 0) {
                $errors[] = 'Gross Professional Fees cannot be negative.';
            }

            $canonicalName = $employee?->full_name ?: $row['name'];
            $nameMismatch = $employee && $row['name'] !== ''
                && $this->normalize($row['name']) !== $this->normalize($canonicalName);

            return [
                'row' => $row['row'],
                'emp_id' => $row['empId'],
                'employee_name' => $canonicalName,
                'gross_professional_fees' => $row['amount'] ?? 0.0,
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
