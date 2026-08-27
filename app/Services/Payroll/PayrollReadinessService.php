<?php

namespace App\Services\Payroll;

use App\Models\Payroll\Canonical\Employee;
use App\Models\Payroll\Canonical\SalaryRate;
use App\Models\Payroll\PayrollSourceBatch;
use App\Models\Payroll\PayrollUserAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollReadinessService
{
    public function check(?string $period = null): array
    {
        $errors = [];
        if (! Schema::connection('payroll')->hasTable('payroll_canonical_employees')) {
            return ['ready' => false, 'errors' => ['Canonical payroll migrations have not been run.'], 'counts' => []];
        }
        $counts = ['active_employees' => Employee::query()->where('is_active', true)->count(), 'salary_rates' => SalaryRate::query()->count(), 'active_accounts' => PayrollUserAccount::query()->where('is_active', true)->count()];
        $counts['leave_types'] = DB::connection('payroll')->table('payroll_canonical_leave_types')->where('is_active', true)->count();
        $counts['timekeeping_periods'] = DB::connection('payroll')->table('payroll_canonical_timekeeping')->distinct()->count('period');
        if (! $counts['active_employees']) {
            $errors[] = 'No active employees are loaded.';
        }
        if (! $counts['salary_rates']) {
            $errors[] = 'No salary schedule is loaded.';
        }
        if (! $counts['active_accounts']) {
            $errors[] = 'No active local payroll account exists.';
        }
        if (! $counts['leave_types']) {
            $errors[] = 'No leave types are loaded.';
        }
        if (! $counts['timekeeping_periods']) {
            $errors[] = 'No timekeeping period is loaded.';
        }
        if ($period && ! DB::connection('payroll')->table('payroll_canonical_timekeeping')->where('period', $period)->exists()) {
            $errors[] = "No timekeeping data is active for {$period}.";
        }

        $latestBatch = PayrollSourceBatch::query()
            ->select(['id', 'kind', 'source', 'status', 'effective_period', 'activated_at'])
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        return ['ready' => $errors === [], 'errors' => $errors, 'counts' => $counts, 'latest_batch' => $latestBatch];
    }
}
