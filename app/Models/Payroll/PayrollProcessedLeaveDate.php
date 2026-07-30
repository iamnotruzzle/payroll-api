<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollProcessedLeaveDate extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_processed_leave_dates';

    protected $guarded = [];

    protected $casts = ['leave_date' => 'date'];
}
