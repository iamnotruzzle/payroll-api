<?php

namespace App\Console\Commands;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\UserAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class HrisPilotReadinessCommand extends Command
{
    protected $signature = 'hris:pilot-readiness
                            {--department= : Optional department_id to spot-check employee coverage}';

    protected $description = 'Verify Employees / Leave / Schedule dual-run cutover readiness for Phase 9 pilots';

    public function handle(): int
    {
        $this->info('HRIS Phase 9 pilot readiness check');
        $this->newLine();

        $failed = 0;
        $failed += $this->checkRoutes([
            'employees.index',
            'employees.create',
            'employees.show',
            'self-service.profile',
            'self-service.leave',
            'leave.requests',
            'leave.approvals',
            'schedule.dashboard',
            'self-service.schedule',
            'training.requests',
            'performance.periods',
        ]) ? 0 : 1;

        $failed += $this->checkTables() ? 0 : 1;
        $failed += $this->checkEmployeeCoverage() ? 0 : 1;

        $this->newLine();
        if ($failed > 0) {
            $this->error("Readiness check finished with {$failed} failing section(s). Fix before dual-run pilot.");

            return self::FAILURE;
        }

        $this->info('Readiness OK. Proceed with dual-run checklist in docs/hris-cutover.md.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $names
     */
    private function checkRoutes(array $names): bool
    {
        $this->line('Routes');
        $ok = true;
        foreach ($names as $name) {
            $exists = Route::has($name);
            $this->line(($exists ? '  [OK] ' : '  [MISS] ').$name);
            $ok = $ok && $exists;
        }

        return $ok;
    }

    private function checkTables(): bool
    {
        $this->newLine();
        $this->line('Legacy HRIS tables');
        $ok = true;
        foreach ([
            'tbl_employee',
            'tbl_useraccount',
            'tbl_employee_leave',
            'tbl_leave_log',
            'tbl_training_details',
            'ipcr_employees',
            'ipcr_calibration_sets',
            'opcrs',
            'employee_documents',
        ] as $table) {
            $exists = Schema::connection('hris')->hasTable($table);
            $this->line(($exists ? '  [OK] ' : '  [MISS] ').'hris.'.$table);
            $ok = $ok && $exists;
        }

        return $ok;
    }

    private function checkEmployeeCoverage(): bool
    {
        $this->newLine();
        $this->line('Coverage snapshot');

        try {
            $active = Employee::query()->where('is_active', 'Y')->count();
            $accounts = UserAccount::query()->count();
            $pendingGate = UserAccount::query()->where(function ($q) {
                $q->whereNull('login_attempt')->orWhere('login_attempt', 0);
            })->count();
            $this->line("  Active employees: {$active}");
            $this->line("  User accounts: {$accounts}");
            $this->line("  Accounts pending profile gate (login_attempt=0): {$pendingGate}");

            $departmentId = $this->option('department');
            if ($departmentId) {
                $department = Department::query()->find($departmentId);
                $count = Employee::query()
                    ->where('department_id', $departmentId)
                    ->where('is_active', 'Y')
                    ->count();
                $label = $department?->department ?? $departmentId;
                $this->line("  Active in department {$label}: {$count}");
            }
        } catch (\Throwable $e) {
            $this->error('  Failed to query HRIS: '.$e->getMessage());

            return false;
        }

        return true;
    }
}
