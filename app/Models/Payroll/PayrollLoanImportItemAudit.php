<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLoanImportItemAudit extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_loan_import_item_audits';

    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'import_item_id',
        'action',
        'old_values',
        'new_values',
        'performed_by',
        'created_at',
        'reverted_at',
        'reverted_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollLoanImportItem::class, 'import_item_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PayrollLoanImport::class, 'import_id');
    }
}
