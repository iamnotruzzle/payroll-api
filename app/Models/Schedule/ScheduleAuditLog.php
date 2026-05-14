<?php

namespace App\Models\Schedule;

class ScheduleAuditLog extends PayrollSchedulerModel
{
    protected $connection = 'payroll_scheduler';

    public $timestamps = false;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'before_values',
        'after_values',
        'performed_by',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'performed_at' => 'datetime',
        ];
    }
}