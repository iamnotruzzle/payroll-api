<?php

namespace App\Console\Commands;

use App\Services\Schedule\Schedulev2SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncSchedulev2SchedulesCommand extends Command
{
    protected $signature = 'schedule:sync-schedulev2
        {--dry-run : Count and validate without writing}
        {--apply : Write synced assignments into payroll_scheduler}
        {--from= : Start date (Y-m-d); defaults via --months-back}
        {--to= : End date (Y-m-d); defaults via --months-ahead}
        {--months-back= : Months before today when --from omitted}
        {--months-ahead= : Months after today when --to omitted}
        {--department= : Optional department_id filter (HRIS home OR duty-location-resolved dept)}
        {--division= : Optional division_id filter (HRIS home dept, duty-location-resolved dept, or NDOS location.division_id)}
        {--emp= : Optional emp_id filter}
        {--limit= : Optional max source rows}
        {--batch= : Optional batch key}';

    protected $description = 'Sync approved (status A) NDOS schedules into payroll_scheduler as locked months; re-compares every run (no DTR side effects)';

    public function handle(Schedulev2SyncService $service): int
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

        $today = CarbonImmutable::today();
        $monthsBack = $this->option('months-back');
        $monthsAhead = $this->option('months-ahead');
        $monthsBack = filled($monthsBack)
            ? max(0, (int) $monthsBack)
            : (int) config('schedule.schedulev2.default_months_back', 1);
        $monthsAhead = filled($monthsAhead)
            ? max(0, (int) $monthsAhead)
            : (int) config('schedule.schedulev2.default_months_ahead', 1);

        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        try {
            $from = filled($fromOption)
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $fromOption)->startOfDay()
                : $today->subMonthsNoOverflow($monthsBack)->startOfMonth();
            $to = filled($toOption)
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $toOption)->startOfDay()
                : $today->addMonthsNoOverflow($monthsAhead)->endOfMonth();
        } catch (\Throwable $e) {
            $this->error('Invalid --from / --to. Use Y-m-d. '.$e->getMessage());

            return self::FAILURE;
        }

        if ($from->greaterThan($to)) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $department = $this->option('department');
        $departmentId = filled($department) ? (int) $department : null;
        $division = $this->option('division');
        $divisionId = filled($division) ? (int) $division : null;
        $empId = filled($this->option('emp')) ? (string) $this->option('emp') : null;
        $limit = filled($this->option('limit')) ? (int) $this->option('limit') : null;

        $this->info($dryRun ? 'Dry run started…' : 'Applying NDOS sync…');
        $this->line("Range: {$from->toDateString()} → {$to->toDateString()}");
        $this->line('Source filter: approved employee_schedules only (status A; P/S/C/R/D skipped at source)');
        if ($departmentId) {
            $this->line("Department filter: {$departmentId} (matches HRIS home or duty-location-resolved dept)");
        }
        if ($divisionId) {
            $cnoId = (int) config('schedule.cno_division_id', 3);
            $hint = $divisionId === $cnoId ? ' (CNO / Nursing Service)' : '';
            $this->line("Division filter: {$divisionId}{$hint} (HRIS home dept, resolved duty-location dept, or location.division_id)");
        }
        if ($empId) {
            $this->line("Employee filter: {$empId}");
        }

        $result = $service->sync(
            from: $from,
            to: $to,
            dryRun: $dryRun,
            departmentId: $departmentId,
            divisionId: $divisionId,
            empId: $empId,
            limit: $limit,
            batchKey: $this->option('batch') ?: null,
        );

        $skipReasons = $result['skip_reasons'] ?? [];
        $unchanged = (int) ($result['unchanged'] ?? 0);
        $accounted = (int) ($result['accounted'] ?? ($result['created'] + $result['updated'] + $unchanged + $result['skipped']));
        $sourceCount = (int) $result['source_count'];

        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $result['dry_run'] ? 'dry-run' : 'apply'],
                ['Batch', $result['batch_key']],
                ['Connection OK', $result['connection_ok'] ? 'yes' : 'no'],
                ['Source rows (approved A)', $sourceCount],
                ['Would create / created', $result['created']],
                ['Would update / updated', $result['updated']],
                ['Unchanged (checked, identical)', $unchanged],
                ['Skipped (total)', $result['skipped']],
                ['  · skipped_oc_or_empty_label', $skipReasons['skipped_oc_or_empty_label'] ?? 0],
                ['  · skipped_no_employee', $skipReasons['skipped_no_employee'] ?? 0],
                ['  · skipped_department_filter', $skipReasons['skipped_department_filter'] ?? 0],
                ['  · skipped_division_filter', $skipReasons['skipped_division_filter'] ?? 0],
                ['  · skipped_no_shift_code', $skipReasons['skipped_no_shift_code'] ?? 0],
                ['Accounted (create+update+unchanged+skip)', $accounted],
                ['Months touched', $result['months_touched']],
                ['Locked months (no DTR)', $result['locked_months']],
                ['Errors', count($result['errors'])],
            ]
        );

        $this->line(
            "Reconciliation: source ({$sourceCount}) = create ({$result['created']})"
            ." + update ({$result['updated']}) + unchanged ({$unchanged}) + skip ({$result['skipped']})"
            ." → accounted {$accounted}"
            .($sourceCount === $accounted ? ' ✓' : ' ✗ MISMATCH')
        );
        $this->comment(
            'Source is approved (A) NDOS employee_schedules only (P/S/C/R/D never fetched). '
            .'Every run re-compares mapped rows by legacy_emp_sched_id: changed → update, identical → unchanged. '
            .'Local locked rows are never deleted when source drops approval. OC/empty labels and missing HRIS employees count as skip.'
        );
        if ($departmentId) {
            $this->comment(
                'Department filter is applied after fetch: a row is kept when HRIS home department_id OR the duty-location-resolved department equals the filter '
                .'(so floaters into that location are included). Placement still uses duty location when mappable, else HRIS home. '
                .'Other rows appear under skipped_department_filter.'
            );
        }
        if ($divisionId) {
            $this->comment(
                'Division filter keeps rows when the HRIS home department, duty-location-resolved department, or NDOS location.division_id '
                .'is in that division (typical Nursing pilot: --division='.(int) config('schedule.cno_division_id', 3).').'
            );
        }

        if (! empty($result['message'])) {
            $this->error($result['message']);
        }

        if ($result['errors'] !== []) {
            $this->warn('Errors (first 20):');
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line(' - '.$error);
            }
        }

        if (! $result['connection_ok']) {
            $this->comment('Configure DB_*_SCHEDULEV2 in .env, then re-run. See docs/hris-integration-todos.md progress log.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->comment('No rows written. Re-run with --apply when ready. Approved rows import under locked months without DTR sync.');
        } else {
            $this->info('Sync write complete. Lock→DTR was not triggered; re-lock manually only if you need payroll DTR push.');
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
