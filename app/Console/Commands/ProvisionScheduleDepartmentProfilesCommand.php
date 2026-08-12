<?php

namespace App\Console\Commands;

use App\Models\Hris\Department;
use App\Models\Schedule\ScheduleDepartmentProfile;
use App\Services\Schedule\ScheduleDivisionService;
use Illuminate\Console\Command;

class ProvisionScheduleDepartmentProfilesCommand extends Command
{
    protected $signature = 'schedule:provision-department-profiles
        {--dry-run : Show what would be created/updated without writing}
        {--apply : Persist profile rows}
        {--cno-only : Only departments under schedule.cno_division_id (default)}
        {--division= : Provision a specific HRIS division_id (overrides --cno-only)}
        {--force : Overwrite existing profiles with mode defaults}
        {--also-simple : Also create missing simple profiles for non-CNO departments}';

    protected $description = 'Seed ScheduleDepartmentProfile rows (CNO nursing flags on; other divisions simple by default)';

    public function handle(ScheduleDivisionService $divisionService): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;

        if ($apply && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('No mode selected. Running --dry-run. Pass --apply to write profiles.');
            $dryRun = true;
        }

        $force = (bool) $this->option('force');
        $alsoSimple = (bool) $this->option('also-simple');
        $divisionOption = $this->option('division');
        $cnoOnly = (bool) $this->option('cno-only') || $divisionOption === null;

        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($divisionOption !== null && $divisionOption !== '') {
            $divisionId = (int) $divisionOption;
            $targets = $divisionService->departmentsForDivision($divisionId);
            $defaults = $divisionService->isCnoDivision($divisionId)
                ? $divisionService->cnoProfileDefaults()
                : $divisionService->simpleProfileDefaults();
            $this->line("Division filter: {$divisionId} (".($divisionService->isCnoDivision($divisionId) ? 'CNO nursing defaults' : 'simple defaults').')');
            [$created, $updated, $skipped] = $this->provisionCollection($targets, $defaults, $force, $dryRun);
        } else {
            $cnoDivisionId = $divisionService->cnoDivisionId();
            $cnoDefaults = $divisionService->cnoProfileDefaults();
            $cnoDepts = $divisionService->departmentsForDivision($cnoDivisionId);
            $this->line("CNO division_id={$cnoDivisionId} — nursing defaults for {$cnoDepts->count()} departments");
            [$created, $updated, $skipped] = $this->provisionCollection($cnoDepts, $cnoDefaults, $force, $dryRun);

            if ($alsoSimple && ! $cnoOnly) {
                // unreachable when cno-only default; --also-simple with no --division
            }

            if ($alsoSimple) {
                $simpleDefaults = $divisionService->simpleProfileDefaults();
                $otherDepts = Department::query()
                    ->where(function ($query) use ($cnoDivisionId) {
                        $query->whereNull('division_id')
                            ->orWhere('division_id', '!=', $cnoDivisionId);
                    })
                    ->orderBy('department_id')
                    ->get(['department_id', 'department', 'division_id']);
                $this->line('Also provisioning missing simple profiles for '.$otherDepts->count().' non-CNO departments');
                [$c2, $u2, $s2] = $this->provisionCollection($otherDepts, $simpleDefaults, false, $dryRun);
                $created += $c2;
                $updated += $u2;
                $skipped += $s2;
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $dryRun ? 'dry-run' : 'apply'],
                ['Created', $created],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Force', $force ? 'yes' : 'no'],
            ]
        );

        if ($dryRun) {
            $this->comment('No rows written. Re-run with --apply when ready.');
        } else {
            $this->info('Department schedule profiles provisioned.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Department>  $departments
     * @param  array<string, bool>  $defaults
     * @return array{0:int,1:int,2:int}
     */
    private function provisionCollection($departments, array $defaults, bool $force, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($departments as $department) {
            $departmentId = (int) $department->department_id;
            $existing = ScheduleDepartmentProfile::query()
                ->where('department_id', $departmentId)
                ->first();

            if (! $existing) {
                $created++;
                $this->line("  + create dept {$departmentId} ({$department->department})");
                if (! $dryRun) {
                    ScheduleDepartmentProfile::query()->create([
                        'department_id' => $departmentId,
                        ...$defaults,
                    ]);
                }

                continue;
            }

            if (! $force) {
                $skipped++;

                continue;
            }

            $changed = false;
            foreach ($defaults as $key => $value) {
                if ((bool) $existing->{$key} !== (bool) $value) {
                    $changed = true;
                    break;
                }
            }

            if (! $changed) {
                $skipped++;

                continue;
            }

            $updated++;
            $this->line("  ~ update dept {$departmentId} ({$department->department})");
            if (! $dryRun) {
                $existing->fill($defaults)->save();
            }
        }

        return [$created, $updated, $skipped];
    }
}
