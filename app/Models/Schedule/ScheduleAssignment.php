<?php

namespace App\Models\Schedule;

use App\Models\Hris\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAssignment extends PayrollSchedulerModel
{
    protected $connection = 'payroll_scheduler';

    protected $fillable = [
        'monthly_schedule_id',
        'employee_id',
        'unit_id',
        'is_temporary_floater',
        'schedule_date',
        'shift_code_id',
        'source',
        'notes',
        'legacy_emp_sched_id',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'unit_id' => 'integer',
            'is_temporary_floater' => 'boolean',
            'legacy_emp_sched_id' => 'integer',
        ];
    }

    public function monthlySchedule(): BelongsTo
    {
        return $this->belongsTo(MonthlySchedule::class);
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ScheduleUnit::class, 'unit_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }
}
