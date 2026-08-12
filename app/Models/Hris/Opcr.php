<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opcr extends Model
{
    protected $connection = 'hris';

    protected $table = 'opcrs';

    protected $fillable = [
        'ipcr_id',
        'budget',
        'entry_by',
    ];

    protected $casts = [
        'ipcr_id' => 'integer',
        'budget' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ipcrEmployee(): BelongsTo
    {
        return $this->belongsTo(IpcrEmployee::class, 'ipcr_id', 'id');
    }

    public function accountables(): HasMany
    {
        return $this->hasMany(OpcrAccountable::class, 'opcr_id', 'id');
    }
}
