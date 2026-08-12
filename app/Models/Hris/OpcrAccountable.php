<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcrAccountable extends Model
{
    protected $connection = 'hris';

    protected $table = 'opcr_accountables';

    protected $fillable = [
        'opcr_id',
        'emp_id',
    ];

    protected $casts = [
        'opcr_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function opcr(): BelongsTo
    {
        return $this->belongsTo(Opcr::class, 'opcr_id', 'id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
