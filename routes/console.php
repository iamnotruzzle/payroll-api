<?php

use App\Services\Rbac\LegacyHrisRbacBackfill;
use App\Services\Schedule\ScheduleDraftGenerationService;
use Carbon\CarbonImmutable;
use Database\Seeders\RBACSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('schedule:generate-next-month-draft {--force}', function () {
    $today = CarbonImmutable::today(config('app.timezone'));
    $draftMonthStart = $today->addDays(7);

    if (! $this->option('force') && $draftMonthStart->day !== 1) {
        $this->info('Next-month draft generation is not due today.');

        return 0;
    }

    $service = app(ScheduleDraftGenerationService::class);
    $result = $service->generate(
        $draftMonthStart->year,
        $draftMonthStart->month,
        null,
        null,
        'system:auto-draft',
    );

    $this->info(sprintf(
        'Draft schedule %d generated for %04d-%02d with %d conflict(s).',
        $result['schedule']->id,
        $draftMonthStart->year,
        $draftMonthStart->month,
        count($result['conflicts']),
    ));

    return 0;
})->purpose('Generate the next monthly draft schedule exactly one week before the upcoming month.');

Artisan::command('rbac:backfill-legacy-accounts
    {--apply : Persist inferred role assignments. Omit for a dry run.}
    {--include-existing : Add inferred roles even when an account already has one or more roles.}
    {--skip-seed : Do not refresh RBAC roles and permissions before scanning accounts.}
    {--show-assignments : Print every inferred account assignment.}', function (LegacyHrisRbacBackfill $backfill) {
    if (! $this->option('skip-seed')) {
        $this->call('db:seed', ['--class' => RBACSeeder::class, '--force' => true]);
    }

    $apply = (bool) $this->option('apply');
    $summary = $apply
        ? $backfill->apply((bool) $this->option('include-existing'))
        : $backfill->preview((bool) $this->option('include-existing'));

    $this->info($apply ? 'Legacy HRIS RBAC backfill applied.' : 'Legacy HRIS RBAC backfill dry run.');
    $this->table(['Metric', 'Count'], [
        ['Accounts scanned', $summary['scanned']],
        ['Eligible for inferred roles', $summary['eligible']],
        ['Skipped because roles already exist', $summary['skipped_existing_roles']],
        ['Accounts updated', $summary['updated']],
    ]);

    if ($summary['missing_roles'] !== []) {
        $this->error('Missing RBAC roles: '.implode(', ', $summary['missing_roles']));
        $this->warn('Run without --skip-seed or fix the RBACSeeder before applying.');
    }

    if ($summary['unmapped_user_levels'] !== []) {
        $this->warn('Unmapped user_level values fell back to employee unless pims_role mapped higher:');
        $this->table(['user_level', 'accounts'], collect($summary['unmapped_user_levels'])->map(fn ($count, $level) => [$level, $count])->values()->all());
    }

    if ($summary['unmapped_pims_roles'] !== []) {
        $this->warn('Unmapped pims_role values fell back to employee unless user_level mapped higher:');
        $this->table(['pims_role', 'accounts'], collect($summary['unmapped_pims_roles'])->map(fn ($count, $role) => [$role, $count])->values()->all());
    }

    if ($this->option('show-assignments') && $summary['assignments'] !== []) {
        $this->table(
            ['userid', 'emp_id', 'user_level', 'pims_role', 'roles'],
            collect($summary['assignments'])
                ->map(fn (array $assignment) => [
                    $assignment['userid'],
                    $assignment['emp_id'],
                    $assignment['user_level'],
                    $assignment['pims_role'],
                    implode(', ', $assignment['roles']),
                ])
                ->all(),
        );
    }

    if (! $apply) {
        $this->comment('Re-run with --apply after reviewing the dry-run summary.');
    }

    return $summary['missing_roles'] === [] ? 0 : 1;
})->purpose('Safely infer RBAC roles for existing HRIS accounts from legacy user_level and pims_role values.');

Schedule::command('schedule:generate-next-month-draft')->dailyAt('00:15');
