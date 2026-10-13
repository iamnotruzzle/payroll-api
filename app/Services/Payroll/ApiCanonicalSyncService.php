<?php

namespace App\Services\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollSourceBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ApiCanonicalSyncService
{
    public function sync(string $period, ?string $by = null, ?string $from = null, ?string $to = null): PayrollSourceBatch
    {
        if (app(PayrollOperatingModeService::class)->current() !== PayrollOperatingMode::Standalone) {
            throw ValidationException::withMessages(['mode' => 'HRIS API synchronization requires standalone mode.']);
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $period);
        if (! $date || $date->format('Y-m') !== $period) {
            throw ValidationException::withMessages(['period' => 'Select a valid YYYY-MM period for the leave export.']);
        }
        $start = CarbonImmutable::parse($period.'-01');
        $from = $from ?: $start->toDateString();
        $to = $to ?: $start->endOfMonth()->toDateString();
        foreach ([$from, $to] as $value) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (! $parsed || $parsed->format('Y-m-d') !== $value) {
                throw ValidationException::withMessages(['period' => 'Leave range dates must use YYYY-MM-DD.']);
            }
        }
        if ($to < $from) {
            throw ValidationException::withMessages(['period' => 'Leave range end must be on or after its start.']);
        }
        if (! DB::connection('payroll')->getSchemaBuilder()->hasColumn('payroll_canonical_leaves', 'leave_dates')) {
            throw new RuntimeException('Run the HRIS API canonical inputs migration on the payroll database before synchronizing.');
        }
        $payload = [];
        $client = app(HrisApiClient::class);
        foreach (['divisions' => 'Divisions', 'departments' => 'Departments', 'positions' => 'Positions',
            'employment-statuses' => 'Employment Statuses', 'employees' => 'Employees',
            'salary-rates' => 'Salary Rates', 'leave-types' => 'Leave Types', 'leaves' => 'Leaves'] as $resource => $sheet) {
            $result = $client->fetch($resource, ['from' => $from, 'to' => $to]);
            if ($result['warning_count']) {
                throw new RuntimeException("HRIS reported warnings for {$resource}; no data was activated.");
            }
            $payload[$sheet] = array_map(fn ($row, $index) => $row + ['_row' => $index + 1], $result['rows'], array_keys($result['rows']));
        }
        if ($payload['Employees'] === [] || $payload['Salary Rates'] === []) {
            throw new RuntimeException('HRIS returned no employees or salary rates; no data was activated.');
        }
        // Payroll employee categories have fixed IDs. Resolve remote IDs by their documented names.
        $categories = ['permanent' => 1, 'casual' => 2, 'part time' => 3, 'contractual' => 4,
            'temporary' => 5, 'visiting consultant' => 6, 'contract of service' => 7,
            'probationary' => 8, 'intern' => 9, 'external' => 10];
        $statuses = [];
        foreach ($payload['Employment Statuses'] as $row) {
            $name = strtolower(trim(str_replace('-', ' ', $row['status'])));
            $statuses[$row['employment_status_id']] = $categories[$name] ?? null;
        }
        foreach ($payload['Employees'] as &$row) {
            $status = $statuses[$row['employment_status_id']] ?? null;
            if (! $status) {
                throw new RuntimeException('An HRIS employment status has no supported payroll category.');
            }
            $row['employment_status_id'] = $status;
            $row['confirm_deactivation'] = true; // Explicit HRIS boolean is authoritative.
        }
        unset($row);
        foreach ($payload as $rows) {
            foreach ($rows as $row) {
                foreach (['is_active', 'is_external', 'cancelled'] as $field) {
                    if (array_key_exists($field, $row) && ! is_bool($row[$field])) {
                        throw new RuntimeException("HRIS {$field} must be a JSON boolean.");
                    }
                }
                foreach (['date_hired', 'effective_from', 'effective_to', 'start_date', 'end_date'] as $field) {
                    if (isset($row[$field])) {
                        $value = $row[$field];
                        $parsed = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
                        if (! $parsed || $parsed->format('Y-m-d') !== $value) {
                            throw new RuntimeException("HRIS {$field} must be a valid YYYY-MM-DD date.");
                        }
                    }
                }
                foreach (['vl_balance', 'sl_balance'] as $field) {
                    if (isset($row[$field]) && (! is_numeric($row[$field]) || $row[$field] < 0)) {
                        throw new RuntimeException("HRIS {$field} must be non-negative.");
                    }
                }
                if (isset($row['effective_from'], $row['effective_to']) && $row['effective_to'] < $row['effective_from']) {
                    throw new RuntimeException('HRIS salary effective_to precedes effective_from.');
                }
            }
        }
        foreach ($payload['Leaves'] as $row) {
            if (! in_array(strtolower(trim($row['status'])), ['approved', 'pending', 'disapproved', 'rejected', 'cancelled', 'canceled'], true)) {
                throw new RuntimeException('Unknown HRIS leave status; no data was activated.');
            }
            foreach (['days_with_pay', 'days_without_pay'] as $field) {
                if (! is_numeric($row[$field]) || $row[$field] < 0) {
                    throw new RuntimeException("HRIS {$field} must be non-negative.");
                }
            }
            if ($row['leave_dates'] !== null) {
                if (! is_array($row['leave_dates']) || ! array_is_list($row['leave_dates'])) {
                    throw new RuntimeException('HRIS leave_dates must be null or a list of YYYY-MM-DD dates.');
                }
                foreach ($row['leave_dates'] as $value) {
                    $parsed = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
                    if (! $parsed || $parsed->format('Y-m-d') !== $value || $value < $row['start_date'] || $value > $row['end_date']) {
                        throw new RuntimeException('HRIS leave_dates contains an invalid or out-of-range date.');
                    }
                }
            }
        }
        $workbooks = app(CanonicalWorkbookService::class);

