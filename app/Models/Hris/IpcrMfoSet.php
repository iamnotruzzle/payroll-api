<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcrMfoSet extends Model
{
    use SoftDeletes;

    protected $connection = 'hris';

    protected $table = 'ipcr_mfo_sets';

    protected $fillable = [
        'mfo_id',
        'period_id',
        'department_id',
        'entry_by',
    ];

    protected $casts = [
        'mfo_id' => 'integer',
        'period_id' => 'integer',
        'department_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function mfo(): BelongsTo
    {
        return $this->belongsTo(IpcrMfo::class, 'mfo_id', 'id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IpcrPeriod::class, 'period_id', 'id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(IpcrEmployee::class, 'mfo_set_id', 'id');
    }
}
