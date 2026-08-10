<?php

namespace App\Console\Commands;

use App\Services\Hris\LeaveService;
use Illuminate\Console\Command;

class AccrueLeaveCreditsCommand extends Command
{
    protected $signature = 'hris:accrue-leave-credits
        {--vl=1.25 : Vacation leave days to add per active employee}
        {--sl=1.25 : Sick leave days to add per active employee}
        {--dry-run : Preview without writing}
        {--apply : Persist credit updates}';

    protected $description = 'Accrue monthly VL/SL on eligible employment statuses (legacy tbl_employee; respects date_hired).';

    public function handle(LeaveService $leaveService): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('Neither --apply nor --dry-run was provided. Running dry-run.');
            $dryRun = true;
        }

        $this->comment('Eligible empstat_ids: '.implode(', ', config('hris.leave_credits.accrual_empstat_ids', [])));
        $this->comment('Excludes positions: '.implode(', ', config('hris.leave_credits.excluded_position_ids', [])));

        $result = $leaveService->accrueMonthlyCredits(
            (float) $this->option('vl'),
            (float) $this->option('sl'),
            $dryRun,
        );

        $this->table(['Metric', 'Value'], [
            ['Mode', $result['dry_run'] ? 'dry-run' : 'applied'],
            ['Employees updated', $result['updated']],
            ['Skipped (ineligible / not hired yet / zero)', $result['skipped']],
            ['VL option', $this->option('vl')],
            ['SL option', $this->option('sl')],
        ]);

        if ($result['dry_run']) {
            $this->comment('Re-run with --apply to persist credit accrual.');
        }

        return self::SUCCESS;
    }
}
