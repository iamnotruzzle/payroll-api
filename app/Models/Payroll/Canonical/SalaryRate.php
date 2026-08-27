<?php

namespace App\Models\Payroll\Canonical;

use Illuminate\Database\Eloquent\Model;

class SalaryRate extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_canonical_salary_rates';

    protected $guarded = [];

    protected $casts = ['effective_from' => 'date', 'effective_to' => 'date', 'salary' => 'float'];
}
