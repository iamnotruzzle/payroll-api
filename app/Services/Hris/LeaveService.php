<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeLeaveCreditLedger;
use App\Models\Hris\EmployeeLeaveLog;
use App\Models\Hris\LeaveType;
use App\Support\Hris\LeaveDates;
use App\Support\Hris\LeaveStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public const ACTION_LWOP_BALANCE = 7;

    /** Statuses used for credit ledger rows, not leave applications. */
    public const LEDGER_STATUS_IDS = [4, 5, 6];

    /**
     * Human-readable label for tbl_leave_log.action (aligned with tbl_leave_status + LWOP).
     */
    public static function actionName(?int $action): string
    {
        if ($action === null) {
            return 'Unknown';
        }

        if ($action === self::ACTION_LWOP_BALANCE) {
            return 'LWOP balance';
        }

        $fromLookup = LeaveStatuses::nameFor($action);
        if ($fromLookup !== '' && ! str_starts_with($fromLookup, 'Status #')) {
            return $fromLookup;
        }

        return match ($action) {
            self::ACTION_FILED => 'Filed / Pending',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_DISAPPROVED => 'Disapproved',
            self::ACTION_CANCELLED => 'Cancelled',
            self::ACTION_CREDIT_ACCRUAL => 'Credit accrual (gain)',
            self::ACTION_CREDIT_UPDATE => 'Credit update',
            self::ACTION_CREDIT_DEBIT => 'Credit debit',
            default => "Action #{$action}",
        };
    }

    /**
     * @param  array{
     *     emp_id: string,
     *     leave_type: int,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     selected_dates?: list<string>|string|null,
     *     date_mode?: string|null,
     *     filing_date?: string|null,
     *     remarks?: string|null,
     *     applicant_note?: string|null,
     *     days_wpay?: float|int|string|null,
     *     days_wopay?: float|int|string|null,
     *     auto_split_credits?: bool|null,
     *     commutation?: string|null,
     *     leave_spent?: string|null,
     *     leave_spent_to?: string|null,
     *     skip_overlap_check?: bool|null
     * }  $data
     */
    public function apply(array $data, string $actionByEmpId): EmployeeLeave
    {
        $employee = $this->employeeOrFail($data['emp_id']);
        $leaveType = $this->leaveTypeOrFail((int) $data['leave_type']);

        $mode = (string) ($data['date_mode'] ?? LeaveDates::MODE_WEEKDAYS);
        $selected = LeaveDates::resolveSelection(
            $mode,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['selected_dates'] ?? null,
        );
        LeaveDates::assertNonEmpty($selected);

        $start = CarbonImmutable::parse($selected[0])->startOfDay();
        $end = CarbonImmutable::parse($selected[array_key_last($selected)])->startOfDay();
        $dateCsv = LeaveDates::toCsv($selected);
        $dayCount = (float) count($selected);

        if (empty($data['skip_overlap_check'])) {
            $this->assertNoOverlap($employee->emp_id, $selected);
        }

        $manualWpay = $this->nullableFloat($data['days_wpay'] ?? null);
        $manualWopay = $this->nullableFloat($data['days_wopay'] ?? null);
        $autoSplit = array_key_exists('auto_split_credits', $data)
            ? (bool) $data['auto_split_credits']
            : ($manualWpay === null && $manualWopay === null);

        return DB::connection('hris')->transaction(function () use (
            $data,
            $actionByEmpId,
            $employee,
            $leaveType,
            $start,
            $end,
            $dateCsv,
            $dayCount,
            $manualWpay,
            $manualWopay,
            $autoSplit,
        ) {
            [$daysWpay, $daysWopay, $borrowedVl] = $autoSplit
                ? $this->splitCredits($employee, $leaveType, $dayCount)
                : [
                    $manualWpay ?? $dayCount,
                    $manualWopay ?? 0.0,
                    0.0,
                ];

            $this->assertDayCountConsistency($dayCount, $daysWpay, $daysWopay, $leaveType);

            $payload = [
                'emp_id' => $employee->emp_id,
                'leave_type' => $leaveType->leave_type_id,
                'leave_spent' => $data['leave_spent'] ?? null,
                'leave_spent_to' => $data['leave_spent_to'] ?? null,
                'commutation' => $data['commutation'] ?? null,
                'filing_date' => $data['filing_date'] ?? now()->toDateString(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'remarks' => $dateCsv,
                'days_wpay' => $daysWpay,
                'days_wopay' => $daysWopay,
                'status' => LeaveStatuses::idFor(LeaveStatuses::PENDING),
            ];

            if ($this->hasApplicantNoteColumn()) {
                $payload['applicant_note'] = $data['applicant_note'] ?? null;
            }

            $leave = EmployeeLeave::query()->create($payload);

            // Legacy parity: deduct with-pay credits on apply (approve only flips status).
            $this->applyCreditImpact(
                $employee,
                $leaveType,
                $daysWpay,
                $borrowedVl,
                deduct: true,
                source: EmployeeLeaveCreditLedger::SOURCE_APPLY,
                leave: $leave,
                recordedBy: $actionByEmpId,
            );

            $this->writeLog(
                $leave,
                self::ACTION_FILED,
                $actionByEmpId,
                'Applied for '.($leaveType->leave_name ?: 'leave'),
                $employee,
                $daysWpay,
            );

            if ($borrowedVl > 0) {
                $this->writeLog(
                    $leave,
                    self::ACTION_FILED,
                    $actionByEmpId,
                    'Borrowed from VL',
                    $employee,
                    $borrowedVl,
                );
            }

            if ($daysWopay >= 1) {
                $this->addLeaveWithoutPayRecord($leave, $employee, $actionByEmpId, $daysWopay);
            }

            return $leave->fresh(['leaveType', 'logs']);
        });
    }

    /**
     * @param  array{
     *     leave_type?: int,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     selected_dates?: list<string>|string|null,
     *     date_mode?: string|null,
     *     filing_date?: string|null,
     *     remarks?: string|null,
     *     applicant_note?: string|null,
     *     days_wpay?: float|int|string|null,
     *     days_wopay?: float|int|string|null,
     *     auto_split_credits?: bool|null,
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

        $employee = $this->employeeOrFail($leave->emp_id);

        $mode = (string) ($data['date_mode'] ?? LeaveDates::MODE_WEEKDAYS);
        $selected = LeaveDates::resolveSelection(
            $mode,
            $data['start_date'] ?? optional($leave->start_date)?->toDateString(),
            $data['end_date'] ?? optional($leave->end_date)?->toDateString(),
            $data['selected_dates'] ?? LeaveDates::for($leave),
        );
        LeaveDates::assertNonEmpty($selected);
        $this->assertNoOverlap($employee->emp_id, $selected, (int) $leave->leave_id);

        $start = CarbonImmutable::parse($selected[0])->startOfDay();
        $end = CarbonImmutable::parse($selected[array_key_last($selected)])->startOfDay();
        $dateCsv = LeaveDates::toCsv($selected);
        $dayCount = (float) count($selected);

        $manualWpay = array_key_exists('days_wpay', $data) ? $this->nullableFloat($data['days_wpay']) : null;
        $manualWopay = array_key_exists('days_wopay', $data) ? $this->nullableFloat($data['days_wopay']) : null;
        $autoSplit = array_key_exists('auto_split_credits', $data)
            ? (bool) $data['auto_split_credits']
            : ($manualWpay === null && $manualWopay === null);

        return DB::connection('hris')->transaction(function () use (
            $leave,
            $data,
            $actionByEmpId,
            $employee,
            $leaveType,
            $start,
            $end,
            $dateCsv,
            $dayCount,
            $manualWpay,
            $manualWopay,
            $autoSplit,
        ) {
            $this->restoreApplyImpact($leave, $employee, $actionByEmpId);

            [$daysWpay, $daysWopay, $borrowedVl] = $autoSplit
                ? $this->splitCredits($employee->fresh(), $leaveType, $dayCount)
                : [
                    $manualWpay ?? $dayCount,
                    $manualWopay ?? 0.0,
                    0.0,
                ];

            $this->assertDayCountConsistency($dayCount, $daysWpay, $daysWopay, $leaveType);

            $fill = [
                'leave_type' => $leaveType->leave_type_id,
                'leave_spent' => array_key_exists('leave_spent', $data) ? $data['leave_spent'] : $leave->leave_spent,
                'leave_spent_to' => array_key_exists('leave_spent_to', $data) ? $data['leave_spent_to'] : $leave->leave_spent_to,
                'commutation' => array_key_exists('commutation', $data) ? $data['commutation'] : $leave->commutation,
                'filing_date' => $data['filing_date'] ?? $leave->filing_date,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'remarks' => $dateCsv,
                'days_wpay' => $daysWpay,
                'days_wopay' => $daysWopay,
            ];

            if ($this->hasApplicantNoteColumn() && array_key_exists('applicant_note', $data)) {
                $fill['applicant_note'] = $data['applicant_note'];
            }

            $leave->forceFill($fill)->save();

            $this->applyCreditImpact(
                $employee,
                $leaveType,
                $daysWpay,
                $borrowedVl,
                deduct: true,
                source: EmployeeLeaveCreditLedger::SOURCE_APPLY,
                leave: $leave,
                recordedBy: $actionByEmpId,
                remarks: 'Leave application updated',
            );

            $this->writeLog($leave, self::ACTION_FILED, $actionByEmpId, 'Leave application updated.', $employee, $daysWpay);

            if ($borrowedVl > 0) {
                $this->writeLog($leave, self::ACTION_FILED, $actionByEmpId, 'Borrowed from VL', $employee, $borrowedVl);
            }

            if ($daysWopay >= 1) {
                $this->addLeaveWithoutPayRecord($leave, $employee, $actionByEmpId, $daysWopay);
            }

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

        return DB::connection('hris')->transaction(function () use ($leave, $actionByEmpId, $remarks) {
            $employee = $this->employeeOrFail($leave->emp_id);
            $this->restoreApplyImpact($leave, $employee, $actionByEmpId);

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

            // Credits already deducted on apply (legacy parity). Approve only flips status.
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
            $this->restoreApplyImpact($leave, $employee, $actionByEmpId);

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

            $log = EmployeeLeaveLog::query()->create([
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

            $this->postCreditLedgerDeltas(
                $employee,
                $beforeVl,
                $beforeSl,
                EmployeeLeaveCreditLedger::SOURCE_MANUAL,
                leaveId: (int) $ledger->leave_id,
                leaveLogId: (int) $log->log_id,
                recordedBy: $actionByEmpId,
                remarks: (string) ($data['remarks'] ?? 'Manual leave credit update'),
                effectiveDate: now()->toDateString(),
            );

            return $employee->fresh();
        });
    }

    /**
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

            $addVl = $isHireMonth ? $creditDays : (($vlDays > 0) ? $vlDays : $creditDays);
            $addSl = $isHireMonth ? $creditDays : (($slDays > 0) ? $slDays : $creditDays);

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
                [$appliedVl, $remainingLwop] = $this->payDownLwopDebt($employee, $addVl, $actionBy);

                $beforeVl = (float) $employee->vacation_leave_credits;
                $beforeSl = (float) $employee->sick_leave_credits;

                $employee->vacation_leave_credits = round(((float) $employee->vacation_leave_credits) + $appliedVl, 3);
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

                $log = EmployeeLeaveLog::query()->create([
                    'leave_id' => $ledger->leave_id,
                    'emp_id' => $employee->emp_id,
                    'action' => self::ACTION_CREDIT_ACCRUAL,
                    'credits' => $appliedVl + $addSl,
                    'vlc' => (float) $employee->vacation_leave_credits,
                    'slc' => (float) $employee->sick_leave_credits,
                    'remarks' => sprintf('Gain VL and SL for %s', $periodLabel),
                    'action_by' => $actionBy,
                ]);

                $this->postCreditLedgerDeltas(
                    $employee,
                    $beforeVl,
                    $beforeSl,
                    EmployeeLeaveCreditLedger::SOURCE_ACCRUAL,
                    leaveId: (int) $ledger->leave_id,
                    leaveLogId: (int) $log->log_id,
                    recordedBy: $actionBy,
                    remarks: sprintf('Gain VL and SL for %s', $periodLabel),
                    effectiveDate: now()->startOfMonth()->toDateString(),
                );

                if ($remainingLwop !== null) {
                    $lwopLog = EmployeeLeaveLog::query()->create([
                        'leave_id' => $ledger->leave_id,
                        'emp_id' => $employee->emp_id,
                        'action' => self::ACTION_LWOP_BALANCE,
                        'credits' => $remainingLwop,
                        'vlc' => (float) $employee->vacation_leave_credits,
                        'slc' => (float) $employee->sick_leave_credits,
                        'remarks' => 'Updated leave without pay balance',
                        'action_by' => $actionBy,
                    ]);

                    // Additive trail for LWOP debt paydown (no VL/SL balance change beyond accrual above).
                    if ($this->creditLedgerEnabled() && abs($addVl - $appliedVl) > 0.0005) {
                        EmployeeLeaveCreditLedger::query()->create([
                            'emp_id' => $employee->emp_id,
                            'bucket' => EmployeeLeaveCreditLedger::BUCKET_VL,
                            'delta' => 0,
                            'balance_after' => (float) $employee->vacation_leave_credits,
                            'effective_date' => now()->startOfMonth()->toDateString(),
                            'source' => EmployeeLeaveCreditLedger::SOURCE_LWOP,
                            'leave_id' => $ledger->leave_id,
                            'leave_log_id' => $lwopLog->log_id,
                            'remarks' => sprintf(
                                'LWOP paydown used %.3f of %.3f VL accrual; remaining debt %.3f',
                                $addVl - $appliedVl,
                                $addVl,
                                $remainingLwop,
                            ),
                            'recorded_by_emp_id' => $actionBy,
                        ]);
                    }
                }
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
        if (LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null) !== LeaveStatuses::PENDING) {
            return false;
        }

        return ! $this->hasTerminalLeaveLog($leave);
    }

    public function isApproved(EmployeeLeave $leave): bool
    {
        if (LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null) !== LeaveStatuses::APPROVED) {
            return false;
        }

        return ! $this->hasTerminalLeaveLog($leave);
    }

    private function hasTerminalLeaveLog(EmployeeLeave $leave): bool
    {
        // Prefer list queries that set has_terminal_log (batched lookup, not correlated EXISTS).
        if (array_key_exists('has_terminal_log', $leave->getAttributes())) {
            return (bool) $leave->getAttribute('has_terminal_log');
        }

        if ($leave->relationLoaded('logs')) {
            return $leave->logs->contains(
                fn ($log) => in_array((int) $log->action, [self::ACTION_CANCELLED, self::ACTION_DISAPPROVED], true)
            );
        }

        return $leave->logs()
            ->whereIn('action', [self::ACTION_CANCELLED, self::ACTION_DISAPPROVED])
            ->exists();
    }

    public function creditBucketFor(EmployeeLeave $leave): string
    {
        return $this->resolveCreditBucket($this->leaveTypeOrFail((int) $leave->leave_type));
    }

    /**
     * @return array{0: float, 1: float, 2: float} [days_wpay, days_wopay, borrowed_vl]
     */
    private function splitCredits(Employee $employee, LeaveType $leaveType, float $numdays): array
    {
        $typeId = (int) $leaveType->leave_type_id;
        $extendedMaternityIds = (array) config('hris.leave_credits.extended_maternity_leave_type_ids', [17]);

        if (in_array($typeId, $extendedMaternityIds, true)) {
            return [0.0, $numdays, 0.0];
        }

        $bucket = $this->resolveCreditBucket($leaveType);

        if ($bucket === 'NONE') {
            return [$numdays, 0.0, 0.0];
        }

        $partTimeId = (int) config('hris.leave_credits.part_time_empstat_id', Employee::EMPSTAT_PART_TIME);
        $isPartTime = (int) $employee->empstat_id === $partTimeId;
        $need = $isPartTime ? ($numdays / 2) : $numdays;

        $available = $bucket === 'SL'
            ? (float) $employee->sick_leave_credits
            : (float) $employee->vacation_leave_credits;

        if ($available >= $need) {
            return [$numdays, 0.0, 0.0];
        }

        if ($isPartTime) {
            $wpay = floor($available * 2);
            $wopay = $numdays - $wpay;
        } else {
            $wpay = floor($available);
            $wopay = $numdays - $wpay;
        }

        $borrowed = 0.0;
        if ($bucket === 'SL' && $wopay > 0 && (float) $employee->vacation_leave_credits > 0) {
            $vl = (float) $employee->vacation_leave_credits;
            $shortfallCredits = $isPartTime ? ($wopay / 2) : $wopay;
            if ($vl >= $shortfallCredits) {
                $borrowed = $shortfallCredits;
                $wpay = $numdays;
                $wopay = 0.0;
            } else {
                $borrowed = floor($vl);
                $coveredCalendar = $isPartTime ? ($borrowed * 2) : $borrowed;
                $wopay = max(0, $wopay - $coveredCalendar);
                $wpay = $wpay + $coveredCalendar;
            }
        }

        // Non-SL quota leaves with insufficient credits: reject rather than silent LWOP.
        $rejectIfShort = (array) config('hris.leave_credits.reject_if_insufficient_type_ids', [4, 5, 6, 7]);
        if (in_array($typeId, $rejectIfShort, true) && $wopay > 0) {
            throw ValidationException::withMessages([
                'credits' => 'Insufficient leave credits for this leave type.',
            ]);
        }

        return [(float) $wpay, (float) max(0, $wopay), (float) $borrowed];
    }

    private function applyCreditImpact(
        Employee $employee,
        LeaveType $leaveType,
        float $daysWpay,
        float $borrowedVl,
        bool $deduct,
        string $source = EmployeeLeaveCreditLedger::SOURCE_APPLY,
        ?EmployeeLeave $leave = null,
        ?string $recordedBy = null,
        ?string $remarks = null,
    ): void {
        $beforeVl = (float) $employee->vacation_leave_credits;
        $beforeSl = (float) $employee->sick_leave_credits;

        $bucket = $this->resolveCreditBucket($leaveType);
        $sign = $deduct ? -1 : 1;
        $partTimeId = (int) config('hris.leave_credits.part_time_empstat_id', Employee::EMPSTAT_PART_TIME);
        $isPartTime = (int) $employee->empstat_id === $partTimeId;
        $withPayCredits = $isPartTime ? ($daysWpay / 2) : $daysWpay;

        if ($bucket === 'SL' && $withPayCredits > 0) {
            // SL portion only; borrowed VL is charged separately.
            $fromSl = max(0.0, $withPayCredits - $borrowedVl);
            $employee->sick_leave_credits = round((float) $employee->sick_leave_credits + ($sign * $fromSl), 3);
        } elseif ($bucket === 'VL' && $withPayCredits > 0) {
            $employee->vacation_leave_credits = round((float) $employee->vacation_leave_credits + ($sign * $withPayCredits), 3);
        }

        if ($borrowedVl > 0) {
            $employee->vacation_leave_credits = round((float) $employee->vacation_leave_credits + ($sign * $borrowedVl), 3);
        }

        if ($deduct) {
            if ((float) $employee->sick_leave_credits < -0.0005 || (float) $employee->vacation_leave_credits < -0.0005) {
                throw ValidationException::withMessages([
                    'credits' => 'Insufficient leave credits for this application.',
                ]);
            }
        }

        $employee->save();

        $this->postCreditLedgerDeltas(
            $employee,
            $beforeVl,
            $beforeSl,
            $source,
            leaveId: $leave?->leave_id !== null ? (int) $leave->leave_id : null,
            leaveLogId: null,
            recordedBy: $recordedBy,
            remarks: $remarks ?? ($deduct ? 'Leave credit deduction' : 'Leave credit restore'),
            effectiveDate: optional($leave?->filing_date)->format('Y-m-d') ?: now()->toDateString(),
        );
    }

    /**
     * Additive VL/SL ledger rows when a bucket balance changed. Never replaces tbl_leave_log.
     */
    private function postCreditLedgerDeltas(
        Employee $employee,
        float $beforeVl,
        float $beforeSl,
        string $source,
        ?int $leaveId = null,
        ?int $leaveLogId = null,
        ?string $recordedBy = null,
        ?string $remarks = null,
        ?string $effectiveDate = null,
    ): void {
        if (! $this->creditLedgerEnabled()) {
            return;
        }

        $afterVl = (float) $employee->vacation_leave_credits;
        $afterSl = (float) $employee->sick_leave_credits;
        $date = $effectiveDate ?: now()->toDateString();

        foreach ([
            [EmployeeLeaveCreditLedger::BUCKET_VL, $beforeVl, $afterVl],
            [EmployeeLeaveCreditLedger::BUCKET_SL, $beforeSl, $afterSl],
        ] as [$bucket, $before, $after]) {
            $delta = round($after - $before, 3);
            if (abs($delta) < 0.0005) {
                continue;
            }

            EmployeeLeaveCreditLedger::query()->create([
                'emp_id' => $employee->emp_id,
                'bucket' => $bucket,
                'delta' => $delta,
                'balance_after' => $after,
                'effective_date' => $date,
                'source' => $source,
                'leave_id' => $leaveId,
                'leave_log_id' => $leaveLogId,
                'remarks' => $remarks,
                'recorded_by_emp_id' => $recordedBy,
            ]);
        }
    }

    private function creditLedgerEnabled(): bool
    {
        return (bool) Cache::remember('hris.has_employee_leave_credit_ledger', 60, function () {
            return Schema::connection('hris')->hasTable('employee_leave_credit_ledger');
        });
    }

    private function restoreApplyImpact(EmployeeLeave $leave, Employee $employee, string $actionByEmpId): void
    {
        $leaveType = $this->leaveTypeOrFail((int) $leave->leave_type);
        $borrowed = (float) $leave->logs()
            ->where('action', self::ACTION_FILED)
            ->where('remarks', 'Borrowed from VL')
            ->sum('credits');

        $this->applyCreditImpact(
            $employee,
            $leaveType,
            (float) ($leave->days_wpay ?? 0),
            $borrowed,
            deduct: false,
            source: EmployeeLeaveCreditLedger::SOURCE_RESTORE,
            leave: $leave,
            recordedBy: $actionByEmpId,
            remarks: 'Credits restored',
        );

        EmployeeLeaveLog::query()
            ->where('leave_id', $leave->leave_id)
            ->where('action', self::ACTION_LWOP_BALANCE)
            ->delete();
    }

    private function addLeaveWithoutPayRecord(
        EmployeeLeave $leave,
        Employee $employee,
        string $actionByEmpId,
        float $daysWopay,
    ): void {
        $prior = (float) EmployeeLeaveLog::query()
            ->where('emp_id', $employee->emp_id)
            ->where('action', self::ACTION_LWOP_BALANCE)
            ->orderByDesc('id')
            ->value('credits');

        $log = EmployeeLeaveLog::query()->create([
            'leave_id' => $leave->leave_id,
            'emp_id' => $employee->emp_id,
            'action' => self::ACTION_LWOP_BALANCE,
            'credits' => round($prior + $daysWopay, 3),
            'vlc' => (float) $employee->vacation_leave_credits,
            'slc' => (float) $employee->sick_leave_credits,
            'remarks' => 'Leave Without Pay Balance',
            'action_by' => $actionByEmpId,
        ]);

        if ($this->creditLedgerEnabled()) {
            EmployeeLeaveCreditLedger::query()->create([
                'emp_id' => $employee->emp_id,
                'bucket' => EmployeeLeaveCreditLedger::BUCKET_VL,
                'delta' => 0,
                'balance_after' => (float) $employee->vacation_leave_credits,
                'effective_date' => optional($leave->filing_date)->format('Y-m-d') ?: now()->toDateString(),
                'source' => EmployeeLeaveCreditLedger::SOURCE_LWOP,
                'leave_id' => $leave->leave_id,
                'leave_log_id' => $log->log_id,
                'remarks' => sprintf('LWOP debt +%.3f (balance %.3f); VL/SL unchanged', $daysWopay, $prior + $daysWopay),
                'recorded_by_emp_id' => $actionByEmpId,
            ]);
        }
    }

    /**
     * @return array{0: float, 1: ?float} [vl_to_credit, remaining_lwop_or_null]
     */
    private function payDownLwopDebt(Employee $employee, float $earnedVl, string $actionBy): array
    {
        $latest = EmployeeLeaveLog::query()
            ->where('emp_id', $employee->emp_id)
            ->where('action', self::ACTION_LWOP_BALANCE)
            ->orderByDesc('id')
            ->first();

        if (! $latest || (float) $latest->credits <= 0) {
            return [$earnedVl, null];
        }

        $debt = (float) $latest->credits;
        if ($earnedVl >= $debt) {
            return [$earnedVl - $debt, 0.0];
        }

        return [0.0, round($debt - $earnedVl, 3)];
    }

    /**
     * @param  list<string>  $selectedDates
     */
    private function assertNoOverlap(string $empId, array $selectedDates, ?int $ignoreLeaveId = null): void
    {
        $selected = array_fill_keys($selectedDates, true);
        $pending = LeaveStatuses::idFor(LeaveStatuses::PENDING);
        $approved = LeaveStatuses::idFor(LeaveStatuses::APPROVED);

        $leaves = EmployeeLeave::query()
            ->where('emp_id', $empId)
            ->whereNotNull('start_date')
            ->whereIn('status', array_filter([$pending, $approved], fn ($id) => $id !== null))
            ->when($ignoreLeaveId, fn ($q) => $q->where('leave_id', '!=', $ignoreLeaveId))
            ->get(['leave_id', 'start_date', 'end_date', 'remarks', 'status']);

        foreach ($leaves as $existing) {
            foreach (LeaveDates::for($existing) as $date) {
                if (isset($selected[$date])) {
                    throw ValidationException::withMessages([
                        'selected_dates' => "Leave date {$date} overlaps an existing pending/approved leave (#{$existing->leave_id}).",
                    ]);
                }
            }
        }
    }

    private function assertDayCountConsistency(
        float $selectedCount,
        float $daysWpay,
        float $daysWopay,
        LeaveType $leaveType,
    ): void {
        $typeId = (int) $leaveType->leave_type_id;
        $vlDeductIds = (array) config('hris.leave_credits.vl_deduct_leave_type_ids', [1, 3, 11]);
        $slDeductIds = (array) config('hris.leave_credits.sl_deduct_leave_type_ids', [2, 18]);

        // After part-time / multiplier quirks, only enforce for simple credit leaves without multipliers.
        if (! in_array($typeId, array_merge($vlDeductIds, $slDeductIds), true)) {
            return;
        }

        $sum = round($daysWpay + $daysWopay, 3);
        if (abs($sum - $selectedCount) > 0.001) {
            // Allow part-time half-credit representation: wpay+wopay may equal selected calendar days.
            // If both differ, still accept when sum equals selected (legacy stores calendar days in wpay/wopay).
            throw ValidationException::withMessages([
                'days_wpay' => 'Days with/without pay must equal the number of selected leave dates.',
            ]);
        }
    }

    private function writeLog(
        EmployeeLeave $leave,
        int $action,
        string $actionByEmpId,
        string $remarks,
        ?Employee $employee = null,
        ?float $credits = null,
    ): void {
        $employee ??= Employee::query()->where('emp_id', $leave->emp_id)->first();

        EmployeeLeaveLog::query()->create([
            'leave_id' => $leave->leave_id,
            'emp_id' => $leave->emp_id,
            'action' => $action,
            'credits' => $credits ?? (float) ($leave->days_wpay ?? 0),
            'vlc' => (float) ($employee?->vacation_leave_credits ?? 0),
            'slc' => (float) ($employee?->sick_leave_credits ?? 0),
            'remarks' => $remarks,
            'action_by' => $actionByEmpId,
        ]);
    }

    private function resolveCreditBucket(LeaveType $leaveType): string
    {
        $typeId = (int) $leaveType->leave_type_id;
        $vlIds = (array) config('hris.leave_credits.vl_deduct_leave_type_ids', [1, 3, 11]);
        $slIds = (array) config('hris.leave_credits.sl_deduct_leave_type_ids', [2, 18]);

        if (in_array($typeId, $slIds, true)) {
            return 'SL';
        }
        if (in_array($typeId, $vlIds, true)) {
            return 'VL';
        }

        $name = strtolower((string) $leaveType->leave_name);
        if (str_contains($name, 'sick') || preg_match('/\bsl\b/', $name)) {
            return 'SL';
        }
        if (str_contains($name, 'vacation') || preg_match('/\bvl\b/', $name) || str_contains($name, 'forced')) {
            return 'VL';
        }

        return 'NONE';
    }

    private function creditLeaveTypeId(): int
    {
        return (int) (config('hris.leave_credits.gain_leave_type_id') ?: 14);
    }

    private function hasApplicantNoteColumn(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::connection('hris')->hasColumn('tbl_employee_leave', 'applicant_note');
        }

        return $cached;
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
