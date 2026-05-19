<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLoanImport extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_loan_imports';

    public $timestamps = false;

    protected $fillable = [
        'source_entity',
        'billing_period',
        'original_filename',
        'stored_path',
        'imported_by',
        'imported_at',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'status',
    ];

    protected $casts = [
        'billing_period' => 'date',
        'imported_at' => 'datetime',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollLoanImportItem::class, 'import_id');
    }
}
