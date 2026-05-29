<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollAdditional extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_additional';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'is_percentage',
        'value',
        'computation_type',
        'formula',
        'variable_name',
        'include_in_net_pay',
        'tax_treatment',
        'annual_exempt_limit',
        'supplemental_tax_rate',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_percentage' => 'boolean',
        'is_active' => 'boolean',
        'include_in_net_pay' => 'boolean',
        'value' => 'decimal:4',
        'annual_exempt_limit' => 'decimal:2',
        'supplemental_tax_rate' => 'decimal:4',
        'sort_order' => 'integer',
    ];
}
