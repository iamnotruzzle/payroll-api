<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrMfo extends Model
{
    protected $connection = 'hris';

    protected $table = 'ipcr_mfos';

    protected $fillable = [
        'mfo',
        'function_type_id',
    ];

    protected $casts = [
        'function_type_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function functionType(): BelongsTo
    {
        return $this->belongsTo(IpcrMfoType::class, 'function_type_id', 'id');
    }

    public function sets(): HasMany
    {
        return $this->hasMany(IpcrMfoSet::class, 'mfo_id', 'id');
    }
}
