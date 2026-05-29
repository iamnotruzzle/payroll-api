<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollGenerationDraft extends Model
{
    protected $connection = 'payroll';

    protected $table = 'payroll_generation_drafts';

    protected $fillable = [
        'configuration_key',
        'division_id',
        'department_id',
        'payroll_type_code',
        'payroll_period',
        'working_days',
        'employee_type',
        'current_step',
        'state_json',
        'saved_by',
        'saved_at',
    ];

    protected $casts = [
        'division_id' => 'integer',
        'department_id' => 'integer',
        'working_days' => 'integer',
        'current_step' => 'integer',
        'state_json' => 'array',
        'saved_at' => 'datetime',
    ];

    public static function configurationKey(
        ?int $divisionId,
        ?int $departmentId,
        string $payrollTypeCode,
        string $period,
        int $workingDays,
        string $employeeType
    ): string {
        return hash('sha256', implode('|', [
            $divisionId ?: 0,
            $departmentId ?: 0,
            $payrollTypeCode,
            $period,
            $workingDays,
            $employeeType,
        ]));
    }
}
