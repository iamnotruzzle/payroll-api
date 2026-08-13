<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollDeduction extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_deduction';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'is_percentage',
        'value',
        'is_active',
        'sort_order',
        'insert_after_column',
        'section',
        'impact_type',
        'is_recurring',
    ];

    protected $casts = [
        'is_percentage' => 'boolean',
        'is_active' => 'boolean',
        'value' => 'decimal:4',
        'sort_order' => 'integer',
        'is_recurring' => 'boolean',
    ];
}
