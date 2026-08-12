<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRequest extends Model
{
    protected $connection = 'hris';

    protected $table = 'tbl_training_requests';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tarf_no',
        'emp_id',
        'role',
        'accepted',
        'ob_ot',
    ];

    protected $casts = [
        'role' => 'integer',
        'accepted' => 'integer',
        'ob_ot' => 'integer',
    ];

    public function trainingDetail(): BelongsTo
    {
        return $this->belongsTo(TrainingDetail::class, 'tarf_no', 'tarf_no');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }
}
