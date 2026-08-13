<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class EmployeePayrollProfile extends Model
{
    protected $connection = 'hris';

    protected $fillable = [
        'emp_id', 'responsibility_center', 'mp2_account_1', 'mp2_account_2',
        'mp2_account_3', 'mp2_account_4', 'lbp_account_no', 'batch_no', 'batch_year', 'fund_type',
    ];

    protected function casts(): array
    {
        return [
            'mp2_account_1' => 'encrypted', 'mp2_account_2' => 'encrypted',
            'mp2_account_3' => 'encrypted', 'mp2_account_4' => 'encrypted',
            'lbp_account_no' => 'encrypted', 'batch_year' => 'integer',
        ];
    }
}
