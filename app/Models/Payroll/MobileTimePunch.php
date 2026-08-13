<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class MobileTimePunch extends Model
{
    public const TYPE_TIME_IN = 'time_in';

    public const TYPE_TIME_OUT = 'time_out';

    protected $connection = 'payroll';

    protected $table = 'mobile_time_punches';

    protected $fillable = [
        'emp_id',
        'dtr_id',
        'punch_type',
        'latitude',
        'longitude',
        'device_timestamp',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'device_timestamp' => 'datetime',
        'recorded_at' => 'datetime',
    ];
}
