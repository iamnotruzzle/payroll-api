<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollGenerationDraft extends Model
{
    public const LEGACY_WIZARD_STEP_COUNT = 8;

    public const ADDITIONAL_PREMIUM_STEP = 6;

    public const WIZARD_STEPS = [
        1 => 'MRA Validation',
        2 => 'Compensation',
        3 => 'Deductions and Adjustments',
        4 => 'Mandatory Deductions',
        5 => 'Deduction Programs',
        6 => 'Additional Premium',
        7 => 'Loan Deductions',
        8 => 'Tax Calculation',
        9 => 'Review',
    ];

    protected $connection = 'payroll';

    protected $table = 'payroll_generation_drafts';

    protected $fillable = [
        'configuration_key',
        'division_id',
        'department_id',
        'payroll_type_code',
        'payroll_period',
        'working_days',
        'gsis_days',
        'included_leave_type_ids',
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
        'gsis_days' => 'integer',
        'included_leave_type_ids' => 'array',
        'current_step' => 'integer',
        'state_json' => 'array',
        'saved_at' => 'datetime',
    ];

    public static function currentWizardStepCount(): int
    {
        return count(self::WIZARD_STEPS);
    }

    public static function restoredWizardStep(int $currentStep, array $state = []): int
    {
        $step = max(1, $currentStep);
        $savedStepCount = (int) ($state['wizard_step_count'] ?? self::LEGACY_WIZARD_STEP_COUNT);
        $currentStepCount = self::currentWizardStepCount();

        if ($savedStepCount > 0 && $savedStepCount < $currentStepCount && $step >= self::ADDITIONAL_PREMIUM_STEP) {
            $step += $currentStepCount - $savedStepCount;
        }

        return max(1, min($currentStepCount, $step));
    }

    public static function wizardStepLabel(int $step): string
    {
        return self::WIZARD_STEPS[$step] ?? 'Step '.$step;
    }

    public static function configurationKey(
        ?int $divisionId,
        ?int $departmentId,
        string $payrollTypeCode,
        string $period,
        int $workingDays,
        string $employeeType,
        int $gsisDays = 30,
        array $includedLeaveTypeIds = []
    ): string {
        return self::configurationKeyForScope(
            $divisionId ? [$divisionId] : [],
            $departmentId ? [$departmentId] : [],
            $payrollTypeCode,
            $period,
            $workingDays,
            $employeeType,
            $gsisDays,
            $includedLeaveTypeIds,
        );
    }

    public static function configurationKeyForScope(
        array $divisionIds,
        array $departmentIds,
        string $payrollTypeCode,
        string $period,
        int $workingDays,
        string $employeeType,
        int $gsisDays = 30,
        array $includedLeaveTypeIds = []
    ): string {
        $divisionIds = collect($divisionIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(',');
        $departmentIds = collect($departmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(',');
        $includedLeaveTypeIds = collect($includedLeaveTypeIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->implode(',');

        return hash('sha256', implode('|', [
            $divisionIds ?: '0',
            $departmentIds ?: '0',
            $payrollTypeCode,
            $period,
            $workingDays,
            $employeeType,
            $gsisDays,
            $includedLeaveTypeIds,
        ]));
    }
}
