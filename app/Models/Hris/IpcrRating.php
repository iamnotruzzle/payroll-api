<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcrRating extends Model
{
    use SoftDeletes;

    protected $connection = 'hris';

    protected $table = 'ipcr_ratings';

    protected $fillable = [
        'ipcr_id',
        'rating_by',
        'remarks',
        'quality',
        'effectiveness',
        'timeliness',
    ];

    protected $casts = [
        'ipcr_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function ipcrEmployee(): BelongsTo
    {
        return $this->belongsTo(IpcrEmployee::class, 'ipcr_id', 'id');
    }

    public function ratingBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rating_by', 'emp_id');
    }

    public function getAverageAttribute(): ?float
    {
        $scores = array_filter([
            is_numeric($this->quality) ? (float) $this->quality : null,
            is_numeric($this->effectiveness) ? (float) $this->effectiveness : null,
            is_numeric($this->timeliness) ? (float) $this->timeliness : null,
        ], fn ($v) => $v !== null);

        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }
}
