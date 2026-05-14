<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollBankTemplateColumn extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_bank_template_columns';

    public $timestamps = false;

    protected $fillable = [
        'template_id',
        'column_key',
        'label',
        'position',
        'width',
    ];

    protected $casts = [
        'position' => 'integer',
        'width'    => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PayrollBankTemplate::class, 'template_id');
    }
}
