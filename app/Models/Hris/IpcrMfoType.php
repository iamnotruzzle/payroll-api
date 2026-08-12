<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class IpcrMfoType extends Model
{
    protected $connection = 'hris';

    protected $table = 'ipcr_mfo_types';

    protected $fillable = [
        'function_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
