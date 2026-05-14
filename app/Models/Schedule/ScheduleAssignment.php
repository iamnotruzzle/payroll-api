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
        'schedule_date',
        'shift_code_id',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return ['schedule_date' => 'date'];
    }

    public function monthlySchedule(): BelongsTo
    {
        return $this->belongsTo(MonthlySchedule::class);
    }

    public function shiftCode(): BelongsTo
    {
        return $this->belongsTo(ShiftCode::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'emp_id');
    }
}
