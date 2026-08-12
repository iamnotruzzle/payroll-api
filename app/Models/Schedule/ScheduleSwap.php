<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSwap extends PayrollSchedulerModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'department_id',
        'requester_emp_id',
        'responder_emp_id',
        'requester_assignment_id',
        'responder_assignment_id',
        'schedule_date',
        'status',
        'notes',
        'requested_by',
        'requested_at',
        'responded_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'schedule_date' => 'date',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function requesterAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class, 'requester_assignment_id');
    }

    public function responderAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class, 'responder_assignment_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACCEPTED], true);
    }
}
