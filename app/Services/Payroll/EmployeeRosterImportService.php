<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmployeeRosterImportService
{
    public function template(string $title): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Employee Roster');
        $sheet->fromArray([['Employee ID', 'Employee Name']], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:B201');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(48);

        $employees = Employee::query()
            ->where('is_active', 'Y')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix']);

        $reference = $book->createSheet();
        $reference->setTitle('Employee Records');
        $reference->fromArray([['Employee ID', 'Employee Name']], null, 'A1');
        foreach ($employees->values() as $index => $employee) {
            $reference->fromArray([[(string) $employee->emp_id, $employee->full_name]], null, 'A'.($index + 2));
        }
        $reference->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        for ($row = 2; $row <= 201; $row++) {
            $validation = $sheet->getCell("A{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setAllowBlank(true)
                ->setShowDropDown(false)
                ->setFormula1("'Employee Records'!\$A\$2:\$A\$".max(2, $employees->count() + 1));
            $sheet->setCellValue("B{$row}", '=IFERROR(VLOOKUP(A'.$row.',\'Employee Records\'!$A:$B,2,FALSE),"")');
        }

        $sheet->getComment('A1')->getText()->createTextRun($title.'. Employee ID is authoritative.');
        $path = tempnam(sys_get_temp_dir(), 'employee_roster_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function preview(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $lastRow = min($sheet->getHighestDataRow(), 5000);
        $rawRows = [];

        for ($row = 2; $row <= $lastRow; $row++) {
            $empId = trim((string) $sheet->getCell("A{$row}")->getFormattedValue());
            $name = trim((string) $sheet->getCell("B{$row}")->getFormattedValue());
            if ($empId !== '' || $name !== '') {
                $rawRows[] = ['row' => $row, 'emp_id' => $empId, 'employee_name' => $name];
            }
        }

        $employees = Employee::query()
            ->whereIn('emp_id', collect($rawRows)->pluck('emp_id')->filter()->unique())
            ->get()
            ->keyBy(fn (Employee $employee) => (string) $employee->emp_id);
        $seen = [];

        return collect($rawRows)->map(function (array $row) use ($employees, &$seen) {
            $employee = $employees->get($row['emp_id']);
            $errors = [];
            if ($row['emp_id'] === '') {
                $errors[] = 'Employee ID is required.';
            } elseif (isset($seen[$row['emp_id']])) {
                $errors[] = 'Duplicate employee ID.';
            } elseif (! $employee) {
                $errors[] = 'Employee ID was not found in HRIS.';
            }
            $seen[$row['emp_id']] = true;

            $canonicalName = $employee?->full_name;
            $nameMismatch = $employee && $row['employee_name'] !== ''
                && $this->normalize($row['employee_name']) !== $this->normalize($canonicalName);

            return [
                ...$row,
                'employee_name' => $canonicalName ?: $row['employee_name'],
                'name_mismatch' => $nameMismatch,
                'valid' => $errors === [],
                'errors' => $errors,
            ];
        })->values()->all();
    }

    private function normalize(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value)) ?? '';
    }
}
