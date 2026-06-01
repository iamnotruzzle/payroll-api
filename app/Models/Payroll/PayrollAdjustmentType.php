<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayrollAdjustmentType extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_adjustment_types';

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function codeFor(string $name): string
    {
        return Str::of($name)->slug('_')->upper()->limit(64, '')->toString();
    }
}
