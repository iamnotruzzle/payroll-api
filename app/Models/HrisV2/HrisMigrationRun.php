<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\HasMany;

class HrisMigrationRun extends HrisV2Model
{
    protected $fillable = [
        'batch_key',
        'status',
        'source_employee_count',
        'migrated_employee_count',
        'source_section_count',
        'migrated_section_count',
        'checksums',
        'notes',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'checksums' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function maps(): HasMany
    {
        return $this->hasMany(LegacyRecordMap::class, 'migration_run_id');
    }
}
