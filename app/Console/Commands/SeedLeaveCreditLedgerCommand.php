<?php

namespace App\Console\Commands;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeaveCreditLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedLeaveCreditLedgerCommand extends Command
{
    protected $signature = 'hris:seed-leave-credit-ledger
        {--reconcile : Report employees where sum(delta) differs from cached VL/SL (no writes)}
        {--dry-run : Preview opening rows without writing}';

    protected $description = 'Seed opening VL/SL rows into employee_leave_credit_ledger (additive; does not alter legacy balances/logs).';

    public function handle(): int
    {
        if (! Schema::connection('hris')->hasTable('employee_leave_credit_ledger')) {
            $this->error('Table employee_leave_credit_ledger is missing. Run migrations first.');

            return self::FAILURE;
        }

        if ($this->option('reconcile')) {
            return $this->reconcile();
        }

        $dryRun = (bool) $this->option('dry-run');
        $seeded = 0;
        $skipped = 0;

        Employee::query()
            ->orderBy('emp_id')
            ->select(['emp_id', 'vacation_leave_credits', 'sick_leave_credits', 'date_hired'])
            ->chunkById(200, function ($employees) use (&$seeded, &$skipped, $dryRun) {
                foreach ($employees as $employee) {
                    $exists = EmployeeLeaveCreditLedger::query()
                        ->where('emp_id', $employee->emp_id)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $from = $employee->date_hired
                        ? optional($employee->date_hired)->format('Y-m-d')
                        : now()->toDateString();

                    $vl = round((float) ($employee->vacation_leave_credits ?? 0), 3);
                    $sl = round((float) ($employee->sick_leave_credits ?? 0), 3);

                    if ($dryRun) {
                        $this->line("Would seed {$employee->emp_id}: VL={$vl}, SL={$sl}, from={$from}");
                        $seeded++;

                        continue;
                    }

                    DB::connection('hris')->transaction(function () use ($employee, $from, $vl, $sl) {
                        EmployeeLeaveCreditLedger::query()->create([
                            'emp_id' => $employee->emp_id,
                            'bucket' => EmployeeLeaveCreditLedger::BUCKET_VL,
                            'delta' => $vl,
                            'balance_after' => $vl,
                            'effective_date' => $from,
                            'source' => EmployeeLeaveCreditLedger::SOURCE_OPENING,
                            'remarks' => 'Opening balance seeded from tbl_employee',
                            'recorded_by_emp_id' => 'system:seed-leave-credit-ledger',
                        ]);
                        EmployeeLeaveCreditLedger::query()->create([
                            'emp_id' => $employee->emp_id,
                            'bucket' => EmployeeLeaveCreditLedger::BUCKET_SL,
                            'delta' => $sl,
                            'balance_after' => $sl,
                            'effective_date' => $from,
                            'source' => EmployeeLeaveCreditLedger::SOURCE_OPENING,
                            'remarks' => 'Opening balance seeded from tbl_employee',
                            'recorded_by_emp_id' => 'system:seed-leave-credit-ledger',
                        ]);
                    });

                    $seeded++;
                }
            }, 'emp_id');

        $this->table(['Metric', 'Value'], [
            ['Mode', $dryRun ? 'dry-run' : 'applied'],
            ['Employees seeded', $seeded],
            ['Skipped (already have ledger)', $skipped],
        ]);

        return self::SUCCESS;
    }

    private function reconcile(): int
    {
        $mismatches = 0;

        Employee::query()
            ->orderBy('emp_id')
            ->select(['emp_id', 'vacation_leave_credits', 'sick_leave_credits'])
            ->chunkById(200, function ($employees) use (&$mismatches) {
                foreach ($employees as $employee) {
                    $sums = EmployeeLeaveCreditLedger::query()
                        ->where('emp_id', $employee->emp_id)
                        ->selectRaw('bucket, SUM(delta) as total')
                        ->groupBy('bucket')
                        ->pluck('total', 'bucket');

                    if ($sums->isEmpty()) {
                        continue;
                    }

                    $vlSum = round((float) ($sums[EmployeeLeaveCreditLedger::BUCKET_VL] ?? 0), 3);
                    $slSum = round((float) ($sums[EmployeeLeaveCreditLedger::BUCKET_SL] ?? 0), 3);
                    $vlCache = round((float) ($employee->vacation_leave_credits ?? 0), 3);
                    $slCache = round((float) ($employee->sick_leave_credits ?? 0), 3);

                    if (abs($vlSum - $vlCache) > 0.001 || abs($slSum - $slCache) > 0.001) {
                        $mismatches++;
                        $this->warn(sprintf(
                            '%s: ledger VL=%.3f cache=%.3f | ledger SL=%.3f cache=%.3f',
                            $employee->emp_id,
                            $vlSum,
                            $vlCache,
                            $slSum,
                            $slCache,
                        ));
                    }
                }
            }, 'emp_id');

        $this->info("Reconcile complete. Mismatches: {$mismatches}");

        return self::SUCCESS;
    }
}
