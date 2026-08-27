<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use Illuminate\Support\Facades\DB;

/**
 * Compatibility fixture source for pre-canonical automated tests only.
 * Production generation is blocked by canonical readiness and never resolves this service.
 */
class LegacyPayrollGenerationTestSource
{
    public function employeeClass(): string
    {
        abort_unless(app()->environment('testing'), 500, 'Canonical employee data is unavailable.');

        return Employee::class;
    }

    public function leaveClass(): string
    {
        abort_unless(app()->environment('testing'), 500, 'Canonical leave data is unavailable.');

        return EmployeeLeave::class;
    }

    public function salaryGroups(string $throughDate)
    {
        abort_unless(app()->environment('testing'), 500, 'Canonical salary data is unavailable.');

        return DB::connection('hris')->table('tbl_salary_grade')
            ->select(['salary_grade', 'step_increment', 'salary', 'effectivity_date'])
            ->whereDate('effectivity_date', '<=', $throughDate)
            ->orderByDesc('effectivity_date')->get()
            ->groupBy(fn ($grade) => $grade->salary_grade.'|'.$grade->step_increment);
    }
}
