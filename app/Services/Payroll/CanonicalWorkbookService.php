<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollSourceBatch;
use App\Models\Payroll\PayrollUserAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CanonicalWorkbookService
{
    public const SHEETS = ['Divisions', 'Departments', 'Positions', 'Employees', 'Salary Rates', 'Leave Types', 'Leaves', 'Timekeeping', 'Accounts'];

    public function template(?string $only = null): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $headers = $this->headers();
        foreach ($headers as $sheet => $columns) {
            if ($only && strcasecmp($only, $sheet) !== 0) {
                continue;
            } $tab = $book->createSheet();
            $tab->setTitle($sheet);
            $tab->fromArray([$columns], null, 'A1');
            $tab->freezePane('A2');
            $tab->getStyle('A1:'.$tab->getHighestColumn().'1')->getFont()->setBold(true);
        }
        if ($book->getSheetCount() === 0) {
            throw ValidationException::withMessages(['kind' => 'Unknown workbook kind.']);
        }
        $book->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'payroll_canonical_').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function stage(string $path, string $filename, ?string $period, ?string $by, ?string $only = null): PayrollSourceBatch
    {
        $checksum = hash_file('sha256', $path);
        $book = IOFactory::load($path);
        $payload = [];
        $errors = [];
        $stats = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $name = $sheet->getTitle();
            if ($only !== null) {
                if (! in_array($only, self::SHEETS, true)) {
                    throw ValidationException::withMessages(['file' => 'Unknown canonical import type.']);
                }
                if ($book->getSheetCount() === 1) {
                    $name = $only;
                } elseif (strcasecmp($name, $only) !== 0) {
                    continue;
                }
            }
            if (! in_array($name, self::SHEETS, true)) {
                continue;
            } $rows = $sheet->toArray(null, true, true, true);
            if (! $rows) {
                continue;
            } $header = array_shift($rows);
            $keys = array_map(fn ($v) => $this->key((string) $v), array_values($header));
            $parsed = [];
            foreach ($rows as $number => $row) {
                $values = array_values($row);
                if (collect($values)->every(fn ($v) => $v === null || trim((string) $v) === '')) {
                    continue;
                } $item = array_combine($keys, array_pad($values, count($keys), null));
                $item['_row'] = $number;
                $parsed[] = $item;
            }
            $payload[$name] = $parsed;
            $stats[$name] = count($parsed);
        }
        if ($payload === []) {
            $errors[] = 'No recognized sheets were found. Expected: '.implode(', ', self::SHEETS).'.';
        }
        $errors = [...$errors, ...$this->validatePayload($payload, $period)];
        try {
            return PayrollSourceBatch::query()->create(['kind' => count($payload) > 1 ? 'consolidated' : strtolower(str_replace(' ', '_', array_key_first($payload) ?? 'unknown')), 'source' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'csv' ? 'csv' : 'workbook', 'status' => $errors === [] ? 'validated' : 'invalid', 'schema_version' => config('payroll_standalone.workbook_version'), 'original_filename' => $filename, 'checksum' => $checksum, 'effective_period' => $period, 'statistics' => $stats, 'errors' => $errors, 'payload' => $payload, 'created_by' => $by]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'This workbook has already been staged for the selected period.']);
        }
    }

    public function activate(PayrollSourceBatch $batch, ?string $by): void
    {
        if ($batch->status !== 'validated') {
            throw ValidationException::withMessages(['batch' => 'Only a validated batch can be activated.']);
        }
        DB::connection('payroll')->transaction(function () use ($batch, $by) {
            $p = $batch->payload ?? [];
            $this->upsert($p['Divisions'] ?? [], 'payroll_canonical_divisions', ['external_id'], fn ($r) => ['external_id' => $r['division_id'], 'name' => $r['name'], 'is_active' => $this->bool($r['is_active'] ?? true)] + $this->meta($batch));
            $this->upsert($p['Departments'] ?? [], 'payroll_canonical_departments', ['external_id'], fn ($r) => ['external_id' => $r['department_id'], 'division_external_id' => $r['division_id'], 'name' => $r['name'], 'is_active' => $this->bool($r['is_active'] ?? true)] + $this->meta($batch));
            $this->upsert($p['Positions'] ?? [], 'payroll_canonical_positions', ['external_id'], fn ($r) => ['external_id' => $r['position_id'], 'title' => $r['title'], 'salary_grade' => $r['salary_grade'] ?: null, 'remarks' => $r['remarks'] ?: null, 'is_active' => $this->bool($r['is_active'] ?? true)] + $this->meta($batch));
            $this->upsert($p['Employees'] ?? [], 'payroll_canonical_employees', ['emp_id'], fn ($r) => collect(['emp_id' => $r['employee_id'], 'firstname' => $r['first_name'], 'middlename' => $r['middle_name'] ?: null, 'lastname' => $r['last_name'], 'extension' => $r['extension'] ?: null, 'suffix' => $r['suffix'] ?: null, 'position_external_id' => $r['position_id'] ?: null, 'department_external_id' => $r['department_id'] ?: null, 'step' => $r['step'] ?: null, 'empstat_id' => $r['employment_status_id'] ?: 1, 'date_hired' => $this->date($r['date_hired'] ?? null), 'tin_no' => $r['tin'] ?: null, 'gsis_no' => $r['gsis'] ?: null, 'phic_no' => $r['philhealth'] ?: null, 'pagibig_no' => $r['pagibig'] ?: null, 'vacation_leave_credits' => $r['vl_balance'] ?: 0, 'sick_leave_credits' => $r['sl_balance'] ?: 0, 'is_external' => $this->bool($r['is_external'] ?? false), 'is_active' => $this->bool($r['is_active'] ?? true), 'responsibility_center' => $r['responsibility_center'] ?: null, 'lbp_account_no' => $r['bank_account'] ?: null, 'fund_type' => $r['fund_type'] ?: null] + $this->meta($batch))->all());
            $this->upsert($p['Salary Rates'] ?? [], 'payroll_canonical_salary_rates', ['salary_grade', 'step', 'effective_from'], fn ($r) => ['salary_grade' => $r['salary_grade'], 'step' => $r['step'], 'salary' => $r['monthly_salary'], 'effective_from' => $this->date($r['effective_from']), 'effective_to' => $this->date($r['effective_to'] ?? null)] + $this->meta($batch));
            $this->upsert($p['Leave Types'] ?? [], 'payroll_canonical_leave_types', ['external_id'], fn ($r) => ['external_id' => $r['leave_type_id'], 'name' => $r['name'], 'is_active' => $this->bool($r['is_active'] ?? true)] + $this->meta($batch));
            $this->upsert($p['Leaves'] ?? [], 'payroll_canonical_leaves', ['external_id'], fn ($r) => ['external_id' => $r['leave_id'], 'emp_id' => $r['employee_id'], 'leave_type_external_id' => $r['leave_type_id'], 'start_date' => $this->date($r['start_date']), 'end_date' => $this->date($r['end_date']), 'days_wpay' => $r['days_with_pay'] ?: 0, 'days_wopay' => $r['days_without_pay'] ?: 0, 'is_cancelled' => $this->bool($r['cancelled'] ?? false)] + $this->meta($batch));
            $this->upsert($p['Timekeeping'] ?? [], 'payroll_canonical_timekeeping', ['period', 'emp_id'], fn ($r) => ['period' => $r['period'], 'emp_id' => $r['employee_id'], 'total_work_days' => $r['work_days'] ?: 0, 'days_with_dtr' => $r['days_with_dtr'] ?: 0, 'regular_hours' => $r['regular_hours'] ?: 0, 'undertime_hours' => $r['undertime_hours'] ?: 0, 'tardy_hours' => $r['tardy_hours'] ?: 0, 'mra_hours' => $r['mra_hours'] ?: 0, 'leave_days_with_pay' => $r['leave_days_with_pay'] ?: 0, 'leave_days_without_pay' => $r['leave_days_without_pay'] ?: 0, 'absent_days' => $r['absent_days'] ?: 0] + $this->meta($batch));
            $this->upsert($p['Accounts'] ?? [], 'payroll_user_accounts', ['emp_id'], fn ($r) => ['source_batch_id' => $batch->id, 'emp_id' => $r['employee_id'], 'username' => $r['username'] ?: null, 'password' => $r['password_hash'], 'login_attempt' => 1, 'is_active' => $this->bool($r['is_active'] ?? true), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($p['Accounts'] ?? [] as $account) {
                $roles = collect(explode(',', (string) ($account['roles'] ?? '')))->map(fn ($role) => trim($role))->filter()->values()->all();
                if ($roles !== []) {
                    PayrollUserAccount::query()->where('emp_id', $account['employee_id'])->firstOrFail()->syncRoles($roles);
                }
            }
            PayrollSourceBatch::query()->where('kind', $batch->kind)->where('status', 'active')->update(['status' => 'superseded']);
            $batch->update(['status' => 'active', 'activated_by' => $by, 'activated_at' => now()]);
        });
    }

    public function rollback(PayrollSourceBatch $batch): void
    {
        if ($batch->status !== 'active') {
            throw ValidationException::withMessages(['batch' => 'Only an active batch can be rolled back.']);
        }
        $previous = PayrollSourceBatch::query()
            ->where('kind', $batch->kind)
            ->where('status', 'superseded')
            ->latest('activated_at')
            ->first();

        DB::connection('payroll')->transaction(function () use ($batch) {
            foreach (['payroll_canonical_divisions', 'payroll_canonical_departments', 'payroll_canonical_positions', 'payroll_canonical_employees', 'payroll_canonical_salary_rates', 'payroll_canonical_leave_types', 'payroll_canonical_leaves', 'payroll_canonical_timekeeping'] as $table) {
                DB::connection('payroll')->table($table)->where('source_batch_id', $batch->id)->delete();
            }
            DB::connection('payroll')->table('payroll_user_accounts')->where('source_batch_id', $batch->id)->delete();
            $batch->update(['status' => 'rolled_back', 'rolled_back_at' => now()]);
        });

        if ($previous) {
            $previous->update(['status' => 'validated']);
            $this->activate($previous, 'system:rollback');
        }
    }

    private function validatePayload(array $payload, ?string $period): array
    {
        $errors = [];
        foreach ($payload as $sheet => $rows) {
            $required = $this->required()[$sheet] ?? [];
            $seen = [];
            foreach ($rows as $row) {
                foreach ($required as $key) {
                    if (! isset($row[$key]) || trim((string) $row[$key]) === '') {
                        $errors[] = "{$sheet} row {$row['_row']}: {$key} is required.";
                    }
                } $identity = match ($sheet) {
                    'Employees','Accounts' => $row['employee_id'] ?? null,'Timekeeping' => ($row['period'] ?? $period).'|'.($row['employee_id'] ?? ''),'Salary Rates' => ($row['salary_grade'] ?? '').'|'.($row['step'] ?? '').'|'.($row['effective_from'] ?? ''),default => $row[array_key_first($row)] ?? null
                };
                if ($identity !== null && isset($seen[$identity])) {
                    $errors[] = "{$sheet}: duplicate key {$identity}.";
                } $seen[$identity] = true;
            }
        }

        $ids = function (string $sheet, string $key, string $table, string $column) use ($payload) {
            $values = collect($payload[$sheet] ?? [])->pluck($key)->filter();
            if (DB::connection('payroll')->getSchemaBuilder()->hasTable($table)) {
                $values = $values->merge(DB::connection('payroll')->table($table)->pluck($column));
            }

            return $values->map(fn ($value) => (string) $value)->flip();
        };
        $divisionIds = $ids('Divisions', 'division_id', 'payroll_canonical_divisions', 'external_id');
        $departmentIds = $ids('Departments', 'department_id', 'payroll_canonical_departments', 'external_id');
        $positionIds = $ids('Positions', 'position_id', 'payroll_canonical_positions', 'external_id');
        $employeeIds = $ids('Employees', 'employee_id', 'payroll_canonical_employees', 'emp_id');
        $leaveTypeIds = $ids('Leave Types', 'leave_type_id', 'payroll_canonical_leave_types', 'external_id');

        foreach ($payload['Departments'] ?? [] as $row) {
            if (! $divisionIds->has((string) ($row['division_id'] ?? ''))) {
                $errors[] = "Departments row {$row['_row']}: unknown division_id.";
            }
        }
        foreach ($payload['Employees'] ?? [] as $row) {
            if (! empty($row['department_id']) && ! $departmentIds->has((string) $row['department_id'])) {
                $errors[] = "Employees row {$row['_row']}: unknown department_id.";
            }
            if (! empty($row['position_id']) && ! $positionIds->has((string) $row['position_id'])) {
                $errors[] = "Employees row {$row['_row']}: unknown position_id.";
            }
            if (! $this->bool($row['is_active'] ?? true)
                && DB::connection('payroll')->getSchemaBuilder()->hasTable('payroll_canonical_employees')
                && DB::connection('payroll')->table('payroll_canonical_employees')->where('emp_id', $row['employee_id'])->where('is_active', true)->exists()
                && ! $this->bool($row['confirm_deactivation'] ?? false)) {
                $errors[] = "Employees row {$row['_row']}: set Confirm Deactivation to Yes before deactivating an active employee.";
            }
        }
        foreach ($payload['Leaves'] ?? [] as $row) {
            if (! $employeeIds->has((string) ($row['employee_id'] ?? ''))) {
                $errors[] = "Leaves row {$row['_row']}: unknown employee_id.";
            }
            if (! $leaveTypeIds->has((string) ($row['leave_type_id'] ?? ''))) {
                $errors[] = "Leaves row {$row['_row']}: unknown leave_type_id.";
            }
            if (strtotime((string) ($row['end_date'] ?? '')) < strtotime((string) ($row['start_date'] ?? ''))) {
                $errors[] = "Leaves row {$row['_row']}: end_date precedes start_date.";
            }
        }
        foreach ($payload['Timekeeping'] ?? [] as $row) {
            if (! $employeeIds->has((string) ($row['employee_id'] ?? ''))) {
                $errors[] = "Timekeeping row {$row['_row']}: unknown employee_id.";
            }
            if ($period && ($row['period'] ?? null) !== $period) {
                $errors[] = "Timekeeping row {$row['_row']}: period differs from the selected import period.";
            }
            foreach (['work_days', 'days_with_dtr', 'regular_hours', 'undertime_hours', 'tardy_hours', 'mra_hours', 'leave_days_with_pay', 'leave_days_without_pay', 'absent_days'] as $field) {
                if (isset($row[$field]) && (! is_numeric($row[$field]) || (float) $row[$field] < 0)) {
                    $errors[] = "Timekeeping row {$row['_row']}: {$field} must be non-negative.";
                }
            }
        }
        $knownRoles = DB::connection('payroll')->getSchemaBuilder()->hasTable('roles') ? DB::connection('payroll')->table('roles')->pluck('name')->flip() : collect();
        foreach ($payload['Accounts'] ?? [] as $row) {
            if (! $employeeIds->has((string) ($row['employee_id'] ?? ''))) {
                $errors[] = "Accounts row {$row['_row']}: unknown employee_id.";
            }
            foreach (collect(explode(',', (string) ($row['roles'] ?? '')))->map(fn ($role) => trim($role))->filter() as $role) {
                if (! $knownRoles->has($role)) {
                    $errors[] = "Accounts row {$row['_row']}: unknown role {$role}.";
                }
            }
        }
        $rateGroups = collect($payload['Salary Rates'] ?? [])->groupBy(fn ($row) => ($row['salary_grade'] ?? '').'|'.($row['step'] ?? ''));
        foreach ($rateGroups as $rates) {
            $sorted = $rates->sortBy('effective_from')->values();
            for ($index = 1; $index < $sorted->count(); $index++) {
                $previousEnd = $sorted[$index - 1]['effective_to'] ?? null;
                if (! $previousEnd || strtotime((string) $previousEnd) >= strtotime((string) $sorted[$index]['effective_from'])) {
                    $errors[] = 'Salary Rates contain overlapping effective ranges for grade '.$sorted[$index]['salary_grade'].' step '.$sorted[$index]['step'].'.';
                }
            }
        }
        foreach (collect($payload['Salary Rates'] ?? [])->groupBy('salary_grade') as $grade => $rates) {
            $steps = $rates->pluck('step')->map(fn ($step) => (int) $step)->filter()->unique()->sort()->values();
            if ($steps->isNotEmpty() && $steps->all() !== range(1, $steps->max())) {
                $errors[] = "Salary Rates have a step gap for grade {$grade}.";
            }
        }

        return array_values(array_unique(array_slice($errors, 0, 200)));
    }

    private function upsert(array $rows, string $table, array $unique, callable $map): void
    {
        if (! $rows) {
            return;
        }

        $updateColumns = array_values(array_diff(array_keys($map($rows[0])), $unique));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::connection('payroll')
                ->table($table)
                ->upsert(array_map($map, $chunk), $unique, $updateColumns);
        }
    }

    private function meta(PayrollSourceBatch $b): array
    {
        return ['source_batch_id' => $b->id, 'created_at' => now(), 'updated_at' => now()];
    }

    private function bool($v): bool
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'y', 'yes', 'true', 'active'], true);
    }

    private function date($v): ?string
    {
        if (! $v) {
            return null;
        }

        if (is_numeric($v)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($v)->format('Y-m-d');
        }

        $value = trim((string) $v);
        if ($value === '' || preg_match('/^0{4}-0{2}-0{2}/', $value)) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $date = date('Y-m-d', $timestamp);

        return (int) substr($date, 0, 4) >= 1000 ? $date : null;
    }

    private function key(string $v): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $v), '_'));
    }

    private function headers(): array
    {
        return ['Divisions' => ['Division ID', 'Name', 'Is Active'], 'Departments' => ['Department ID', 'Division ID', 'Name', 'Is Active'], 'Positions' => ['Position ID', 'Title', 'Salary Grade', 'Remarks', 'Is Active'], 'Employees' => ['Employee ID', 'First Name', 'Middle Name', 'Last Name', 'Extension', 'Suffix', 'Position ID', 'Department ID', 'Step', 'Employment Status ID', 'Date Hired', 'TIN', 'GSIS', 'PhilHealth', 'Pagibig', 'VL Balance', 'SL Balance', 'Is External', 'Is Active', 'Responsibility Center', 'Bank Account', 'Fund Type', 'Confirm Deactivation'], 'Salary Rates' => ['Salary Grade', 'Step', 'Monthly Salary', 'Effective From', 'Effective To'], 'Leave Types' => ['Leave Type ID', 'Name', 'Is Active'], 'Leaves' => ['Leave ID', 'Employee ID', 'Leave Type ID', 'Start Date', 'End Date', 'Days With Pay', 'Days Without Pay', 'Cancelled'], 'Timekeeping' => ['Period', 'Employee ID', 'Work Days', 'Days With DTR', 'Regular Hours', 'Undertime Hours', 'Tardy Hours', 'MRA Hours', 'Leave Days With Pay', 'Leave Days Without Pay', 'Absent Days'], 'Accounts' => ['Employee ID', 'Username', 'Password Hash', 'Is Active', 'Roles']];
    }

    private function required(): array
    {
        return ['Divisions' => ['division_id', 'name'], 'Departments' => ['department_id', 'division_id', 'name'], 'Positions' => ['position_id', 'title'], 'Employees' => ['employee_id', 'first_name', 'last_name'], 'Salary Rates' => ['salary_grade', 'step', 'monthly_salary', 'effective_from'], 'Leave Types' => ['leave_type_id', 'name'], 'Leaves' => ['leave_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date'], 'Timekeeping' => ['period', 'employee_id'], 'Accounts' => ['employee_id', 'password_hash']];
    }
}
