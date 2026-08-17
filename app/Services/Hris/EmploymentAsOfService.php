<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeEmploymentHistory;
use Carbon\CarbonImmutable;

class EmploymentAsOfService
{
    /**
     * Return the employee assignment in force on the payroll date.
     *
     * @return array<string, mixed>
     */
    public function resolve(string $empId, CarbonImmutable|string $payrollDate, array $fallback = []): array
    {
        $asOf = $payrollDate instanceof CarbonImmutable
            ? $payrollDate
            : CarbonImmutable::parse($payrollDate);

        $history = EmployeeEmploymentHistory::query()
            ->with(['position', 'department.division'])
            ->where('emp_id', $empId)
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $asOf->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($history) {
            return array_merge($fallback, [
                'position_id' => $history->position_id,
                'position' => $history->position?->position_title ?? ($fallback['position'] ?? null),
                'department_id' => $history->department_id,
                'department' => $history->department?->department ?? ($fallback['department'] ?? null),
                'division' => $history->department?->division?->division ?? ($fallback['division'] ?? null),
                'salary_grade' => $history->salary_grade ?? ($fallback['salary_grade'] ?? null),
                'step' => $history->step ?? ($fallback['step'] ?? null),
                'employment_effective_from' => $history->effective_from?->toDateString(),
                'employment_effective_to' => $history->effective_to?->toDateString(),
            ]);
        }

        $employee = Employee::query()
            ->with(['position', 'department.division'])
            ->where('emp_id', $empId)
            ->first();

        return array_merge([
            'position_id' => $employee?->position_id,
            'position' => $employee?->position?->position_title,
            'department_id' => $employee?->department_id,
            'department' => $employee?->department?->department,
            'division' => $employee?->department?->division?->division,
            'step' => $employee?->step,
        ], $fallback);
    }

    public function payrollDate(?string $period, mixed $snapshotCreatedAt = null): CarbonImmutable
    {
        if (filled($period)) {
            try {
                return CarbonImmutable::parse($period)->endOfMonth()->startOfDay();
            } catch (\Throwable) {
                // Fall through to the snapshot date for malformed legacy periods.
            }
        }

        return $snapshotCreatedAt
            ? CarbonImmutable::parse($snapshotCreatedAt)->startOfDay()
            : CarbonImmutable::today();
    }
}
