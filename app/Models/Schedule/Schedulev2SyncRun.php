<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedulev2SyncRun extends PayrollSchedulerModel
{
    protected $fillable = [
        'batch_key',
        'dry_run',
        'from_date',
        'to_date',
        'department_id',
        'division_id',
        'emp_id',
        'limit',
        'status',
        'stats',
        'errors',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'from_date' => 'date',
            'to_date' => 'date',
            'department_id' => 'integer',
            'division_id' => 'integer',
            'limit' => 'integer',
            'stats' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function maps(): HasMany
    {
        return $this->hasMany(Schedulev2LegacyMap::class, 'sync_run_id');
    }
}
