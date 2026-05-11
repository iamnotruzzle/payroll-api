<?php

namespace App\Models\Schedule;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlySchedule extends PayrollSchedulerModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'department_id',
        'year',
        'month',
        'status',
        'generated_by',
        'generated_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'locked_by',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
