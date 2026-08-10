<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeLeaveLog;
use App\Models\Hris\LeaveType;
use App\Support\Hris\LeaveStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /** Legacy tbl_leave_log.action values (from live HRIS). */
    public const ACTION_FILED = 0;

    public const ACTION_APPROVED = 1;

    public const ACTION_DISAPPROVED = 2;

    public const ACTION_CANCELLED = 3;

    public const ACTION_CREDIT_ACCRUAL = 4;

    public const ACTION_CREDIT_UPDATE = 5;

    public const ACTION_CREDIT_DEBIT = 6;

    /** Statuses used for credit ledger rows, not leave applications. */
    public const LEDGER_STATUS_IDS = [4, 5, 6];

    /**
     * @param  array{
     *     emp_id: string,
     *     leave_type: int,
     *     start_date: string,
     *     end_date: string,
     *     filing_date?: string|null,
     *     remarks?: string|null,
     *     days_wpay?: float|int|string|null,
     *     days_wopay?: float|int|string|null,
     *     commutation?: string|null,
     *     leave_spent?: string|null,
     *     leave_spent_to?: string|null
     * }  $data
     */
    public function apply(array $data, string $actionByEmpId): EmployeeLeave
    {
        $employee = $this->employeeOrFail($data['emp_id']);
        $leaveType = $this->leaveTypeOrFail((int) $data['leave_type']);
        [$start, $end] = $this->assertDateRange($data['start_date'], $data['end_date']);

        $daysWpay = $this->nullableFloat($data['days_wpay'] ?? null);
        $daysWopay = $this->nullableFloat($data['days_wopay'] ?? null);

        if ($daysWpay === null && $daysWopay === null) {
            $daysWpay = (float) ($start->diffInDays($end) + 1);
            $daysWopay = 0.0;
        }

        return DB::connection('hris')->transaction(function () use ($data, $actionByEmpId, $employee, $leaveType, $start, $end, $daysWpay, $daysWopay) {
            $leave = EmployeeLeave::query()->create([
                'emp_id' => $employee->emp_id,
                'leave_type' => $leaveType->leave_type_id,
                'leave_spent' => $data['leave_spent'] ?? null,
                'leave_spent_to' => $data['leave_spent_to'] ?? null,
                'commutation' => $data['commutation'] ?? null,
                'filing_date' => $data['filing_date'] ?? now()->toDateString(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'remarks' => $data['remarks'] ?? null,
                'days_wpay' => $daysWpay ?? 0,
                'days_wopay' => $daysWopay ?? 0,
                'status' => LeaveStatuses::idFor(LeaveStatuses::PENDING),
            ]);

            $this->writeLog(
                $leave,
                self::ACTION_FILED,
                $actionByEmpId,
                'Applied for '.($leaveType->leave_name ?: 'leave'),
                $employee,
            );

            return $leave->fresh(['leaveType', 'logs']);
        });
    }

    /**
     * @param  array{
     *     leave_type?: int,
     *     start_date?: string,
     *     end_date?: string,
     *     filing_date?: string|null,
     *     remarks?: string|null,
     *     days_wpay?: float|int|string|null,
     *     days_wopay?: float|int|string|null,
     *     commutation?: string|null,
     *     leave_spent?: string|null,
     *     leave_spent_to?: string|null
     * }  $data
     */
    public function update(EmployeeLeave $leave, array $data, string $actionByEmpId): EmployeeLeave
    {
        $this->assertEditable($leave);

        $leaveType = isset($data['leave_type'])
            ? $this->leaveTypeOrFail((int) $data['leave_type'])
            : $this->leaveTypeOrFail((int) $leave->leave_type);

        $startDate = $data['start_date'] ?? optional($leave->start_date)?->toDateString() ?? (string) $leave->start_date;
        $endDate = $data['end_date'] ?? optional($leave->end_date)?->toDateString() ?? (string) $leave->end_date;
        [$start, $end] = $this->assertDateRange($startDate, $endDate);

        return DB::connection('hris')->transaction(function () use ($leave, $data, $actionByEmpId, $leaveType, $start, $end) {
            $leave->forceFill([
                'leave_type' => $leaveType->leave_type_id,
                'leave_spent' => array_key_exists('leave_spent', $data) ? $data['leave_spent'] : $leave->leave_spent,
                'leave_spent_to' => array_key_exists('leave_spent_to', $data) ? $data['leave_spent_to'] : $leave->leave_spent_to,
                'commutation' => array_key_exists('commutation', $data) ? $data['commutation'] : $leave->commutation,
                'filing_date' => $data['filing_date'] ?? $leave->filing_date,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $leave->remarks,
                'days_wpay' => array_key_exists('days_wpay', $data) ? ($this->nullableFloat($data['days_wpay']) ?? 0) : $leave->days_wpay,
                'days_wopay' => array_key_exists('days_wopay', $data) ? ($this->nullableFloat($data['days_wopay']) ?? 0) : $leave->days_wopay,
            ])->save();

            $this->writeLog($leave, self::ACTION_FILED, $actionByEmpId, 'Leave application updated.');

            return $leave->fresh(['leaveType', 'logs']);
        });
    }

    public function cancel(EmployeeLeave $leave, string $actionByEmpId, ?string $remarks = null): EmployeeLeave
    {
        $key = LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null);

        if (in_array($key, [LeaveStatuses::CANCELLED, LeaveStatuses::DISAPPROVED], true)) {
            throw ValidationException::withMessages([
                'leave' => 'This leave request is already closed.',
            ]);
        }

        return DB::connection('hris')->transaction(function () use ($leave, $actionByEmpId, $remarks, $key) {
            $employee = $this->employeeOrFail($leave->emp_id);

            if ($key === LeaveStatuses::APPROVED) {
                $this->restoreCredits($employee, $leave);
            }

            $leave->forceFill([
                'status' => LeaveStatuses::idFor(LeaveStatuses::CANCELLED),
            ])->save();

            $this->writeLog(
                $leave,
                self::ACTION_CANCELLED,
                $actionByEmpId,
                $remarks ?: 'Cancel leave application',
                $employee,
            );

            return $leave->fresh(['leaveType', 'logs']);
        });
    }

    public function approve(EmployeeLeave $leave, string $actionByEmpId, ?string $remarks = null): EmployeeLeave
    {
        $this->assertPending($leave);

        return DB::connection('hris')->transaction(function () use ($leave, $actionByEmpId, $remarks) {
            $employee = $this->employeeOrFail($leave->emp_id);
            $this->deductCredits($employee, $leave);

            $leave->forceFill([
                'status' => LeaveStatuses::idFor(LeaveStatuses::APPROVED),
            ])->save();

            $this->writeLog(
                $leave,
                self::ACTION_APPROVED,
                $actionByEmpId,
                $remarks ?: 'Approved leave application',
                $employee,
            );

            return $leave->fresh(['leaveType', 'logs']);
        });
    }

    public function disapprove(EmployeeLeave $leave, string $actionByEmpId, ?string $remarks = null): EmployeeLeave
    {
        $this->assertPending($leave);

        return DB::connection('hris')->transaction(function () use ($leave, $actionByEmpId, $remarks) {
            $employee = $this->employeeOrFail($leave->emp_id);

            $leave->forceFill([
                'status' => LeaveStatuses::idFor(LeaveStatuses::DISAPPROVED),
            ])->save();

            $this->writeLog(
                $leave,
                self::ACTION_DISAPPROVED,
                $actionByEmpId,
                $remarks ?: 'Disapproved leave application',
                $employee,
            );

            return $leave->fresh(['leaveType', 'logs']);
        });
    }

    /**
     * @param  array{vacation_leave_credits?: float|int|string|null, sick_leave_credits?: float|int|string|null, remarks?: string|null}  $data
     */
    public function updateCredits(string $empId, array $data, string $actionByEmpId): Employee
    {
        $employee = $this->employeeOrFail($empId);

        return DB::connection('hris')->transaction(function () use ($employee, $data, $actionByEmpId) {
            $beforeVl = (float) $employee->vacation_leave_credits;
            $beforeSl = (float) $employee->sick_leave_credits;

            if (array_key_exists('vacation_leave_credits', $data) && $data['vacation_leave_credits'] !== null && $data['vacation_leave_credits'] !== '') {
                $employee->vacation_leave_credits = round((float) $data['vacation_leave_credits'], 3);
            }
            if (array_key_exists('sick_leave_credits', $data) && $data['sick_leave_credits'] !== null && $data['sick_leave_credits'] !== '') {
                $employee->sick_leave_credits = round((float) $data['sick_leave_credits'], 3);
            }

            $employee->save();

            $vlDelta = (float) $employee->vacation_leave_credits - $beforeVl;
            $slDelta = (float) $employee->sick_leave_credits - $beforeSl;
            $action = ($vlDelta + $slDelta) >= 0 ? self::ACTION_CREDIT_UPDATE : self::ACTION_CREDIT_DEBIT;
            $status = ($vlDelta + $slDelta) >= 0
                ? LeaveStatuses::idFor('update (credit)') ?? 5
                : LeaveStatuses::idFor('update (debit)') ?? 6;

            $ledger = EmployeeLeave::query()->create([
                'emp_id' => $employee->emp_id,
                'leave_type' => $this->creditLeaveTypeId(),
                'leave_spent' => '',
                'leave_spent_to' => '',
                'commutation' => '',
                'filing_date' => now()->toDateString(),
                'start_date' => null,
                'end_date' => null,
                'remarks' => $data['remarks'] ?? null,
                'days_wpay' => 0,
                'days_wopay' => 0,
                'status' => $status,
            ]);

            EmployeeLeaveLog::query()->create([
                'leave_id' => $ledger->leave_id,
                'emp_id' => $employee->emp_id,
                'action' => $action,
                'credits' => abs($vlDelta) + abs($slDelta),
                'vlc' => (float) $employee->vacation_leave_credits,
                'slc' => (float) $employee->sick_leave_credits,
                'remarks' => trim(sprintf(
                    'VL Updated by %s (%.3f→%.3f), SL (%.3f→%.3f). %s',
                    $actionByEmpId,
                    $beforeVl,
                    (float) $employee->vacation_leave_credits,
                    $beforeSl,
                    (float) $employee->sick_leave_credits,
                    (string) ($data['remarks'] ?? ''),
                )),
                'action_by' => $actionByEmpId,
            ]);

            return $employee->fresh();
        });
    }

    /**
     * Accrue monthly VL/SL for employment statuses eligible in config/hris.php.
     * Respects date_hired (no accrual before hire; hire month uses tbl_leave_earned prorata).
     * Optional $vlDays/$slDays override the per-employee rate when both are provided and > 0
     * and the employee is not in hire-month prorata mode.
     *
     * @return array{updated: int, skipped: int, dry_run: bool}
     */
    public function accrueMonthlyCredits(float $vlDays = 1.25, float $slDays = 1.25, bool $dryRun = true, ?string $actionBy = 'system:leave-accrual'): array
    {
        $employees = Employee::query()
            ->where('is_active', 'Y')
            ->get([
                'emp_id',
                'vacation_leave_credits',
                'sick_leave_credits',
                'empstat_id',
                'position_id',
                'date_hired',
                'is_active',
            ]);

        $updated = 0;
        $skipped = 0;
        $periodLabel = now()->format('F Y');
        $leaveTypeId = $this->creditLeaveTypeId();
        $gainStatus = LeaveStatuses::idFor('gain') ?? 4;
        $computer = app(LeaveCreditComputationService::class);
        $asOf = CarbonImmutable::now()->startOfDay();

        foreach ($employees as $employee) {
            [$eligible, $reason, $creditDays] = $computer->monthlyAccrualFor($employee, $asOf);

            if (! $eligible || $creditDays <= 0) {
                $skipped++;

                continue;
            }

            $hired = $employee->date_hired
                ? CarbonImmutable::parse($employee->date_hired)->startOfDay()
                : null;
            $isHireMonth = $hired && $hired->format('Y-m') === $asOf->format('Y-m');

            // Flat CLI overrides apply only for full-month accrual (not hire-month prorata).
            $addVl = $isHireMonth ? $creditDays : (($vlDays > 0) ? $vlDays : $creditDays);
            $addSl = $isHireMonth ? $creditDays : (($slDays > 0) ? $slDays : $creditDays);

            // When overrides match defaults, prefer the status-aware rate from the computer.
            $defaultRate = (float) (config('hris.leave_credits.monthly_vl') ?: 1.25);
            if (! $isHireMonth && abs($vlDays - $defaultRate) < 0.0005 && abs($slDays - $defaultRate) < 0.0005) {
                $addVl = $creditDays;
                $addSl = $creditDays;
            }

            if ($addVl <= 0 && $addSl <= 0) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $updated++;

                continue;
            }

            DB::connection('hris')->transaction(function () use ($employee, $addVl, $addSl, $actionBy, $periodLabel, $leaveTypeId, $gainStatus) {
                $employee->vacation_leave_credits = round(((float) $employee->vacation_leave_credits) + $addVl, 3);
                $employee->sick_leave_credits = round(((float) $employee->sick_leave_credits) + $addSl, 3);
                $employee->date_gain_lc = now();
                $employee->save();

                $ledger = EmployeeLeave::query()->create([
                    'emp_id' => $employee->emp_id,
                    'leave_type' => $leaveTypeId,
                    'leave_spent' => '',
                    'leave_spent_to' => '',
                    'commutation' => '',
                    'filing_date' => now()->startOfMonth()->toDateString(),
                    'start_date' => null,
                    'end_date' => null,
                    'remarks' => null,
                    'days_wpay' => 0,
                    'days_wopay' => 0,
                    'status' => $gainStatus,
                ]);

                EmployeeLeaveLog::query()->create([
                    'leave_id' => $ledger->leave_id,
                    'emp_id' => $employee->emp_id,
                    'action' => self::ACTION_CREDIT_ACCRUAL,
                    'credits' => $addVl + $addSl,
                    'vlc' => (float) $employee->vacation_leave_credits,
                    'slc' => (float) $employee->sick_leave_credits,
                    'remarks' => sprintf('Gain VL and SL for %s', $periodLabel),
                    'action_by' => $actionBy,
                ]);
            });

            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    public function isPending(EmployeeLeave $leave): bool
    {
        return LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null) === LeaveStatuses::PENDING
            && ! $leave->logs()->whereIn('action', [self::ACTION_CANCELLED, self::ACTION_DISAPPROVED])->exists();
    }

    public function isApproved(EmployeeLeave $leave): bool
    {
        return LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null) === LeaveStatuses::APPROVED
            && ! $leave->logs()->whereIn('action', [self::ACTION_CANCELLED, self::ACTION_DISAPPROVED])->exists();
    }

    public function creditBucketFor(EmployeeLeave $leave): string
    {
        return $this->resolveCreditBucket($this->leaveTypeOrFail((int) $leave->leave_type));
    }

    private function deductCredits(Employee $employee, EmployeeLeave $leave): void
    {
        $days = (float) ($leave->days_wpay ?? 0);
        if ($days <= 0) {
            return;
        }

        $bucket = $this->resolveCreditBucket($this->leaveTypeOrFail((int) $leave->leave_type));

        if ($bucket === 'SL') {
            if ((float) $employee->sick_leave_credits < $days) {
                throw ValidationException::withMessages([
                    'credits' => 'Insufficient sick leave credits for approval.',
                ]);
            }
            $employee->sick_leave_credits = round((float) $employee->sick_leave_credits - $days, 3);
        } elseif ($bucket === 'VL') {
            if ((float) $employee->vacation_leave_credits < $days) {
                throw ValidationException::withMessages([
                    'credits' => 'Insufficient vacation leave credits for approval.',
                ]);
            }
            $employee->vacation_leave_credits = round((float) $employee->vacation_leave_credits - $days, 3);
        } else {
            return;
        }

        $employee->save();
    }

    private function restoreCredits(Employee $employee, EmployeeLeave $leave): void
    {
        $days = (float) ($leave->days_wpay ?? 0);
        if ($days <= 0) {
            return;
        }

        $bucket = $this->resolveCreditBucket($this->leaveTypeOrFail((int) $leave->leave_type));

        if ($bucket === 'SL') {
            $employee->sick_leave_credits = round((float) $employee->sick_leave_credits + $days, 3);
        } elseif ($bucket === 'VL') {
            $employee->vacation_leave_credits = round((float) $employee->vacation_leave_credits + $days, 3);
        } else {
            return;
        }

        $employee->save();
    }

    private function writeLog(
        EmployeeLeave $leave,
        int $action,
        string $actionByEmpId,
        string $remarks,
        ?Employee $employee = null,
    ): void {
        $employee ??= Employee::query()->where('emp_id', $leave->emp_id)->first();

        EmployeeLeaveLog::query()->create([
            'leave_id' => $leave->leave_id,
            'emp_id' => $leave->emp_id,
            'action' => $action,
            'credits' => (float) ($leave->days_wpay ?? 0),
            'vlc' => (float) ($employee?->vacation_leave_credits ?? 0),
            'slc' => (float) ($employee?->sick_leave_credits ?? 0),
            'remarks' => $remarks,
            'action_by' => $actionByEmpId,
        ]);
    }

    private function resolveCreditBucket(LeaveType $leaveType): string
    {
        $name = strtolower((string) $leaveType->leave_name);
        if (str_contains($name, 'sick') || preg_match('/\bsl\b/', $name)) {
            return 'SL';
        }
        if (str_contains($name, 'vacation') || preg_match('/\bvl\b/', $name)) {
            return 'VL';
        }

        return 'NONE';
    }

    private function creditLeaveTypeId(): int
    {
        $type = LeaveType::query()
            ->where(function ($query) {
                $query->where('leave_name', 'like', '%credit%')
                    ->orWhere('leave_name', 'like', '%gain%')
                    ->orWhere('leave_name', 'like', '%vacation%');
            })
            ->orderBy('leave_type_id')
            ->first();

        return (int) ($type?->leave_type_id ?? 14);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function assertDateRange(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the start date.',
            ]);
        }

        return [$start, $end];
    }

    private function assertPending(EmployeeLeave $leave): void
    {
        if (! $this->isPending($leave)) {
            throw ValidationException::withMessages([
                'leave' => 'Only pending leave requests can be approved or disapproved.',
            ]);
        }
    }

    private function assertEditable(EmployeeLeave $leave): void
    {
        if (! $this->isPending($leave)) {
            throw ValidationException::withMessages([
                'leave' => 'Only pending leave requests can be edited.',
            ]);
        }
    }

    private function employeeOrFail(string $empId): Employee
    {
        $employee = Employee::query()->where('emp_id', $empId)->first();
        if (! $employee) {
            throw ValidationException::withMessages([
                'emp_id' => 'Employee not found.',
            ]);
        }

        return $employee;
    }

    private function leaveTypeOrFail(int $leaveTypeId): LeaveType
    {
        $leaveType = LeaveType::query()->where('leave_type_id', $leaveTypeId)->first();
        if (! $leaveType) {
            throw ValidationException::withMessages([
                'leave_type' => 'Leave type not found.',
            ]);
        }

        return $leaveType;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 3);
    }
}
