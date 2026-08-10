<?php

namespace App\Console\Commands;

use App\Services\Hris\LeaveCreditComputationService;
use Illuminate\Console\Command;

class ComputeLeaveCreditsCommand extends Command
{
    protected $signature = 'hris:compute-leave-credits
        {--emp= : Optional single emp_id}
        {--limit= : Optional max employees to process}
        {--dry-run : Preview computed balances without writing (default)}
        {--apply : Set absolute VL/SL from hire-date recompute (writes ledger via updateCredits)}';

    protected $description = 'Compute leave credits/entitlements from date_hired + employment status (legacy hris tables).';

    public function handle(LeaveCreditComputationService $service): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        if ($apply && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('Neither --apply nor --dry-run was provided. Running dry-run.');
            $dryRun = true;
        }

        $empId = filled($this->option('emp')) ? (string) $this->option('emp') : null;
        $limit = filled($this->option('limit')) ? (int) $this->option('limit') : null;

        $this->info($dryRun
            ? 'Preview: theoretical VL/SL from date_hired − approved usage − undertime (no writes).'
            : 'Apply: setting absolute VL/SL balances from recompute (ledger via updateCredits).');

        $result = $service->applyComputedBalances($empId, $limit, $dryRun);

        $tableRows = collect($result['rows'])->map(function (array $row) {
            $other = collect($row['entitlements'] ?? [])
                ->filter(fn (array $e) => ($e['max_value'] ?? 0) > 0)
                ->map(function (array $e) {
                    $flag = ($e['eligible'] ?? true) ? '' : '!';

                    return sprintf('%s%s:%.1f', $flag, substr((string) $e['leave_name'], 0, 12), (float) ($e['remaining'] ?? 0));
                })
                ->take(4)
                ->implode(' | ');

            return [
                $row['emp_id'],
                $row['status_label'],
                $row['date_hired'] ?? '—',
                $row['months_of_service'],
                $row['accrual_eligible'] ? 'Y' : 'N',
                number_format($row['vl']['earned'], 3),
                number_format($row['vl']['used'], 3),
                number_format($row['vl']['undertime'], 3),
                number_format($row['vl']['computed'], 3),
                number_format($row['vl']['stored'], 3),
                number_format($row['vl']['delta'], 3),
                number_format($row['sl']['earned'], 3),
                number_format($row['sl']['used'], 3),
                number_format($row['sl']['computed'], 3),
                number_format($row['sl']['stored'], 3),
                number_format($row['sl']['delta'], 3),
                $other ?: '—',
            ];
        })->all();

        if ($tableRows !== []) {
            $this->table(
                [
                    'emp_id', 'status', 'hired', 'mos', 'accr?',
                    'VL earn', 'VL used', 'VL UT', 'VL calc', 'VL now', 'VL Δ',
                    'SL earn', 'SL used', 'SL calc', 'SL now', 'SL Δ',
                    'other remaining',
                ],
                $tableRows,
            );
        } else {
            $this->warn('No employees matched.');
        }

        $this->table(['Metric', 'Value'], [
            ['Mode', $result['dry_run'] ? 'dry-run' : 'applied'],
            ['Rows', count($result['rows'])],
            ['Would update / updated', $result['updated']],
            ['Skipped (ineligible or unchanged)', $result['skipped']],
        ]);

        if ($result['dry_run']) {
            $this->comment('Apply sets absolute VL/SL = earned − approved VL/SL usage − undertime. Re-run with --apply to persist.');
            $this->comment('Stored balances often diverge (manual edits, monetization, incomplete historical ledgers). Review Δ carefully.');
        }

        return self::SUCCESS;
    }
}
