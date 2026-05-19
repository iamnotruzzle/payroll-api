<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLoanType extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_loan_types';

    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'review_group',
        'review_column_key',
        'review_column_label',
        'match_keywords',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'match_keywords' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(PayrollLoanEntity::class, 'entity_id');
    }
}
