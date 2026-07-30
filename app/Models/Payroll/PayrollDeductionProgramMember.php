<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionProgramMember extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_deduction_program_members';

    protected $guarded = [];
}
