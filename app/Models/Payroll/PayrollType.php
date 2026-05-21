<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollType extends Model
{
    public const CODE_GENERAL = 'general';

    public const CODE_HAZARD = 'hazard';

    public const CODE_MEDICARE = 'medicare';

    protected $connection = 'payroll';

    protected $table = 'payroll_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function generationRouteFor(string $code): string
    {
        return match ($code) {
            self::CODE_HAZARD => 'payroll.generation.hazard',
            self::CODE_MEDICARE => 'payroll.generation.medicare',
            default => 'payroll.generation',
        };
    }
}
