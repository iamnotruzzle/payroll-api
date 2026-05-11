<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollStatutoryContribution extends Model
{
    protected $connection = 'payroll';
    protected $table = 'payroll_statutory_contributions';

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'split_across_cuts',
        'is_mpf',
        'remarks',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'split_across_cuts' => 'boolean',
        'is_mpf' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