        return DB::connection('payroll')->transaction(function () use ($payload, $period, $by, $workbooks, $from, $to) {
            $payload = $this->preserveLocalFields($payload);
            $errors = $workbooks->validatePayload($payload, $period);
            if ($errors !== []) {
                throw ValidationException::withMessages(['sync' => $errors]);
            }
            $batch = PayrollSourceBatch::query()->firstOrCreate([
                'kind' => 'api_full', 'checksum' => hash('sha256', json_encode([$payload, $from, $to])), 'effective_period' => $period,
            ], ['source' => 'hris_api', 'status' => 'validated', 'schema_version' => '1.0',
                'statistics' => array_map('count', $payload) + ['leave_range' => ['from' => $from, 'to' => $to]], 'errors' => [], 'payload' => $payload, 'created_by' => $by]);
            if ($batch->status !== 'active') {
                $batch->update(['status' => 'validated']);
                $workbooks->activate($batch, $by);
            }

            return $batch->fresh();
        });
    }

    private function preserveLocalFields(array $payload): array
    {
        foreach ($payload['Leaves'] as &$row) {
            $row['cancelled'] = $row['cancelled'] || in_array(strtolower(trim($row['status'])), ['cancelled', 'canceled'], true);
        }
        unset($row);
        foreach (['Employees' => ['payroll_canonical_employees', 'emp_id', 'employee_id', [
            'responsibility_center' => 'responsibility_center', 'bank_account' => 'lbp_account_no', 'fund_type' => 'fund_type',
        ]], 'Positions' => ['payroll_canonical_positions', 'external_id', 'position_id', ['remarks' => 'remarks', 'is_active' => 'is_active']],
            'Leave Types' => ['payroll_canonical_leave_types', 'external_id', 'leave_type_id', ['is_active' => 'is_active']]] as $sheet => [$table, $key, $remoteKey, $fields]) {
            $existing = DB::connection('payroll')->table($table)->get()->keyBy($key);
            foreach ($payload[$sheet] as &$row) {
                $local = $existing->get($row[$remoteKey]);
                foreach ($fields as $field => $column) {
                    $row[$field] = $local?->{$column} ?? ($field === 'is_active' ? true : null);
                    if ($field === 'is_active') {
                        $row[$field] = (bool) $row[$field];
                    }
                }
                if ($sheet === 'Employees') {
                    foreach (['tin' => 'tin_no', 'gsis' => 'gsis_no', 'philhealth' => 'phic_no', 'pagibig' => 'pagibig_no'] as $field => $column) {
                        if (is_string($row[$field]) && str_contains($row[$field], '*')) {
                            $row[$field] = $local?->{$column};
                        }
                    }
                }
            }
            unset($row);
        }

        return $payload;
    }
}
