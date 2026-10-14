<?php

namespace App\Models\Payroll;

use App\Models\Hris\EmployeeDtr;
use App\Services\Payroll\PayrollTimekeepingSourceService;

class PayrollEmployeeDtr extends EmployeeDtr
{
    public function getConnectionName()
    {
        return app(PayrollTimekeepingSourceService::class)->usesLocalSnapshot() ? 'payroll' : parent::getConnectionName();
    }

    public function getTable()
    {
        return app(PayrollTimekeepingSourceService::class)->usesLocalSnapshot() ? 'payroll_hris_dtr_snapshots' : parent::getTable();
    }
}
