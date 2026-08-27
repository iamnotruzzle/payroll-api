<?php

namespace App\Services\Payroll;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DtrMraInputImportService
{
    public function template(Collection $employees): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('DTR-MRA Inputs');
        $sheet->fromArray([[
            'Employee ID',
            'Employee Name',
            'DTR/MRA Deduction Days',
            'Logbook LWOP Days',
        ]], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(24);

        foreach ($employees->values() as $index => $employee) {
            $row = $index + 2;
            $sheet->setCellValueExplicit("A{$row}", (string) $employee['emp_id'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", (string) $employee['label']);
            $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode('0.000');
        }

        $path = tempnam(sys_get_temp_dir(), 'dtr_mra_inputs_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function preview(string $path, Collection $employees): array
    {
        $knownEmployees = $employees->pluck('label', 'emp_id');
        $sheet = IOFactory::load($path)->getSheet(0);
        $headers = [];
        foreach (range(1, Coordinate::columnIndexFromString($sheet->getHighestDataColumn())) as $column) {
            $headers[$this->key((string) $sheet->getCell([$column, 1])->getFormattedValue())] = $column;
        }

        $employeeColumn = $headers['employee_id'] ?? null;
        $deductionColumn = $headers['dtr_mra_deduction_days'] ?? $headers['deduction_days'] ?? null;
        $lwopColumn = $headers['logbook_lwop_days'] ?? $headers['lwop_days'] ?? null;
        if (! $employeeColumn || (! $deductionColumn && ! $lwopColumn)) {
            return [[
                'row' => 1,
                'emp_id' => '',
                'employee_name' => '',
                'deduction_days' => null,
                'logbook_lwop_days' => null,
                'valid' => false,
                'errors' => ['Expected Employee ID and at least one DTR/MRA or Logbook LWOP column.'],
            ]];
        }

        $rows = [];
        $seen = [];
        for ($row = 2; $row <= min(10000, $sheet->getHighestDataRow()); $row++) {
            $empId = trim((string) $sheet->getCell([$employeeColumn, $row])->getFormattedValue());
            $deductionDays = $this->number($sheet, $deductionColumn, $row);
            $logbookLwopDays = $this->number($sheet, $lwopColumn, $row);
            if ($empId === '' && $deductionDays === null && $logbookLwopDays === null) {
                continue;
            }

            $errors = [];
            if ($empId === '' || ! $knownEmployees->has($empId)) {
                $errors[] = 'Employee ID is not part of this payroll scope.';
            }
            if ($empId !== '' && isset($seen[$empId])) {
                $errors[] = 'Employee ID appears more than once in this file.';
            }
            $seen[$empId] = true;
            foreach (['DTR/MRA deduction days' => $deductionDays, 'Logbook LWOP days' => $logbookLwopDays] as $label => $value) {
                if ($value !== null && ($value < 0 || $value > 31)) {
                    $errors[] = "{$label} must be between 0 and 31.";
                }
            }
            if ($deductionDays === null && $logbookLwopDays === null) {
                $errors[] = 'Enter at least one deduction value.';
            }

            $rows[] = [
                'row' => $row,
                'emp_id' => $empId,
                'employee_name' => (string) $knownEmployees->get($empId, ''),
                'deduction_days' => $deductionDays,
                'logbook_lwop_days' => $logbookLwopDays,
                'valid' => $errors === [],
                'errors' => $errors,
            ];
        }

        return $rows;
    }

    private function number($sheet, ?int $column, int $row): ?float
    {
        if (! $column) {
            return null;
        }

        $value = trim((string) $sheet->getCell([$column, $row])->getFormattedValue());
        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? round((float) $value, 3) : -1;
    }

    private function key(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value), '_'));
    }
}
