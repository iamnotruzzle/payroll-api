<?php

namespace App\Console\Commands;

use App\Services\Payroll\ConnectedCanonicalSyncService;
use Illuminate\Console\Command;

class SyncPayrollCanonicalData extends Command
{
    protected $signature = 'payroll:canonical-sync {--by=system:canonical-sync}';

    protected $description = 'Atomically synchronize payroll-owned canonical data from connected HRIS sources';

    public function handle(ConnectedCanonicalSyncService $service): int
    {
        $this->info('Staging connected payroll data...');
        $batch = $service->sync($this->option('by'));
        $this->info("Activated canonical source batch #{$batch->id}.");

        return self::SUCCESS;
    }
}
