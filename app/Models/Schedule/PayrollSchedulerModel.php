<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Model;

abstract class PayrollSchedulerModel extends Model
{

    protected $connection = 'payroll_scheduler';
}
