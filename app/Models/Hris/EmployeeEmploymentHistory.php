<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEmploymentHistory extends Model
{
    protected $connection = 'hris';

    protected $table = 'employee_employment_history';

    protected $fillable = [
        'emp_id',
        'effective_from',
        'effective_to',
        'item_number',
        'position_id',
        'department_id',
        'empstat_id',
        'step',
        'salary_grade',
        'nature',
        'remarks',
        'recorded_by_emp_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'position_id' => 'integer',
            'department_id' => 'integer',
            'empstat_id' => 'integer',
            'step' => 'integer',
            'salary_grade' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id', 'position_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class, 'empstat_id', 'empstat_id');
    }

    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }
}
