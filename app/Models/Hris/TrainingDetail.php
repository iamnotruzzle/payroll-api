<?php

namespace App\Models\Hris;

use App\Casts\SafeCarbonDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingDetail extends Model
{
    protected $connection = 'hris';

    protected $table = 'tbl_training_details';

    protected $primaryKey = 'tarf_no';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tarf_no',
        'training_name',
        'training_venue',
        'sponsor',
        'sponsor_type',
        'start_date',
        'end_date',
        'hrs',
        'type',
        'mode',
        'description',
        'wfpm_no',
        'q2',
        'q3',
        'q4',
        'status',
        'approvedby_petu_id',
        'approvedby_petu',
        'petu_notes',
        'approved_by',
        'approvedby_mcc',
        'mcc_notes',
    ];

    protected $casts = [
        'start_date' => SafeCarbonDate::class,
        'end_date' => SafeCarbonDate::class,
        'hrs' => 'float',
        'sponsor_type' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'q2' => 'integer',
        'q3' => 'integer',
        'q4' => 'integer',
        'approvedby_petu' => 'datetime',
        'approvedby_mcc' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class, 'tarf_no', 'tarf_no');
    }

    public function ldiType(): BelongsTo
    {
        return $this->belongsTo(TrainingTypeLookup::class, 'type', 'id');
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(UploadedFile::class, 'tag', 'tarf_no');
    }

    public function approvedByPetu(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approvedby_petu_id', 'emp_id');
    }

    public function approvedByMcc(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'emp_id');
    }
}
