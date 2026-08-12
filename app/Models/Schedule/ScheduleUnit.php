<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleUnit extends PayrollSchedulerModel
{
    public const TYPES = [
        'ward' => 'Ward',
        'section' => 'Section',
        'clinic' => 'Clinic',
        'office' => 'Office',
        'area' => 'Area',
        'other' => 'Other',
    ];

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'unit_type',
        'sort_order',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class, 'unit_id');
    }

    public function handlers(): HasMany
    {
        return $this->hasMany(ScheduleUserUnit::class, 'schedule_unit_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->unit_type] ?? ucfirst((string) $this->unit_type);
    }
}
