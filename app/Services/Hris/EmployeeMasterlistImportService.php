<?php

namespace App\Services\Hris;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeEmploymentHistory;
use App\Models\Hris\EmployeeMasterlistImport;
use App\Models\Hris\EmployeeMasterlistImportRow;
use App\Models\Hris\EmployeePayrollProfile;
use App\Models\Hris\Position;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeeMasterlistImportService
{
    public const SHEET = 'Masterlist';

    private const FIELDS = [
        'emp_id' => ['EMPLOYEE NO', 'EMPLOYEE NUMBER', 'EMPLOYEE ID'],
        'position_title' => ['POSITION TITLE'],
        'lastname' => ['LAST NAME'],
        'firstname' => ['FIRST NAME'],
        'middlename' => ['MIDDLE NAME'],
        'extension' => ['EXT', 'EXTENSION'],
        'salary_grade' => ['SG PAYROLL', 'SG-PAYROLL'],
        'step' => ['SI PAYROLL', 'SI-PAYROLL'],
        'division' => ['DIVISION'],
        'department' => ['DEPARTMENT'],
        'responsibility_center' => ['RESPONSIBILITY CENTER'],
        'tin_no' => ['TIN NO', 'TIN NUMBER'],
        'gsis_no' => ['GSIS NO', 'GSIS NUMBER'],
        'phic_no' => ['PHIC NO', 'PHILHEALTH NO'],
        'pagibig_no' => ['HDMF NO', 'PAGIBIG NO'],
        'mp2_account_1' => ['MP2 ACCOUNT 1'],
        'mp2_account_2' => ['MP2 ACCOUNT 2'],
        'mp2_account_3' => ['MP2 ACCOUNT 3'],
        'mp2_account_4' => ['MP2 ACCOUNT 4'],
        'lbp_account_no' => ['LBP ACCOUNT NO'],
        'batch_no' => ['BATCH NO'],
        'batch_year' => ['BATCH YEAR'],
        'fund_type' => ['FUND TYPE'],
        'item_number' => ['ITEM NUMBER'],
        'birthdate' => ['DOB', 'DATE OF BIRTH'],
        'gender' => ['SEX', 'GENDER'],
        'date_hired' => ['DOA', 'DATE OF APPOINTMENT'],
        'effective_from' => ['DOP', 'DATE OF PROMOTION'],
        'eligibility' => ['ELIGIBILITY'],
        'appointment_status' => ['STATUS OF APPT', 'STATUS OF APPOINTMENT'],
        'appointment_nature' => ['NATURE OF APPOINTMENT'],
    ];

    public function stage(string $absolutePath, string $originalName, string $effectiveDate, array $options, ?string $actor): EmployeeMasterlistImport
    {
        $type = IOFactory::identify($absolutePath);
        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(true);
        if (! in_array(self::SHEET, $reader->listWorksheetNames($absolutePath), true)) {
            throw ValidationException::withMessages(['file' => 'The workbook must contain a Masterlist sheet.']);
        }
        $reader->setLoadSheetsOnly([self::SHEET]);
        $book = $reader->load($absolutePath);

        $sheet = $book->getSheetByName(self::SHEET);
        $columns = $this->headerMap($sheet);
        foreach (['emp_id', 'position_title', 'lastname', 'firstname', 'division', 'department', 'birthdate', 'gender', 'appointment_status'] as $required) {
            if (! isset($columns[$required])) {
                throw ValidationException::withMessages(['file' => "Masterlist is missing the required {$required} column."]);
            }
        }

        $storedPath = Storage::disk('local')->putFileAs(
            'employee-masterlist-imports',
            $absolutePath,
            hash_file('sha256', $absolutePath).'-'.basename($originalName)
        );

        $import = EmployeeMasterlistImport::query()->create([
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'file_hash' => hash_file('sha256', $absolutePath),
            'sheet_name' => self::SHEET,
            'status' => 'preview',
            'effective_date' => $effectiveDate,
            'options' => $options,
            'imported_by_emp_id' => $actor,
        ]);

        $positions = Position::query()->whereNotExists(fn ($query) => $query->selectRaw('1')->from('hris_reference_metadata')->whereColumn('reference_id', 'tbl_position.position_id')->where('reference_type', 'position')->where('is_active', false))->get()->keyBy(fn (Position $row) => $this->normalize($row->position_title));
        $departments = Department::query()->whereNotExists(fn ($query) => $query->selectRaw('1')->from('hris_reference_metadata')->whereColumn('reference_id', 'tbl_department.department_id')->where('reference_type', 'department')->where('is_active', false))->with('division')->get()->keyBy(
            fn (Department $row) => $this->normalize($row->division?->division).'|'.$this->normalize($row->department)
        );
        $seen = [];
        $payloads = [];
        $lastRow = min((int) $sheet->getHighestDataRow(), 10000);

        for ($number = 2; $number <= $lastRow; $number++) {
            $payload = $this->readRow($sheet, $columns, $number);
            if (collect($payload)->every(fn ($value) => $value === null || $value === '')) {
                continue;
            }
            $payload['emp_id'] = app(EmployeeProfileWriteService::class)->normalizeEmpId((string) ($payload['emp_id'] ?? ''));
            $payloads[] = ['row' => $number, 'payload' => $payload];
        }

        $employees = Employee::query()
            ->with(['position', 'department.division', 'employmentStatus'])
            ->whereIn('emp_id', collect($payloads)->pluck('payload.emp_id')->filter()->unique())
            ->get()->keyBy(fn (Employee $row) => (string) $row->emp_id);
        $histories = EmployeeEmploymentHistory::query()
            ->whereIn('emp_id', $employees->keys())
            ->whereNull('effective_to')->get()->keyBy('emp_id');

        $staged = [];
        foreach ($payloads as $source) {
            $payload = $source['payload'];
            $employee = $employees->get($payload['emp_id']);
            $history = $employee ? $histories->get($employee->emp_id) : null;
            $errors = [];
            $warnings = [];

            if ($payload['emp_id'] === '') {
                $errors[] = 'Employee ID is required.';
            } elseif (isset($seen[$payload['emp_id']])) {
                $errors[] = 'Duplicate Employee ID in workbook.';
            }
            $seen[$payload['emp_id']] = true;

            $position = $positions->get($this->normalize($payload['position_title']));
            $department = $departments->get($this->normalize($payload['division']).'|'.$this->normalize($payload['department']));
            $statusId = $this->employmentStatusId($payload['appointment_status']);
            if (! $position && (($options['employment'] ?? true) || ! $employee)) {
                $errors[] = 'Position title is not mapped.';
            }
            if (! $department && (($options['employment'] ?? true) || ! $employee)) {
                $errors[] = 'Division and department are not mapped.';
            }
            if (! $statusId && (($options['employment'] ?? true) || ! $employee)) {
                $errors[] = 'Appointment status must be P or T.';
            }
            if ($payload['firstname'] === '' || $payload['lastname'] === '') {
                $errors[] = 'First name and last name are required.';
            }
            if (! $this->validDate($payload['birthdate'])) {
                $errors[] = 'Date of birth is invalid.';
            }
            if ($payload['date_hired'] !== '' && ! $this->validDate($payload['date_hired'])) {
                $errors[] = 'Date of appointment is invalid.';
            }
            if ($payload['effective_from'] !== '' && ! $this->validDate($payload['effective_from'])) {
                $errors[] = 'Date of promotion is invalid.';
            }
            if ($this->validDate($payload['birthdate']) && $this->validDate($payload['date_hired']) && $payload['birthdate'] >= $payload['date_hired']) {
                $errors[] = 'Date of birth must be before date of appointment.';
            }
            if ($payload['step'] !== '' && (! ctype_digit((string) $payload['step']) || (int) $payload['step'] < 1 || (int) $payload['step'] > 8)) {
                $errors[] = 'Step must be from 1 to 8.';
            }
            if ($payload['salary_grade'] !== '' && (! ctype_digit((string) $payload['salary_grade']) || (int) $payload['salary_grade'] < 1 || (int) $payload['salary_grade'] > 33)) {
                $errors[] = 'Salary grade must be from 1 to 33.';
            }
            if (! $employee && ! ($options['create_new'] ?? false)) {
                $warnings[] = 'New employee; creation is not enabled.';
            }

            $changes = $this->changes($payload, $employee, $history, $position?->position_id, $department?->department_id, $statusId);
            if ($employee) {
                $enabledGroups = collect([
                    ($options['identity'] ?? true) ? 'identity' : null,
                    ($options['employment'] ?? true) ? 'employment' : null,
                    ($options['government_ids'] ?? false) ? 'government_ids' : null,
                ])->filter()->all();
                $changes = array_values(array_filter($changes, fn ($change) => in_array($change['group'], $enabledGroups, true)));
            }
            if ($employee && (
                $this->normalize($employee->firstname) !== $this->normalize($payload['firstname'])
                || $this->normalize($employee->lastname) !== $this->normalize($payload['lastname'])
            )) {
                $warnings[] = 'Employee name differs from HRIS.';
            }

            $action = ! $employee ? 'new' : ($changes === [] ? 'unchanged' : 'update');
            $staged[] = [
                'import_id' => $import->id,
                'source_row' => $source['row'],
                'emp_id' => $payload['emp_id'] ?: null,
                'action' => $action,
                'status' => 'pending',
                'selected' => $action !== 'unchanged' && ($employee || ($options['create_new'] ?? false)),
                'source_payload' => json_encode($payload),
                'changes' => json_encode($changes),
                'warnings' => json_encode($warnings),
                'errors' => json_encode($errors),
                'resolved_position_id' => $position?->position_id,
                'resolved_department_id' => $department?->department_id,
                'resolved_empstat_id' => $statusId,
                'preview_employee_updated_at' => $employee?->updated_at?->format('Y-m-d H:i:s'),
                'row_hash' => hash('sha256', json_encode($payload)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($staged, 250) as $chunk) {
            EmployeeMasterlistImportRow::query()->insert($chunk);
        }
        $this->refreshCounts($import);
        $book->disconnectWorksheets();

        return $import->fresh();
    }

    public function apply(EmployeeMasterlistImport $import, ?string $actor): EmployeeMasterlistImport
    {
        if ($import->status === 'completed') {
            throw ValidationException::withMessages(['import' => 'This import was already applied.']);
        }

        $import->update(['status' => 'processing']);
        $options = $import->options ?? [];

        $import->rows()->where('selected', true)->where('status', 'pending')->orderBy('id')->chunkById(100, function ($rows) use ($import, $options, $actor) {
            foreach ($rows as $row) {
                if (($row->errors ?? []) !== []) {
                    $row->update(['status' => 'skipped', 'failure_message' => 'Resolve row errors before applying.']);

                    continue;
                }

                try {
                    DB::connection('hris')->transaction(function () use ($row, $import, $options, $actor) {
                        $payload = $row->source_payload;
                        $employee = Employee::query()->where('emp_id', $row->emp_id)->lockForUpdate()->first();
                        if ($employee && $row->preview_employee_updated_at && $employee->updated_at?->format('Y-m-d H:i:s') !== $row->preview_employee_updated_at) {
                            throw ValidationException::withMessages(['row' => 'Employee changed after preview. Preview the workbook again.']);
                        }

                        if (! $employee) {
                            if (! ($options['create_new'] ?? false)) {
                                throw ValidationException::withMessages(['row' => 'New employee creation is disabled.']);
                            }
                            $employee = $this->createEmployee($row, $payload, $import, $actor);
                        } else {
                            $this->updateEmployee($employee, $row, $payload, $import, $options, $actor);
                        }

                        if ($options['payroll_profile'] ?? false) {
                            $this->updatePayrollProfile($employee->emp_id, $payload);
                        }
                    });
                    $row->update(['status' => 'applied', 'failure_message' => null]);
                } catch (\Throwable $exception) {
                    report($exception);
                    $row->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 1000)]);
                }
            }
        });

        $this->refreshCounts($import);
        $remaining = $import->rows()->where('selected', true)->where('status', 'pending')->exists();
        $import->update(['status' => $remaining ? 'preview' : 'completed', 'applied_at' => $remaining ? null : now()]);

        return $import->fresh();
    }

    public function mapPosition(EmployeeMasterlistImport $import, string $sourceLabel, int $positionId): void
    {
        $key = $this->normalize($sourceLabel);
        $import->rows()->where('status', 'pending')->get()->each(function (EmployeeMasterlistImportRow $row) use ($key, $positionId) {
            if ($this->normalize($row->source_payload['position_title'] ?? '') === $key) {
                $errors = array_values(array_filter($row->errors ?? [], fn ($error) => $error !== 'Position title is not mapped.'));
                $row->update(['resolved_position_id' => $positionId, 'errors' => $errors]);
            }
        });
        $this->refreshCounts($import);
    }

    public function mapDepartment(EmployeeMasterlistImport $import, string $division, string $department, int $departmentId): void
    {
        $key = $this->normalize($division).'|'.$this->normalize($department);
        $import->rows()->where('status', 'pending')->get()->each(function (EmployeeMasterlistImportRow $row) use ($key, $departmentId) {
            $payload = $row->source_payload;
            if ($this->normalize($payload['division'] ?? '').'|'.$this->normalize($payload['department'] ?? '') === $key) {
                $errors = array_values(array_filter($row->errors ?? [], fn ($error) => $error !== 'Division and department are not mapped.'));
                $row->update(['resolved_department_id' => $departmentId, 'errors' => $errors]);
            }
        });
        $this->refreshCounts($import);
    }

    private function createEmployee(EmployeeMasterlistImportRow $row, array $payload, EmployeeMasterlistImport $import, ?string $actor): Employee
    {
        $employee = Employee::query()->create([
            'emp_id' => $row->emp_id,
            'firstname' => $payload['firstname'], 'middlename' => $this->blank($payload['middlename']),
            'lastname' => $payload['lastname'], 'extension' => $this->blank($payload['extension']),
            'position_id' => $row->resolved_position_id, 'department_id' => $row->resolved_department_id,
            'empstat_id' => $row->resolved_empstat_id, 'step' => $payload['step'] ?: null,
            'birthdate' => $payload['birthdate'], 'date_hired' => $payload['date_hired'] ?: $import->effective_date,
            'gender' => $this->gender($payload['gender']), 'tin_no' => $this->blank($payload['tin_no']),
            'gsis_no' => $this->blank($payload['gsis_no']), 'phic_no' => $this->blank($payload['phic_no']),
            'pagibig_no' => $this->blank($payload['pagibig_no']), 'is_active' => 'Y',
        ]);
        app(EmploymentHistoryService::class)->recordChange($employee->emp_id, $this->historyData($row, $payload, $import), $actor);

        return $employee->fresh();
    }

    private function updateEmployee(Employee $employee, EmployeeMasterlistImportRow $row, array $payload, EmployeeMasterlistImport $import, array $options, ?string $actor): void
    {
        if ($options['identity'] ?? true) {
            $this->fillNonBlank($employee, $payload, [
                'firstname' => 'firstname', 'middlename' => 'middlename', 'lastname' => 'lastname',
                'extension' => 'extension', 'birthdate' => 'birthdate', 'gender' => 'gender', 'date_hired' => 'date_hired',
            ]);
        }
        if ($options['government_ids'] ?? false) {
            $this->fillNonBlank($employee, $payload, [
                'tin_no' => 'tin_no', 'gsis_no' => 'gsis_no', 'phic_no' => 'phic_no', 'pagibig_no' => 'pagibig_no',
            ]);
        }
        $employee->save();

        if (($options['employment'] ?? true) && $this->hasEmploymentChange($employee, $row, $payload)) {
            app(EmploymentHistoryService::class)->recordChange($employee->emp_id, $this->historyData($row, $payload, $import), $actor);
        }
    }

    private function historyData(EmployeeMasterlistImportRow $row, array $payload, EmployeeMasterlistImport $import): array
    {
        return [
            'effective_from' => $payload['effective_from'] ?: $import->effective_date->toDateString(),
            'item_number' => $payload['item_number'], 'position_id' => $row->resolved_position_id,
            'department_id' => $row->resolved_department_id, 'empstat_id' => $row->resolved_empstat_id,
            'step' => $payload['step'] ?: null, 'salary_grade' => $payload['salary_grade'] ?: null,
            'nature' => $this->nature($payload['appointment_nature']),
            'remarks' => 'Imported from '.$import->original_name.' row '.$row->source_row.'. Original nature: '.($payload['appointment_nature'] ?: 'not supplied'),
        ];
    }

    private function updatePayrollProfile(string $empId, array $payload): void
    {
        $attributes = [];
        foreach (['responsibility_center', 'mp2_account_1', 'mp2_account_2', 'mp2_account_3', 'mp2_account_4', 'lbp_account_no', 'batch_no', 'batch_year', 'fund_type'] as $field) {
            if (($payload[$field] ?? '') !== '') {
                $attributes[$field] = $payload[$field];
            }
        }
        if ($attributes !== []) {
            EmployeePayrollProfile::query()->updateOrCreate(['emp_id' => $empId], $attributes);
        }
    }

    private function fillNonBlank(Employee $employee, array $payload, array $map): void
    {
        foreach ($map as $source => $target) {
            if (($payload[$source] ?? '') !== '') {
                $employee->{$target} = $target === 'gender' ? $this->gender($payload[$source]) : $payload[$source];
            }
        }
    }

    private function hasEmploymentChange(Employee $employee, EmployeeMasterlistImportRow $row, array $payload): bool
    {
        return (int) $employee->position_id !== (int) $row->resolved_position_id
            || (int) $employee->department_id !== (int) $row->resolved_department_id
            || (int) $employee->empstat_id !== (int) $row->resolved_empstat_id
            || (int) $employee->step !== (int) ($payload['step'] ?: 0)
            || ($row->changes && collect($row->changes)->contains(fn ($change) => in_array($change['field'], ['salary_grade', 'item_number'], true)));
    }

    private function changes(array $payload, ?Employee $employee, ?EmployeeEmploymentHistory $history, ?int $positionId, ?int $departmentId, ?int $statusId): array
    {
        if (! $employee) {
            return [['field' => 'employee', 'group' => 'identity', 'current' => null, 'incoming' => 'New employee']];
        }
        $pairs = [
            'firstname' => ['identity', $employee->firstname, $payload['firstname']],
            'middlename' => ['identity', $employee->middlename, $payload['middlename']],
            'lastname' => ['identity', $employee->lastname, $payload['lastname']],
            'extension' => ['identity', $employee->extension, $payload['extension']],
            'birthdate' => ['identity', optional($employee->birthdate)->format('Y-m-d'), $payload['birthdate']],
            'gender' => ['identity', $this->gender($employee->gender), $this->gender($payload['gender'])],
            'date_hired' => ['identity', optional($employee->date_hired)->format('Y-m-d'), $payload['date_hired']],
            'tin_no' => ['government_ids', $employee->tin_no, $payload['tin_no']],
            'gsis_no' => ['government_ids', $employee->gsis_no, $payload['gsis_no']],
            'phic_no' => ['government_ids', $employee->phic_no, $payload['phic_no']],
            'pagibig_no' => ['government_ids', $employee->pagibig_no, $payload['pagibig_no']],
            'position' => ['employment', $employee->position_id, $positionId],
            'department' => ['employment', $employee->department_id, $departmentId],
            'appointment_status' => ['employment', $employee->empstat_id, $statusId],
            'step' => ['employment', $employee->step, $payload['step']],
            'salary_grade' => ['employment', $history?->salary_grade, $payload['salary_grade']],
            'item_number' => ['employment', $history?->item_number, $payload['item_number']],
        ];
        $changes = [];
        foreach ($pairs as $field => [$group, $current, $incoming]) {
            if ($incoming !== '' && $incoming !== null && $this->comparable($current) !== $this->comparable($incoming)) {
                $changes[] = compact('field', 'group', 'current', 'incoming');
            }
        }

        return $changes;
    }

    private function headerMap($sheet): array
    {
        $available = [];
        foreach (range(1, Coordinate::columnIndexFromString($sheet->getHighestDataColumn())) as $column) {
            $available[$this->normalize($sheet->getCell([$column, 1])->getFormattedValue())] = $column;
        }
        $map = [];
        foreach (self::FIELDS as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($available[$this->normalize($alias)])) {
                    $map[$field] = $available[$this->normalize($alias)];
                    break;
                }
            }
        }

        return $map;
    }

    private function readRow($sheet, array $columns, int $row): array
    {
        $data = [];
        foreach (self::FIELDS as $field => $_aliases) {
            $cell = isset($columns[$field]) ? $sheet->getCell([$columns[$field], $row]) : null;
            $value = $cell ? trim((string) $cell->getFormattedValue()) : '';
            $data[$field] = in_array($field, ['birthdate', 'date_hired', 'effective_from'], true)
                ? $this->dateValue($cell?->getValue(), $value)
                : $value;
        }

        return $data;
    }

    private function dateValue(mixed $raw, string $formatted): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        try {
            if (is_numeric($raw)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();
            }

            return CarbonImmutable::parse($formatted)->toDateString();
        } catch (\Throwable) {
            return $formatted;
        }
    }

    private function refreshCounts(EmployeeMasterlistImport $import): void
    {
        $query = $import->rows();
        $import->update([
            'total_rows' => (clone $query)->count(),
            'new_rows' => (clone $query)->where('action', 'new')->count(),
            'changed_rows' => (clone $query)->where('action', 'update')->count(),
            'unchanged_rows' => (clone $query)->where('action', 'unchanged')->count(),
            'warning_rows' => (clone $query)->get(['warnings'])->filter(fn ($row) => ($row->warnings ?? []) !== [])->count(),
            'error_rows' => (clone $query)->get(['errors'])->filter(fn ($row) => ($row->errors ?? []) !== [])->count(),
            'applied_rows' => (clone $query)->where('status', 'applied')->count(),
            'failed_rows' => (clone $query)->where('status', 'failed')->count(),
        ]);
    }

    private function employmentStatusId(mixed $value): ?int
    {
        return match (strtoupper(trim((string) $value))) {
            'P', 'PERMANENT' => Employee::EMPSTAT_PERMANENT,
            'T', 'TEMPORARY' => Employee::EMPSTAT_TEMPORARY,
            default => null,
        };
    }

    private function nature(mixed $value): string
    {
        $normalized = $this->normalize($value);

        return match ($normalized) {
            'original', 'orginal', 'orignal' => EmploymentHistoryService::NATURE_ORIGINAL,
            'transfer' => EmploymentHistoryService::NATURE_TRANSFER,
            'reappointment' => EmploymentHistoryService::NATURE_REAPPOINTMENT,
            'promotion' => EmploymentHistoryService::NATURE_PROMOTION,
            default => EmploymentHistoryService::NATURE_OTHER,
        };
    }

    private function gender(mixed $value): string
    {
        return match (strtoupper(trim((string) $value))) {
            'F', 'FEMALE' => 'F', 'M', 'MALE' => 'M', default => trim((string) $value),
        };
    }

    private function normalize(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string) $value))) ?? '';
    }

    private function comparable(mixed $value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }

    private function validDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $value);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1
                && $date->format('Y-m-d') === (string) $value;
        } catch (\Throwable) {
            return false;
        }
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
