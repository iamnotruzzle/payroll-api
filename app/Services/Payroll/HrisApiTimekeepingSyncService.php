<?php

namespace App\Services\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollSourceBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HrisApiTimekeepingSyncService
{
    public function sync(string $payrollPeriod, ?string $by, array $scope): PayrollSourceBatch
    {
        if (app(PayrollOperatingModeService::class)->current() !== PayrollOperatingMode::Standalone) {
            throw ValidationException::withMessages(['sync' => 'API timekeeping preparation requires standalone mode.']);
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $payrollPeriod);
        if (! $date || $date->format('Y-m') !== $payrollPeriod) {
            throw ValidationException::withMessages(['period' => 'Select a valid payroll month.']);
        }
        if (empty($scope['division_ids']) && empty($scope['department_ids'])) {
            throw ValidationException::withMessages(['sync' => 'Select a payroll scope before synchronizing.']);
        }
        $ids = app(PayrollSyncScope::class)->employees($scope)->pluck('emp_id')->map('strval')->all();
        if ($ids === []) {
            throw new RuntimeException('No employees match the payroll scope. Synchronize master data first.');
        }
        $period = CarbonImmutable::createFromFormat('!Y-m', $payrollPeriod)->subMonthNoOverflow()->format('Y-m');
        $rows = [];
        foreach (array_chunk($ids, 100) as $chunk) {
            $result = app(HrisApiClient::class)->fetch('timekeeping', ['period' => $period, 'employee_ids' => $chunk]);
            if ($result['warning_count']) {
                throw new RuntimeException('HRIS reported timekeeping warnings. Existing attendance was retained.');
            }
            $dtrCounts = null;
            foreach ($result['rows'] as $row) {
                $id = $row['employee_id'];
                if (! in_array($id, $chunk, true) || $row['period'] !== $period || isset($rows[$id])) {
                    throw new RuntimeException('HRIS timekeeping returned an unexpected employee, period, or duplicate record. Existing attendance was retained.');
                }
                if ($row['days_with_dtr'] === null) {
                    if ($dtrCounts === null) {
                        $missingIds = array_values(array_intersect($chunk, array_column(array_filter($result['rows'], fn ($record) => $record['days_with_dtr'] === null && $record['period'] === $period), 'employee_id')));
                        $start = CarbonImmutable::createFromFormat('!Y-m', $period);
                        try {
                            $dtrCounts = DB::connection('hris')->table('tbl_employee_dtr')
                                ->whereIn('emp_id', $missingIds)
                                ->where('dtr_date', '>=', $start->toDateString())
                                ->where('dtr_date', '<', $start->addMonth()->toDateString())
                                ->select('emp_id')->selectRaw('COUNT(DISTINCT DATE(dtr_date)) AS dtr_days')
                                ->groupBy('emp_id')->pluck('dtr_days', 'emp_id');
                        } catch (\Exception $e) {
                            throw new RuntimeException('Could not load missing DTR day counts from the HRIS database. Check the HRIS connection and tbl_employee_dtr. Existing attendance was retained.');
                        }
                    }
                    $row['days_with_dtr'] = (int) ($dtrCounts->get($id) ?? 0);
                    $row['days_with_dtr_source'] = 'hris_db';
                }
                foreach (['work_days', 'holidays', 'days_with_dtr', 'regular_hours', 'tardy_hours', 'undertime_hours', 'leave_days_with_pay', 'leave_days_without_pay', 'absent_days'] as $field) {
                    if (! is_numeric($row[$field]) || ! is_finite((float) $row[$field]) || (float) $row[$field] < 0) {
                        throw new RuntimeException("Invalid HRIS timekeeping {$field}. Existing attendance was retained.");
                    }
                }
                $rows[$id] = $row + ['mra_hours' => 0, '_row' => count($rows) + 1];
            }
        }
        if (array_diff($ids, array_map('strval', array_keys($rows))) !== []) {
            throw new RuntimeException('HRIS did not return timekeeping for every scoped employee. Existing attendance was retained.');
        }
        $payload = ['Timekeeping' => array_values($rows)];
        $workbooks = app(CanonicalWorkbookService::class);
        $errors = $workbooks->validatePayload($payload, $period);
        if ($errors !== []) {
            throw ValidationException::withMessages(['sync' => $errors]);
        }

        return DB::connection('payroll')->transaction(function () use ($payload, $period, $payrollPeriod, $scope, $by, $workbooks) {
            $batch = PayrollSourceBatch::query()->firstOrCreate([
                'kind' => 'api_timekeeping_'.str_replace('-', '', $period).'_'.substr(hash('sha256', json_encode($scope)), 0, 16),
                'effective_period' => $period, 'checksum' => hash('sha256', json_encode($payload)),
            ], ['source' => 'hris_api', 'status' => 'validated', 'schema_version' => '1.0',
                'payload' => $payload, 'errors' => [], 'created_by' => $by,
                'statistics' => ['Employees' => count($payload['Timekeeping']),
                    'DTR database fallbacks' => count(array_filter($payload['Timekeeping'], fn ($row) => ($row['days_with_dtr_source'] ?? null) === 'hris_db')),
                    'payroll_period' => $payrollPeriod, 'attendance_period' => $period, 'scope' => $scope]]);
            $storedCount = DB::connection('payroll')->table('payroll_canonical_timekeeping')
                ->where('period', $period)->where('source_batch_id', $batch->id)
                ->whereIn('emp_id', array_column($payload['Timekeeping'], 'employee_id'))->count();
            if ($batch->status !== 'active' || $storedCount !== count($payload['Timekeeping'])) {
                $batch->update(['status' => 'validated']);
                $workbooks->activate($batch, $by);
            }
            app(PayrollTimekeepingSourceService::class)->save('hris_api', $by);

            return $batch->fresh();
        });
    }
}
