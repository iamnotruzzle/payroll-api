<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryGrade;
use App\Models\Payroll\PayrollAdditional;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Models\Payroll\PayrollMraReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class PayrollGeneration extends Component
{
    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public string $search = '';

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public int $currentStep = 1;

    public array $deductionDayOverrides = [];

    public array $steps = [
        1 => 'MRA Validation',
        2 => 'Allowances Computation',
        3 => 'Deductions and Adjustments',
        4 => 'Statutory',
        5 => 'Loan Deductions',
        6 => 'Review',
    ];

    public function mount(): void
    {
        $this->departmentId = auth()->user()?->employee?->department_id;
        $this->period = CarbonImmutable::today()->format('Y-m');
    }

    public function updatedDepartmentId(): void
    {
        $this->resetGenerationState();
    }

    public function updatedPeriod(): void
    {
        $this->resetGenerationState();
    }

    public function updatedEmployeeTypeFilter(): void
    {
        $this->resetGenerationState();
    }

    public function goToStep(int $step): void
    {
        $this->currentStep = max(1, min(6, $step));
    }

    public function nextStep(): void
    {
        $this->goToStep($this->currentStep + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->currentStep - 1);
    }

    public function render()
    {
        $compensations = $this->compensations();
        $rows = $this->payrollRows($compensations);
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);

        return view('livewire.payroll.payroll-generation', [
            'departments' => Department::query()->orderBy('department')->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'compensations' => $compensations,
            'rows' => $rows,
            'previousMraPeriod' => $previousMraPeriod,
            'previousMraReport' => $previousMraReport,
            'totals' => [
                'basic_salary' => $rows->sum('basic_salary'),
                'compensations' => $compensations->mapWithKeys(
                    fn ($item) => [$item->id => $rows->sum(fn ($row) => $row['compensations'][$item->id]['amount'] ?? 0)]
                ),
                'statutory_deductions' => [
                    'life_retirement' => $rows->sum('statutory_deductions.life_retirement'),
                    'phic' => $rows->sum('statutory_deductions.phic'),
                    'mandatory_pagibig' => $rows->sum('statutory_deductions.mandatory_pagibig'),
                ],
                'gross' => $rows->sum('gross'),
                'net_before_other_deductions' => $rows->sum('net_before_other_deductions'),
                'fifteenth' => $rows->sum('fifteenth'),
                'thirtieth' => $rows->sum('thirtieth'),
            ],
        ]);
    }

    private function payrollRows(Collection $compensations): Collection
    {
        if (! $this->departmentId) {
            return collect();
        }

        $employees = Employee::query()
            ->with(['position', 'department'])
            ->where('department_id', $this->departmentId)
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($query) use ($search) {
                    $query->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        $salaryMatrix = $this->salaryMatrix();
        $periodStart = $this->selectedPeriodStart();
        $periodEnd = $periodStart->endOfMonth();
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);
        $empIds = $employees->pluck('emp_id')->all();
        $labels = PayrollDtrLabel::query()
            ->whereIn('emp_id', $empIds)
            ->whereBetween('dtr_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->groupBy('emp_id');
        $adjustments = PayrollDtrAdjustment::query()
            ->whereIn('emp_id', $empIds)
            ->whereBetween('dtr_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('adjustment_type', ['TARDINESS', 'UNDERTIME'])
            ->get()
            ->groupBy('emp_id');
        $mraAdjustments = $previousMraReport
            ? PayrollLeaveCreditAdjustment::query()
                ->where('mra_report_id', $previousMraReport->id)
                ->whereIn('emp_id', $empIds)
                ->get()
                ->keyBy('emp_id')
            : collect();
        $labelOptions = PayrollDtrLabelOption::query()->get()->keyBy('code');

        return $employees->map(function (Employee $employee) use ($compensations, $salaryMatrix, $labels, $adjustments, $mraAdjustments, $labelOptions, $previousMraReport) {
            $salaryGrade = (int) ($employee->position?->salary_grade ?? 0);
            $step = max(1, min(8, (int) ($employee->step ?: 1)));
            $basicSalary = (float) ($salaryMatrix[$salaryGrade][$step] ?? 0);
            $fallbackDeductionDays = $this->deductionDays(
                $labels->get($employee->emp_id, collect()),
                $adjustments->get($employee->emp_id, collect()),
                $labelOptions,
            );
            $mraAdjustment = $mraAdjustments->get($employee->emp_id);
            $mraDeductionDays = $previousMraReport
                ? (float) ($mraAdjustment?->adjustment_days ?? 0)
                : $fallbackDeductionDays;
            $deductionDays = $this->deductionDaysFor($employee->emp_id, $mraDeductionDays);
            $variables = [
                'basic_salary' => $basicSalary,
                'salary' => $basicSalary,
                'sg' => $salaryGrade,
                'step' => $step,
                'hazard_rate' => $this->hazardRate($salaryGrade),
                'working_days' => max(1, $this->workingDays),
                'leave_days' => $deductionDays,
                'paid_days' => max(0, $this->workingDays - $deductionDays),
            ];

            $computed = [];
            foreach ($compensations as $item) {
                $amount = $this->computeCompensation($item, $variables);
                $key = $item->variable_name ?: str($item->name)->snake()->toString();
                $variables[$key] = $amount;

                $computed[$item->id] = [
                    'name' => $item->name,
                    'amount' => $amount,
                ];
            }

            $statutoryDeductions = $this->statutoryDeductions($basicSalary);
            $gross = $basicSalary + collect($computed)->sum('amount');
            $netBeforeOtherDeductions = $gross - collect($statutoryDeductions)->sum();
            $fifteenth = round($netBeforeOtherDeductions / 2, 2);
            $thirtieth = round($netBeforeOtherDeductions - $fifteenth, 2);

            return [
                'emp_id' => $employee->emp_id,
                'employee_name' => $employee->full_name,
                'department' => $employee->department?->department,
                'position' => $employee->position?->position_title,
                'salary_grade' => $salaryGrade ?: null,
                'step' => $step,
                'sg_step' => $salaryGrade ? 'SG '.$salaryGrade.' / Step '.$step : '-',
                'deduction_days' => $deductionDays,
                'mra_deduction_days' => $mraDeductionDays,
                'mra_minutes' => (int) ($mraAdjustment?->undertime_tardy_minutes ?? 0),
                'has_mra_adjustment' => $mraAdjustment !== null,
                'basic_salary' => $basicSalary,
                'compensations' => $computed,
                'statutory_deductions' => $statutoryDeductions,
                'gross' => $gross,
                'net_before_other_deductions' => $netBeforeOtherDeductions,
                'fifteenth' => $fifteenth,
                'thirtieth' => $thirtieth,
            ];
        });
    }

    private function resetGenerationState(): void
    {
        $this->currentStep = 1;
        $this->deductionDayOverrides = [];
    }

    private function selectedPeriodStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m', $this->period)->startOfMonth();
    }

    private function previousMraPeriod(): array
    {
        $start = $this->selectedPeriodStart()->subMonthNoOverflow()->startOfMonth();

        return [
            'start' => $start,
            'end' => $start->endOfMonth(),
        ];
    }

    private function previousMraReport(array $period): ?PayrollMraReport
    {
        if (! $this->departmentId) {
            return null;
        }

        return PayrollMraReport::query()
            ->where('department_id', $this->departmentId)
            ->whereDate('period_start', $period['start']->toDateString())
            ->whereDate('period_end', $period['end']->toDateString())
            ->latest('generated_at')
            ->first();
    }

    private function deductionDaysFor(string $empId, float $default): float
    {
        $override = $this->deductionDayOverrides[$empId] ?? null;

        if ($override === null || $override === '' || ! is_numeric($override)) {
            return round(max(0, $default), 3);
        }

        return round(max(0, (float) $override), 3);
    }

    private function deductionDays(Collection $labels, Collection $adjustments, Collection $labelOptions): float
    {
        $leaveDays = $labels
            ->filter(function (PayrollDtrLabel $label) use ($labelOptions) {
                $code = strtoupper((string) $label->label);
                $name = strtoupper((string) ($labelOptions->get($label->label)?->name ?? $label->label));

                return str_contains($name, 'LEAVE')
                    || in_array($code, ['VL', 'SL', 'FL', 'SPL', 'LWOP', 'LEAVE_WITHOUT_PAY'], true);
            })
            ->count();

        $undertimeDays = $adjustments->sum('minutes') / 480;

        return round($leaveDays + $undertimeDays, 3);
    }

    private function statutoryDeductions(float $basicSalary): array
    {
        $phicBase = match (true) {
            $basicSalary <= 0 => 0,
            $basicSalary <= 10000 => 500,
            $basicSalary >= 100000 => 5000,
            default => $basicSalary * 0.05,
        };

        return [
            'life_retirement' => round($basicSalary * 0.09, 2),
            'phic' => floor(($phicBase / 2) * 100) / 100,
            'mandatory_pagibig' => $basicSalary > 0 ? 200.0 : 0.0,
        ];
    }

    private function compensations(): Collection
    {
        return PayrollAdditional::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function salaryMatrix(): array
    {
        $grades = SalaryGrade::query()
            ->select(['salary_grade', 'step_increment', 'salary', 'effectivity_date'])
            ->orderByDesc('effectivity_date')
            ->get()
            ->groupBy(fn ($grade) => $grade->salary_grade.'|'.$grade->step_increment);

        $matrix = [];
        foreach ($grades as $key => $items) {
            [$salaryGrade, $step] = explode('|', $key);
            $matrix[(int) $salaryGrade][(int) $step] = (float) $items->first()->salary;
        }

        return $matrix;
    }

    private function computeCompensation(PayrollAdditional $item, array $variables): float
    {
        $type = $item->computation_type ?: ($item->is_percentage ? 'percentage' : 'fixed');
        $value = (float) $item->value;

        return match ($type) {
            'percentage' => round($variables['basic_salary'] * ($value > 1 ? $value / 100 : $value), 2),
            'formula' => round($this->evaluateFormula((string) $item->formula, $variables), 2),
            default => round($value, 2),
        };
    }

    private function hazardRate(int $salaryGrade): float
    {
        return match (true) {
            $salaryGrade <= 19 => 0.25,
            $salaryGrade === 20 => 0.15,
            $salaryGrade === 21 => 0.13,
            $salaryGrade === 22 => 0.12,
            $salaryGrade === 23 => 0.11,
            in_array($salaryGrade, [24, 25], true) => 0.10,
            $salaryGrade === 26 => 0.09,
            $salaryGrade === 27 => 0.08,
            $salaryGrade === 28 => 0.07,
            in_array($salaryGrade, [29, 30], true) => 0.06,
            $salaryGrade === 31 => 0.05,
            default => 0.0,
        };
    }

    private function evaluateFormula(string $formula, array $variables): float
    {
        $expression = strtolower($formula);
        uksort($variables, fn ($a, $b) => strlen($b) <=> strlen($a));

        $expression = $this->resolveFormulaFunctions($expression, $variables);

        if (preg_match('/[a-z_]/i', $expression) || preg_match('/[^0-9+\-*\/().\s]/', $expression)) {
            return 0.0;
        }

        return $this->parseExpression($expression);
    }

    private function resolveFormulaFunctions(string $expression, array $variables): string
    {
        uksort($variables, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($variables as $name => $value) {
            $expression = preg_replace('/\b'.preg_quote(strtolower($name), '/').'\b/', (string) (float) $value, $expression);
        }

        while (preg_match('/\b(max|min)\s*\(([^()]+)\)/i', $expression, $matches)) {
            $values = collect(explode(',', $matches[2]))
                ->map(fn ($argument) => trim($argument))
                ->filter(fn ($argument) => $argument !== '' && ! preg_match('/[a-z_]/i', $argument))
                ->map(fn ($argument) => $this->parseExpression($argument))
                ->values();

            if ($values->isEmpty()) {
                return $expression;
            }

            $value = strtolower($matches[1]) === 'max'
                ? $values->max()
                : $values->min();

            $expression = str_replace($matches[0], (string) (float) $value, $expression);
        }

        return $expression;
    }

    private function parseExpression(string $expression): float
    {
        $tokens = preg_split('/\s*([+\-*\/()])\s*/', trim($expression), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $index = 0;

        $parseFactor = function () use (&$tokens, &$index, &$parseFactor, &$parseTerm, &$parseExpression) {
            $token = $tokens[$index++] ?? '0';

            if ($token === '(') {
                $value = $parseExpression();
                $index++;

                return $value;
            }

            if ($token === '-') {
                return -1 * $parseFactor();
            }

            return is_numeric($token) ? (float) $token : 0.0;
        };

        $parseTerm = function () use (&$tokens, &$index, $parseFactor) {
            $value = $parseFactor();
            while (($tokens[$index] ?? null) === '*' || ($tokens[$index] ?? null) === '/') {
                $operator = $tokens[$index++];
                $next = $parseFactor();
                $value = $operator === '*' ? $value * $next : ($next == 0.0 ? 0.0 : $value / $next);
            }

            return $value;
        };

        $parseExpression = function () use (&$tokens, &$index, $parseTerm) {
            $value = $parseTerm();
            while (($tokens[$index] ?? null) === '+' || ($tokens[$index] ?? null) === '-') {
                $operator = $tokens[$index++];
                $next = $parseTerm();
                $value = $operator === '+' ? $value + $next : $value - $next;
            }

            return $value;
        };

        return $parseExpression();
    }
}
