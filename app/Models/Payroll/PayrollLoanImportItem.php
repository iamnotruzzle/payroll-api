<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLoanImportItem extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_loan_import_items';

    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'row_number',
        'entity',
        'due_month',
        'employee_id',
        'matched_emp_id',
        'employee_name',
        'loan_account_no',
        'loan_type',
        'monthly_amortization',
        'amount_due',
        'outstanding_balance',
        'principal_due',
        'interest_due',
        'penalty_due',
        'remarks',
        'validation_status',
        'validation_errors',
    ];

    protected $casts = [
        'due_month' => 'date',
        'monthly_amortization' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'principal_due' => 'decimal:2',
        'interest_due' => 'decimal:2',
        'penalty_due' => 'decimal:2',
        'validation_errors' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(PayrollLoanImport::class, 'import_id');
    }
}
