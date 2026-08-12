<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcrEmployee extends Model
{
    use SoftDeletes;

    protected $connection = 'hris';

    protected $table = 'ipcr_employees';

    protected $fillable = [
        'emp_id',
        'mfo_set_id',
        'type_id',
        'target',
        'accomplishment',
        'accomplishment_date',
    ];

    protected $casts = [
        'mfo_set_id' => 'integer',
        'type_id' => 'integer',
        'accomplishment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function mfoSet(): BelongsTo
    {
        return $this->belongsTo(IpcrMfoSet::class, 'mfo_set_id', 'id');
    }

    public function ipcrType(): BelongsTo
    {
        return $this->belongsTo(IpcrType::class, 'type_id', 'id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(IpcrRating::class, 'ipcr_id', 'id');
    }
}
