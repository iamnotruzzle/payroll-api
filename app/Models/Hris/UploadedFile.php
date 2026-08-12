<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedFile extends Model
{
    public const TYPE_SUPPORTING = 1;

    public const TYPE_REPORT = 2;

    protected $connection = 'hris';

    protected $table = 'tbl_uploaded_files';

    protected $fillable = [
        'filename',
        'tag',
        'type',
        'uploaded_by',
        'file_stat',
        'remarks',
    ];

    protected $casts = [
        'type' => 'integer',
        'file_stat' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by', 'emp_id');
    }

    public function trainingDetail(): BelongsTo
    {
        return $this->belongsTo(TrainingDetail::class, 'tag', 'tarf_no');
    }
}
