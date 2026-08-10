<?php

namespace App\Models\Schedule;

class ScheduleDepartmentProfile extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'uses_units',
        'uses_floaters',
        'uses_on_call',
        'uses_swaps',
        'uses_census',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'uses_units' => 'boolean',
            'uses_floaters' => 'boolean',
            'uses_on_call' => 'boolean',
            'uses_swaps' => 'boolean',
            'uses_census' => 'boolean',
            'meta' => 'array',
        ];
    }

    public static function forDepartment(int|string $departmentId): self
    {
        return static::query()->firstOrNew(
            ['department_id' => $departmentId],
            [
                'uses_units' => false,
                'uses_floaters' => false,
                'uses_on_call' => false,
                'uses_swaps' => false,
                'uses_census' => false,
            ]
        );
    }
}
