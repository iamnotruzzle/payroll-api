<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcrCalibrationSet extends Model
{
    use SoftDeletes;

    protected $connection = 'hris';

    protected $table = 'ipcr_calibration_sets';

    protected $fillable = [
        'ipcr_employee_id',
        'score',
        'calibration_type',
        'calibration',
        'mfo_id',
    ];

    protected $casts = [
        'ipcr_employee_id' => 'integer',
        'score' => 'integer',
        'mfo_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function ipcrEmployee(): BelongsTo
    {
        return $this->belongsTo(IpcrEmployee::class, 'ipcr_employee_id', 'id');
    }

    public function mfo(): BelongsTo
    {
        return $this->belongsTo(IpcrMfo::class, 'mfo_id', 'id');
    }
}
