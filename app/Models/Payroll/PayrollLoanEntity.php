<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLoanEntity extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_loan_entities';

    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function loanTypes(): HasMany
    {
        return $this->hasMany(PayrollLoanType::class, 'entity_id');
    }
}
