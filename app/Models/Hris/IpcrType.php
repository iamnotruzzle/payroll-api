<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;

class IpcrType extends Model
{
    protected $connection = 'hris';

    protected $table = 'ipcr_types';

    protected $fillable = [
        'type',
        'remarks',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
