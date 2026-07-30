<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollExternalEmployeeOverride extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_external_employee_overrides';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];
}
