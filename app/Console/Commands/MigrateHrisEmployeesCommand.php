<?php

namespace App\Console\Commands;

use App\Services\Hris\EmployeeMigrationService;
use Illuminate\Console\Command;

class MigrateHrisEmployeesCommand extends Command
{
    protected $signature = 'hris:migrate-employees
        {--dry-run : Count and validate without writing to hris_v2}
        {--apply : Write migrated employees into hris_v2}
        {--emp= : Optional single emp_id to migrate}
        {--limit= : Optional max employees to process}
        {--batch= : Optional batch key}';

    protected $description = 'Migrate legacy HRIS employees and PDS sections into hris_v2';

    public function handle(EmployeeMigrationService $service): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;

        if ($apply && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('No mode selected. Running --dry-run. Pass --apply to write data.');
            $dryRun = true;
        }

        $limit = $this->option('limit');
        $limit = filled($limit) ? (int) $limit : null;
        $empId = $this->option('emp');
        $empId = filled($empId) ? (string) $empId : null;

        $this->info($dryRun ? 'Dry run started…' : 'Applying employee migration…');
        if ($empId) {
            $this->line("Scoped to emp_id={$empId}");
        }

        $result = $service->migrate(
            batchKey: $this->option('batch') ?: null,
            dryRun: $dryRun,
            limit: $limit,
            empId: $empId,
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $result['dry_run'] ? 'dry-run' : 'apply'],
                ['Batch', $result['batch_key']],
                ['Source employees', $result['source_employee_count']],
                ['Processed employees', $result['migrated_employee_count']],
                ['Source PDS sections', $result['source_section_count']],
                ['Migrated PDS sections', $result['migrated_section_count']],
                ['Created', $result['created']],
                ['Updated', $result['updated']],
                ['Skipped / errors', $result['skipped']],
            ]
        );

        if ($result['errors'] !== []) {
            $this->warn('Errors (first 20):');
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line(' - '.$error);
            }
        }

        if ($dryRun) {
            $this->comment('No rows written. Re-run with --apply when ready.');
        } else {
            $this->info('Migration write complete.');
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
