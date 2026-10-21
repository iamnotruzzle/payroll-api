<?php

namespace App\Console\Commands;

use App\Services\Payroll\ApiCanonicalSyncService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SyncPayrollApiData extends Command
{
    protected $signature = 'payroll:api-sync {--period= : YYYY-MM payroll period} {--from= : YYYY-MM-DD leave cutoff start} {--to= : YYYY-MM-DD leave cutoff end} {--by=system:api-sync}';

    protected $description = 'Synchronize standalone payroll data from the HRIS payroll API';

    public function handle(ApiCanonicalSyncService $service): int
    {
        try {
            $batch = $service->sync((string) $this->option('period'), $this->option('by'), $this->option('from'), $this->option('to'));
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        } catch (\Illuminate\Database\QueryException $e) {
            $this->error('Payroll database synchronization failed. Active data was not replaced. Check the application log.');

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Activated HRIS API source batch #{$batch->id}.");
        if ($error = app(\App\Services\Payroll\PayrollReadinessService::class)->apiGenerationError()) {
            $this->warn($error);
        } elseif (app(\App\Services\Payroll\PayrollTimekeepingSourceService::class)->usesHrisApi()) {
            $this->info('HRIS API attendance summaries selected. Review scoped previous-month attendance before finalizing payroll.');
        } else {
            $this->info('HRIS database timekeeping selected. Complete the DTR/MRA workflow before finalizing payroll.');
        }

        return self::SUCCESS;
    }
}
