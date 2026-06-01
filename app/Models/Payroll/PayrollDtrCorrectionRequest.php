<?php

namespace App\Models\Payroll;

use App\Models\Hris\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PayrollDtrCorrectionRequest extends Model
{
    public const TYPE_TIME_IN = 'TIME_IN';

    public const TYPE_TIME_OUT = 'TIME_OUT';

    public const TYPE_BOTH = 'BOTH';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $connection = 'payroll';

    protected $table = 'payroll_dtr_correction_requests';

    protected $fillable = [
        'emp_id',
        'department_id',
        'dtr_date',
        'request_type',
        'requested_time_in',
        'requested_time_out',
        'requested_timeout_nextday',
        'reason',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
        'status',
        'requested_by_emp_id',
        'requested_at',
        'approver_emp_id',
        'approved_at',
        'approved_by_emp_id',
        'rejected_at',
        'rejected_by_emp_id',
        'approver_remarks',
        'previous_dtr',
        'applied_dtr',
    ];

    protected $casts = [
        'department_id' => 'integer',
        'dtr_date' => 'date',
        'requested_timeout_nextday' => 'boolean',
        'attachment_size' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'previous_dtr' => 'array',
        'applied_dtr' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path
            ? Storage::disk('public')->url($this->attachment_path)
            : null;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_emp_id', 'emp_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_emp_id', 'emp_id');
    }
}
