<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrPeriod extends Model
{
    protected $connection = 'hris';

    protected $table = 'ipcr_periods';

    protected $fillable = [
        'year',
        'period_type',
        'period',
    ];

    protected $casts = [
        'year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function mfoSets(): HasMany
    {
        return $this->hasMany(IpcrMfoSet::class, 'period_id', 'id');
    }

    public function getLabelAttribute(): string
    {
        $type = ucfirst((string) $this->period_type);
        $period = (string) $this->period;

        return "{$this->year} {$type} {$period}";
    }
}
