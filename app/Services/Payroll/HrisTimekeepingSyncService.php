<?php

namespace App\Services\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollSourceBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HrisTimekeepingSyncService
{
    public function sync(string $period, ?string $by = null): PayrollSourceBatch
    {
        if (app(PayrollOperatingModeService::class)->current() !== PayrollOperatingMode::Standalone) {
            throw ValidationException::withMessages(['sync' => 'HRIS timekeeping preparation requires standalone mode.']);
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m', $period);
        if (! $date || $date->format('Y-m') !== $period) {
            throw ValidationException::withMessages(['period' => 'Select a valid payroll month.']);
        }
        if (! Schema::connection('payroll')->hasTable('payroll_hris_dtr_snapshots')) {
            throw new RuntimeException('Run the HRIS timekeeping snapshot migration on the payroll database before synchronizing.');
        }
        $start = CarbonImmutable::parse($period.'-01')->subMonthNoOverflow();
        $from = $start->toDateString();
        $to = $start->endOfMonth()->toDateString();
        $ids = DB::connection('payroll')->table('payroll_canonical_employees')->pluck('emp_id');
        if ($ids->isEmpty()) {
            throw new RuntimeException('Synchronize employee master data before timekeeping.');
        }
        $rows = [];
        foreach ($ids->chunk(500) as $chunk) {
            $records = DB::connection('hris')->table('tbl_employee_dtr')
                ->whereIn('emp_id', $chunk->all())->whereBetween('dtr_date', [$from, $to])->orderBy('dtr_id')->get();
            foreach ($records as $record) {
                $row = ['emp_id' => (string) $record->emp_id, 'dtr_date' => substr((string) $record->dtr_date, 0, 10), 'source_dtr_id' => $record->dtr_id];
                foreach (['timein_am', 'timeout_am', 'timein_pm', 'timeout_pm', 'timeout_nextday', 'machine_id'] as $column) {
                    $row[$column] = $record->{$column} ?? null;
                }
                $key = $row['emp_id'].'|'.$row['dtr_date'];
                if (isset($rows[$key])) {
                    throw new RuntimeException('HRIS contains duplicate employee DTR dates. Resolve duplicates before synchronizing.');
                }
                $rows[$key] = $row;
            }
        }
        if ($rows === []) {
            throw new RuntimeException("No HRIS DTR records were found for {$from} through {$to}. Existing timekeeping was retained.");
        }

        return DB::connection('payroll')->transaction(function () use ($rows, $by, $period, $from, $to) {
            $batch = PayrollSourceBatch::query()->create([
                'kind' => 'hris_timekeeping', 'source' => 'hris_db', 'status' => 'active', 'schema_version' => '1.0',
                'effective_period' => $period, 'checksum' => hash('sha256', json_encode($rows)),
                'statistics' => ['DTR records' => count($rows), 'Employees' => count(array_unique(array_column($rows, 'emp_id'))), 'from' => $from, 'to' => $to],
                'payload' => ['DTR' => array_values($rows)], 'errors' => [], 'created_by' => $by, 'activated_by' => $by, 'activated_at' => now(),
            ]);
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                $records = array_map(fn ($row) => $row + ['source_batch_id' => $batch->id, 'created_at' => now(), 'updated_at' => now()], $chunk);
                DB::connection('payroll')->table('payroll_hris_dtr_snapshots')->upsert($records, ['emp_id', 'dtr_date'],
                    ['source_dtr_id', 'timein_am', 'timeout_am', 'timein_pm', 'timeout_pm', 'timeout_nextday', 'machine_id', 'source_batch_id', 'updated_at']);
            }
            DB::connection('payroll')->table('payroll_hris_dtr_snapshots')->whereBetween('dtr_date', [$from, $to])
                ->whereIn('emp_id', array_keys(collect($rows)->keyBy('emp_id')->all()))
                ->where('source_batch_id', '!=', $batch->id)->delete();
            PayrollSourceBatch::query()->where('kind', 'hris_timekeeping')->where('effective_period', $period)
                ->where('id', '!=', $batch->id)->where('status', 'active')->update(['status' => 'superseded']);
            app(PayrollTimekeepingSourceService::class)->save('hris_db', $by, true);

            return $batch;
        });
    }
}
