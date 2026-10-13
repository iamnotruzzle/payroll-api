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
    public function apiGenerationError(): ?string
    {
        if (Schema::connection('payroll')->hasTable('payroll_source_batches')
            && PayrollSourceBatch::query()->where('source', 'hris_api')->where('status', 'active')->exists()) {
            $timekeeping = app(PayrollTimekeepingSourceService::class);
            if ($timekeeping->usesHrisDatabase()) {
                return $timekeeping->databaseError();
            }

            return 'Payroll generation is blocked: the HRIS API contract has no timekeeping endpoint.';
        }

        return null;
    }

    public function check(?string $period = null, bool $requireTimekeeping = true): array
    {
        $errors = [];
        if ($error = $this->apiGenerationError()) {
            $errors[] = $error;
        }
        if (! Schema::connection('payroll')->hasTable('payroll_canonical_employees')) {
            return ['ready' => false, 'errors' => ['Canonical payroll migrations have not been run.'], 'counts' => []];
        }
        $counts = ['active_employees' => Employee::query()->where('is_active', true)->count(), 'salary_rates' => SalaryRate::query()->count(), 'active_accounts' => PayrollUserAccount::query()->where('is_active', true)->count()];
        $counts['leave_types'] = DB::connection('payroll')->table('payroll_canonical_leave_types')->where('is_active', true)->count();
        $counts['timekeeping_periods'] = DB::connection('payroll')->table('payroll_canonical_timekeeping')->distinct()->count('period');
        $hrisTimekeeping = app(PayrollTimekeepingSourceService::class)->usesHrisDatabase();
        if ($hrisTimekeeping) {
            if ($error = app(PayrollTimekeepingSourceService::class)->databaseError()) {
                $errors[] = $error;
            }
            $requireTimekeeping = false; // DTR/MRA readiness is validated for the selected payroll scope.
        }
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
        if ($requireTimekeeping && ! $counts['timekeeping_periods']) {
            $errors[] = 'No timekeeping period is loaded.';
        }
        if ($requireTimekeeping && $period && ! DB::connection('payroll')->table('payroll_canonical_timekeeping')->where('period', $period)->exists()) {
            $errors[] = "No timekeeping data is active for {$period}.";
        }

        $latestBatch = PayrollSourceBatch::query()
            ->select(['id', 'kind', 'source', 'status', 'effective_period', 'activated_at'])
            ->where('status', 'active')
            ->latest('activated_at')
            ->first();

        return ['ready' => $errors === [], 'errors' => array_values(array_unique($errors)), 'counts' => $counts, 'latest_batch' => $latestBatch];
    }
}
