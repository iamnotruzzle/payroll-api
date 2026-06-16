<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveLog extends Model
{
    protected $connection = 'mysql';

    protected $table = 'tbl_leave_log';

    protected $primaryKey = 'log_id';

    protected $fillable = [
        'leave_id',
        'emp_id',
        'action',
        'credits',
        'vlc',
        'slc',
        'remarks',
        'action_by',
    ];

    protected $casts = [
        'action' => 'integer',
        'credits' => 'float',
        'vlc' => 'float',
        'slc' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeave::class, 'leave_id', 'leave_id');
    }
}
