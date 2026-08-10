<?php

namespace App\Models\HrisV2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyRecordMap extends HrisV2Model
{
    protected $fillable = [
        'source_table',
        'source_key',
        'target_table',
        'target_id',
        'emp_id',
        'checksum',
        'migration_run_id',
    ];

    public function migrationRun(): BelongsTo
    {
        return $this->belongsTo(HrisMigrationRun::class, 'migration_run_id');
    }
}
