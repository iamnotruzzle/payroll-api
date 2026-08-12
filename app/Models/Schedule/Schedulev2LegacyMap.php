<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedulev2LegacyMap extends PayrollSchedulerModel
{
    protected $fillable = [
        'source_table',
        'source_key',
        'target_table',
        'target_id',
        'emp_id',
        'checksum',
        'sync_run_id',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'sync_run_id' => 'integer',
        ];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(Schedulev2SyncRun::class, 'sync_run_id');
    }
}
