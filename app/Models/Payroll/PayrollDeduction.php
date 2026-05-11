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
    ];

    protected $casts = [
        'is_percentage' => 'boolean',
        'is_active' => 'boolean',
        'value' => 'decimal:4',
    ];
}
