<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmploymentStatus;
use App\Models\Hris\LeaveType;
use App\Support\Hris\LeaveStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes theoretical leave credits / entitlements from date_hired + empstat.
 *
 * Preview (default): never writes. Apply: sets absolute VL/SL on tbl_employee
 * via LeaveService::updateCredits (ledger row), using earned − known deductions.
 */
class LeaveCreditComputationService
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    /**
     * @return array{
     *     emp_id: string,
     *     status_id: int|null,
     *     status_label: string,
     *     date_hired: string|null,
     *     months_of_service: float,
     *     accrual_eligible: bool,
     *     accrual_skip_reason: string|null,
     *     monthly_rate: float,
     *     vl: array{stored: float, earned: float, used: float, undertime: float, computed: float, delta: float},
     *     sl: array{stored: float, earned: float, used: float, undertime: float, computed: float, delta: float},
     *     entitlements: list<array<string, mixed>>,
     *     notes: list<string>
     * }
     */
    public function computeForEmployee(Employee|string $employee, ?CarbonImmutable $asOf = null): array
    {
        $employee = $employee instanceof Employee
            ? $employee
            : Employee::query()->where('emp_id', $employee)->firstOrFail();

        $asOf ??= CarbonImmutable::now()->startOfDay();
        $cfg = config('hris.leave_credits');

        $statusId = $employee->empstat_id !== null ? (int) $employee->empstat_id : null;
        $statusLabel = $this->statusLabel($statusId);
        $hired = $this->parseHireDate($employee->date_hired);

        [$eligible, $skipReason] = $this->accrualEligibility($employee, $hired, $asOf);
        $rate = $this->monthlyRate($employee);
        $monthsOfService = $hired ? round($hired->floatDiffInMonths($asOf), 2) : 0.0;

        $vlEarned = 0.0;
        $slEarned = 0.0;
        $notes = [];

        if ($eligible && $hired) {
            // Absolute days: hire-month uses tbl_leave_earned (1.25 scale); later months × employee rate.
            $vlEarned = $this->earnedCreditDays($hired, $asOf, $rate);
            $slEarned = $vlEarned;
            $notes[] = 'Hire-month prorata from tbl_leave_earned (days remaining in month); later months at full monthly rate (part-time 0.625).';
        } elseif ($skipReason) {
            $notes[] = $skipReason;
        }

        $vlUsed = $this->sumApprovedDays($employee->emp_id, $cfg['vl_deduct_leave_type_ids'] ?? []);
        $slUsed = $this->sumApprovedDays($employee->emp_id, $cfg['sl_deduct_leave_type_ids'] ?? []);
        $undertime = $this->sumUndertimeDays($employee->emp_id);

        // Undertime historically hits VL only (legacy MenuController::setundertime).
        $vlComputed = round(max(0, $vlEarned - $vlUsed - $undertime), 3);
        $slComputed = round(max(0, $slEarned - $slUsed), 3);

        $vlStored = round((float) ($employee->vacation_leave_credits ?? 0), 3);
        $slStored = round((float) ($employee->sick_leave_credits ?? 0), 3);

        $entitlements = $this->computeEntitlements($employee, $hired, $asOf, $eligible);

        return [
            'emp_id' => (string) $employee->emp_id,
            'full_name' => $employee->full_name,
            'status_id' => $statusId,
            'status_label' => $statusLabel,
            'date_hired' => $hired?->toDateString(),
            'months_of_service' => $monthsOfService,
            'accrual_eligible' => $eligible,
            'accrual_skip_reason' => $skipReason,
            'monthly_rate' => $rate,
            'vl' => [
                'stored' => $vlStored,
                'earned' => $vlEarned,
                'used' => $vlUsed,
                'undertime' => $undertime,
                'computed' => $vlComputed,
                'delta' => round($vlComputed - $vlStored, 3),
            ],
            'sl' => [
                'stored' => $slStored,
                'earned' => $slEarned,
                'used' => $slUsed,
                'undertime' => 0.0,
                'computed' => $slComputed,
                'delta' => round($slComputed - $slStored, 3),
            ],
            'entitlements' => $entitlements,
            'notes' => $notes,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function computeForEmployees(?string $empId = null, ?int $limit = null, ?CarbonImmutable $asOf = null): Collection
    {
        $query = Employee::query()
            ->where('is_active', 'Y')
            ->orderBy('emp_id');

        if ($empId) {
            $query->where('emp_id', $empId);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Employee $employee) => $this->computeForEmployee($employee, $asOf));
    }

    /**
     * Apply absolute VL/SL balances from computation.
     *
     * @return array{updated: int, skipped: int, dry_run: bool, rows: list<array<string, mixed>>}
     */
    public function applyComputedBalances(
        ?string $empId = null,
        ?int $limit = null,
        bool $dryRun = true,
        ?string $actionBy = 'system:leave-recompute',
    ): array {
        $rows = $this->computeForEmployees($empId, $limit);
        $updated = 0;
        $skipped = 0;
        $summary = [];

        foreach ($rows as $row) {
            $summary[] = $row;

            if (! $row['accrual_eligible']) {
                $skipped++;

                continue;
            }

            $sameVl = abs($row['vl']['computed'] - $row['vl']['stored']) < 0.0005;
            $sameSl = abs($row['sl']['computed'] - $row['sl']['stored']) < 0.0005;
            if ($sameVl && $sameSl) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $updated++;

                continue;
            }

            $this->leaveService->updateCredits($row['emp_id'], [
                'vacation_leave_credits' => $row['vl']['computed'],
                'sick_leave_credits' => $row['sl']['computed'],
                'remarks' => sprintf(
                    'Absolute recompute from date_hired (%s) + empstat: VL earned %.3f − used %.3f − undertime %.3f; SL earned %.3f − used %.3f.',
                    $row['date_hired'] ?? 'n/a',
                    $row['vl']['earned'],
                    $row['vl']['used'],
                    $row['vl']['undertime'],
                    $row['sl']['earned'],
                    $row['sl']['used'],
                ),
            ], $actionBy);

            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'rows' => $summary,
        ];
    }

    /**
     * Whether an employee should receive the monthly VL/SL accrual for $asOf.
     *
     * @return array{0: bool, 1: string|null, 2: float} eligible, reason, rate
     */
    public function monthlyAccrualFor(Employee $employee, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now()->startOfDay();
        $hired = $this->parseHireDate($employee->date_hired);
        [$eligible, $reason] = $this->accrualEligibility($employee, $hired, $asOf);

        if (! $eligible) {
            return [false, $reason, 0.0];
        }

        // No accrual before hire month; mid-hire month uses prorata units for that month only.
        if ($hired->greaterThan($asOf)) {
            return [false, 'As-of date is before date_hired.', 0.0];
        }

        $rate = $this->monthlyRate($employee);

        if ($hired->format('Y-m') === $asOf->format('Y-m')) {
            $days = $this->hireMonthProrataDays($hired);

            return [$days > 0, $days > 0 ? null : 'Hire-month prorata is zero.', $days];
        }

        return [true, null, $rate];
    }

    public function monthlyRate(Employee $employee): float
    {
        $cfg = config('hris.leave_credits');
        $partTimeId = (int) ($cfg['part_time_empstat_id'] ?? Employee::EMPSTAT_PART_TIME);

        if ((int) $employee->empstat_id === $partTimeId) {
            return (float) ($cfg['part_time_monthly_rate'] ?? 0.625);
        }

        return (float) ($cfg['monthly_vl'] ?? 1.25);
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    public function accrualEligibility(Employee $employee, ?CarbonImmutable $hired, ?CarbonImmutable $asOf = null): array
    {
        $cfg = config('hris.leave_credits');
        $asOf ??= CarbonImmutable::now()->startOfDay();

        if (($employee->is_active ?? null) !== 'Y') {
            return [false, 'Employee is inactive.'];
        }

        if (! $hired) {
            return [false, 'Missing or invalid date_hired.'];
        }

        if ($hired->greaterThan($asOf)) {
            return [false, 'date_hired is in the future relative to as-of date.'];
        }

        $excludedPositions = array_map('intval', $cfg['excluded_position_ids'] ?? []);
        if (in_array((int) $employee->position_id, $excludedPositions, true)) {
            return [false, 'Position is excluded from VL/SL accrual (COS / Technical Assistant).'];
        }

        $allowed = array_map('intval', $cfg['accrual_empstat_ids'] ?? []);
        if (! in_array((int) $employee->empstat_id, $allowed, true)) {
            return [false, 'Employment status is not eligible for monthly VL/SL accrual.'];
        }

        return [true, null];
    }

    /**
     * Absolute VL/SL days earned from hire through as-of month (inclusive).
     * Hire month: tbl_leave_earned lookup (legacy). Later months: full $monthlyRate each.
     */
    public function earnedCreditDays(CarbonImmutable $hired, CarbonImmutable $asOf, float $monthlyRate): float
    {
        if ($hired->greaterThan($asOf)) {
            return 0.0;
        }

        $hireMonthStart = $hired->startOfMonth();
        $asOfMonthStart = $asOf->startOfMonth();
        $prorata = $this->hireMonthProrataDays($hired);

        if ($hireMonthStart->equalTo($asOfMonthStart)) {
            return round($prorata, 3);
        }

        $fullMonthsAfterHire = $hireMonthStart->diffInMonths($asOfMonthStart);

        return round($prorata + ($fullMonthsAfterHire * $monthlyRate), 3);
    }

    public function hireMonthProrataDays(CarbonImmutable $hired): float
    {
        $remainingDays = (int) floor($hired->diffInDays($hired->endOfMonth()));
        $remainingDays = max(0, min(30, $remainingDays));

        if (! Schema::connection('hris')->hasTable('tbl_leave_earned')) {
            $full = (float) (config('hris.leave_credits.monthly_vl') ?: 1.25);

            return round(($remainingDays / 30) * $full, 3);
        }

        $earned = DB::connection('hris')
            ->table('tbl_leave_earned')
            ->where('day_id', $remainingDays)
            ->value('leave_earned');

        if ($earned === null) {
            $earned = DB::connection('hris')
                ->table('tbl_leave_earned')
                ->where('day_id', 30)
                ->value('leave_earned') ?? 1.25;
        }

        return round((float) $earned, 3);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function computeEntitlements(Employee $employee, ?CarbonImmutable $hired, CarbonImmutable $asOf, bool $accrualEligible): array
    {
        $cfg = config('hris.leave_credits');
        $hidden = array_map('intval', $cfg['hidden_leave_type_ids'] ?? []);
        $types = $this->displayLeaveTypes();

        $annualUsage = $this->usageByType(
            $employee->emp_id,
            [LeaveStatuses::idFor(LeaveStatuses::PENDING) ?? 0, LeaveStatuses::idFor(LeaveStatuses::APPROVED) ?? 1],
            $asOf->startOfYear()->toDateString(),
            $asOf->endOfYear()->toDateString(),
        );
        $lifetimeUsage = $this->usageByType(
            $employee->emp_id,
            [LeaveStatuses::idFor(LeaveStatuses::PENDING) ?? 0, LeaveStatuses::idFor(LeaveStatuses::APPROVED) ?? 1],
        );

        $rows = [];

        foreach ($types as $type) {
            $id = (int) $type->leave_type_id;
            if (in_array($id, $hidden, true)) {
                continue;
            }

            if (in_array($id, [1, 2], true)) {
                continue;
            }

            $max = (float) ($type->max_value ?? 0);
            if ($max <= 0) {
                $rows[] = [
                    'leave_type_id' => $id,
                    'leave_name' => (string) $type->leave_name,
                    'max_value' => 0.0,
                    'used' => 0.0,
                    'remaining' => null,
                    'period' => null,
                    'eligible' => null,
                    'eligibility_notes' => ['No fixed max_value; balance not computed.'],
                ];

                continue;
            }

            $period = $cfg['entitlement_period'][$id] ?? 'annual';
            $used = round((float) (($period === 'annual' ? $annualUsage : $lifetimeUsage)[$id] ?? 0), 3);
            [$eligible, $eligNotes] = $this->evaluateEligibility($employee, $hired, $asOf, $id, $accrualEligible);

            $remaining = round(max(0, $max - $used), 3);

            $rows[] = [
                'leave_type_id' => $id,
                'leave_name' => (string) $type->leave_name,
                'max_value' => $max,
                'used' => $used,
                'remaining' => $eligible ? $remaining : 0.0,
                'period' => $period,
                'eligible' => $eligible,
                'eligibility_notes' => $eligNotes,
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, LeaveType>
     */
    private function displayLeaveTypes(): Collection
    {
        static $types = null;

        return $types ??= LeaveType::query()
            ->where('to_display', 1)
            ->orderBy('leave_type_id')
            ->get();
    }

    /**
     * @param  list<int>  $statusIds
     * @return array<int, float>
     */
    private function usageByType(string $empId, array $statusIds, ?string $from = null, ?string $to = null): array
    {
        $query = EmployeeLeave::query()
            ->selectRaw('leave_type, COALESCE(SUM(days_wpay), 0) as days')
            ->where('emp_id', $empId)
            ->whereIn('status', $statusIds)
            ->whereNotNull('start_date')
            ->groupBy('leave_type');

        if ($from && $to) {
            $query->whereBetween('start_date', [$from, $to]);
        }

        return $query->pluck('days', 'leave_type')
            ->map(fn ($days) => (float) $days)
            ->all();
    }

    /**
     * @return array{0: bool, 1: list<string>}
     */
    public function evaluateEligibility(
        Employee $employee,
        ?CarbonImmutable $hired,
        CarbonImmutable $asOf,
        int $leaveTypeId,
        bool $accrualEligible,
    ): array {
        $rules = config('hris.leave_credits.eligibility.'.$leaveTypeId);
        $notes = [];

        if (! is_array($rules) || $rules === []) {
            // Default: annual special leaves that deduct VL still require accrual-eligible status.
            if (! $accrualEligible && ! in_array($leaveTypeId, [7, 9], true)) {
                $notes[] = 'Employment status / position not in VL/SL accrual set; entitlement shown for reference only.';
            }

            return [true, $notes];
        }

        $anyStatus = (bool) ($rules['any_employment_status'] ?? false);
        if (! $anyStatus && ! $accrualEligible) {
            $notes[] = 'Not eligible: employment status/position excluded from plantilla leave accrual.';

            return [false, $notes];
        }

        if (isset($rules['gender'])) {
            $gender = strtoupper(trim((string) ($employee->gender ?? '')));
            $allowed = array_map(fn ($g) => strtoupper(trim((string) $g)), $rules['gender']);
            if ($gender === '') {
                $notes[] = 'Gender missing — cannot confirm eligibility.';

                return [false, $notes];
            }
            $normalizedAllowed = collect($allowed)
                ->flatMap(fn (string $g) => [$g, $g[0] ?? $g])
                ->unique()
                ->all();
            if (! in_array($gender, $normalizedAllowed, true) && ! in_array($gender[0] ?? '', $normalizedAllowed, true)) {
                $notes[] = 'Gender does not match leave-type eligibility.';

                return [false, $notes];
            }
        }

        if (! empty($rules['requires_married'])) {
            $marriedIds = array_map('intval', config('hris.leave_credits.married_civil_stat_ids') ?? [1]);
            if ($employee->civil_stat === null || $employee->civil_stat === '') {
                $notes[] = 'Civil status missing — cannot confirm married requirement.';

                return [false, $notes];
            }
            if (! in_array((int) $employee->civil_stat, $marriedIds, true)) {
                $notes[] = 'Requires married civil status.';

                return [false, $notes];
            }
        }

        if (! empty($rules['requires_solo_parent'])) {
            $flag = strtoupper(trim((string) ($employee->is_soloparent ?? '')));
            if ($flag === '') {
                $notes[] = 'Solo-parent flag missing — cannot confirm eligibility.';

                return [false, $notes];
            }
            if (! in_array($flag, ['Y', '1', 'YES', 'TRUE'], true)) {
                $notes[] = 'Requires solo parent designation.';

                return [false, $notes];
            }
        }

        if (! $hired) {
            $notes[] = 'date_hired missing — service-length gates cannot be verified.';

            return [false, $notes];
        }

        if (isset($rules['min_service_years'])) {
            $years = $hired->floatDiffInYears($asOf);
            if ($years < (float) $rules['min_service_years']) {
                $notes[] = sprintf('Requires at least %s year(s) of service (have %.2f).', $rules['min_service_years'], $years);

                return [false, $notes];
            }
        }

        if (isset($rules['min_service_months'])) {
            $months = $hired->floatDiffInMonths($asOf);
            if ($months < (float) $rules['min_service_months']) {
                $notes[] = sprintf('Requires at least %s month(s) of service (have %.2f).', $rules['min_service_months'], $months);

                return [false, $notes];
            }
        }

        return [true, $notes];
    }

    private function sumApprovedDays(string $empId, array $leaveTypeIds): float
    {
        if ($leaveTypeIds === []) {
            return 0.0;
        }

        $approved = LeaveStatuses::idFor(LeaveStatuses::APPROVED) ?? 1;

        return round((float) EmployeeLeave::query()
            ->where('emp_id', $empId)
            ->whereIn('leave_type', $leaveTypeIds)
            ->where('status', $approved)
            ->sum('days_wpay'), 3);
    }

    private function sumUndertimeDays(string $empId): float
    {
        $typeId = (int) (config('hris.leave_credits.undertime_leave_type_id') ?? 15);

        $fromLeaves = (float) EmployeeLeave::query()
            ->where('emp_id', $empId)
            ->where('leave_type', $typeId)
            ->sum('days_wpay');

        // Legacy undertime often stores the deduction on the leave log credits column.
        $fromLogs = (float) DB::connection('hris')
            ->table('tbl_leave_log as log')
            ->join('tbl_employee_leave as leave', 'leave.leave_id', '=', 'log.leave_id')
            ->where('log.emp_id', $empId)
            ->where('leave.leave_type', $typeId)
            ->where('log.action', LeaveService::ACTION_CREDIT_DEBIT)
            ->sum('log.credits');

        $legacy = max($fromLeaves, $fromLogs);
        if ($legacy > 0) {
            return round($legacy, 3);
        }

        if (Schema::connection('payroll')->hasTable('payroll_leave_credit_adjustments')) {
            return round(abs((float) DB::connection('payroll')
                ->table('payroll_leave_credit_adjustments')
                ->where('emp_id', $empId)
                ->sum('adjustment_days')), 3);
        }

        return 0.0;
    }

    private function parseHireDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            $date = $value instanceof \DateTimeInterface
                ? CarbonImmutable::instance(\Carbon\Carbon::parse($value))
                : CarbonImmutable::parse((string) $value);

            if ($date->year < 1900) {
                return null;
            }

            return $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function statusLabel(?int $statusId): string
    {
        if ($statusId === null) {
            return '—';
        }

        static $cache = null;
        $cache ??= EmploymentStatus::query()->pluck('status', 'empstat_id');

        return (string) ($cache[$statusId] ?? "Status #{$statusId}");
    }
}
