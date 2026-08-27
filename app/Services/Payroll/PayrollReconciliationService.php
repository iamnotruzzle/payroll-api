<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;

class PayrollReconciliationService
{
    public function preview(): array
    {
        $localIds = DB::connection('payroll')->table('payroll_canonical_employees')->pluck('emp_id');
        $sourceIds = DB::connection('hris')->table('tbl_employee')->pluck('emp_id');

        return [
            'local_employee_count' => $localIds->count(),
            'source_employee_count' => $sourceIds->count(),
            'only_local' => $localIds->diff($sourceIds)->values()->take(100)->all(),
            'only_source' => $sourceIds->diff($localIds)->values()->take(100)->all(),
            'local_salary_rates' => DB::connection('payroll')->table('payroll_canonical_salary_rates')->count(),
            'source_salary_rates' => DB::connection('hris')->table('tbl_salary_grade')->count(),
        ];
    }
}
