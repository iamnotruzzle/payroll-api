<?php

namespace App\Console\Commands;

use App\Services\Schedule\Schedulev2BackfillService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillSchedulev2Command extends Command
{
    protected $signature = 'schedule:backfill-schedulev2
        {--dry-run : Preview clear counts + source counts without writing (default)}
        {--apply : Destructively clear schedule tables then backfill from NDOS}
        {--force : Required with --apply (confirms destructive truncate)}
        {--with-assignments : After references, pull approved (A) assignments via schedule sync (no DTR)}
        {--from= : Assignment sync start date (Y-m-d); used with --with-assignments}
        {--to= : Assignment sync end date (Y-m-d); used with --with-assignments}
        {--months-back= : Months before today when --from omitted (assignment sync)}
        {--months-ahead= : Months after today when --to omitted (assignment sync)}
        {--division= : Optional division_id filter for assignment sync (e.g. CNO)}
        {--batch= : Optional batch key}';

    protected $description = 'Clear payroll_scheduler schedule tables then backfill shift codes, units, groups, pools, settings from NDOS (no DTR)';

    public function handle(Schedulev2BackfillService $service): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        $withAssignments = (bool) $this->option('with-assignments');

        if ($apply && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('No mode selected. Running --dry-run. Pass --apply --force to clear+write.');
            $dryRun = true;
        }

        if ($apply && ! $force) {
            $this->error('Destructive apply requires --force. Example: php artisan schedule:backfill-schedulev2 --apply --force');

            return self::FAILURE;
        }

        $today = CarbonImmutable::today();
        $monthsBack = filled($this->option('months-back'))
            ? max(0, (int) $this->option('months-back'))
            : (int) config('schedule.schedulev2.default_months_back', 1);
        $monthsAhead = filled($this->option('months-ahead'))
            ? max(0, (int) $this->option('months-ahead'))
            : (int) config('schedule.schedulev2.default_months_ahead', 1);

        $assignmentFrom = null;
        $assignmentTo = null;
        if ($withAssignments) {
            try {
                $assignmentFrom = filled($this->option('from'))
                    ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('from'))->startOfDay()
                    : $today->subMonthsNoOverflow($monthsBack)->startOfMonth();
                $assignmentTo = filled($this->option('to'))
                    ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('to'))->startOfDay()
                    : $today->addMonthsNoOverflow($monthsAhead)->endOfMonth();
            } catch (\Throwable $e) {
                $this->error('Invalid --from / --to. Use Y-m-d. '.$e->getMessage());

                return self::FAILURE;
            }

            if ($assignmentFrom->greaterThan($assignmentTo)) {
                $this->error('--from must be on or before --to.');

                return self::FAILURE;
            }
        }

        $division = $this->option('division');
        $divisionId = filled($division) ? (int) $division : null;

        $this->info($dryRun ? 'Dry-run: NDOS full backfill preview…' : 'APPLY: clearing schedule tables then backfilling…');
        $this->warn('This targets ONLY schedule-owned tables on payroll_scheduler. HRIS/payroll/non-schedule tables are never touched.');
        $this->line('Lock→DTR is never triggered.');

        $this->newLine();
        $this->line('Tables that will be truncated:');
        foreach ($service->clearTables() as $table) {
            $this->line('  - '.$table);
        }
        $this->newLine();
        $this->comment('Skipped (not cleared): employee_references, schedule_print_settings, schedule_print_logos, roles, cache, jobs, migrations.');

        if ($withAssignments) {
            $this->line(
                'Assignment sync after refs: '
                .$assignmentFrom->toDateString().' → '.$assignmentTo->toDateString()
                .' (approved A only; months locked without DTR)'
            );
            if ($divisionId) {
                $this->line("Division filter for assignments: {$divisionId}");
            }
        } else {
            $this->comment('Assignments will remain empty after clear unless you pass --with-assignments (or run schedule:sync-schedulev2 later).');
        }

        if (! $dryRun) {
            $this->warn('DESTRUCTIVE: existing local schedule data in the listed tables will be permanently deleted.');
        }

        $result = $service->backfill(
            dryRun: $dryRun,
            force: $force,
            withAssignments: $withAssignments,
            assignmentFrom: $assignmentFrom,
            assignmentTo: $assignmentTo,
            divisionId: $divisionId,
            batchKey: $this->option('batch') ?: null,
        );

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $result['dry_run'] ? 'dry-run' : 'apply'],
                ['Batch', $result['batch_key']],
                ['Connection OK', $result['connection_ok'] ? 'yes' : 'no'],
                ['With assignments', $result['with_assignments'] ? 'yes' : 'no'],
                ['Errors', count($result['errors'] ?? [])],
            ]
        );

        if (($result['cleared'] ?? []) !== []) {
            $this->newLine();
            $this->info($dryRun ? 'Would clear (row counts):' : 'Cleared:');
            $clearRows = [];
            foreach ($result['cleared'] as $table => $count) {
                $clearRows[] = [$table, $count];
            }
            $this->table(['Table', $dryRun ? 'Rows now' : 'Rows removed'], $clearRows);
        }

        if (($result['source_counts'] ?? []) !== []) {
            $this->newLine();
            $this->info('NDOS source counts:');
            $sourceRows = [];
            foreach ($result['source_counts'] as $table => $count) {
                $sourceRows[] = [$table, $count];
            }
            $this->table(['Source table', 'Count'], $sourceRows);
        }

        if (($result['created'] ?? []) !== []) {
            $this->newLine();
            $this->info($dryRun ? 'Estimated / planned creates:' : 'Created:');
            $createdRows = [];
            foreach ($result['created'] as $entity => $count) {
                $createdRows[] = [$entity, $count];
            }
            $this->table(['Entity', $dryRun ? 'Estimate' : 'Created'], $createdRows);
        }

        if (($result['skipped'] ?? []) !== []) {
            $this->newLine();
            $this->info('Skip reasons:');
            $skipRows = [];
            foreach ($result['skipped'] as $reason => $count) {
                $skipRows[] = [$reason, $count];
            }
            $this->table(['Reason', 'Count'], $skipRows);
        }

        foreach ($result['notes'] ?? [] as $note) {
            $this->comment($note);
        }

        if (! empty($result['assignment_sync'])) {
            $as = $result['assignment_sync'];
            $this->newLine();
            $this->info('Assignment sync summary:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Source (A)', $as['source_count'] ?? 0],
                    ['Created', $as['created'] ?? 0],
                    ['Updated', $as['updated'] ?? 0],
                    ['Unchanged', $as['unchanged'] ?? 0],
                    ['Skipped', $as['skipped'] ?? 0],
                    ['Months touched', $as['months_touched'] ?? 0],
                    ['Locked months (no DTR)', $as['locked_months'] ?? 0],
                ]
            );
        }

        if (! empty($result['message'])) {
            $this->error($result['message']);
        }

        if (($result['errors'] ?? []) !== []) {
            $this->warn('Errors (first 20):');
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line(' - '.$error);
            }
        }

        if (! ($result['connection_ok'] ?? false)) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->comment('No changes written. When ready: php artisan schedule:backfill-schedulev2 --apply --force');
            $this->comment('Optional assignments: add --with-assignments [--division=3] [--from=] [--to=]');
        } else {
            $this->info('Backfill complete. Lock→DTR was not triggered.');
        }

        return ($result['errors'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }
}
