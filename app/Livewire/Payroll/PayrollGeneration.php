<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryGrade;
use App\Models\Payroll\PayrollAdditional;
use App\Models\Payroll\PayrollDeduction;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Models\Payroll\PayrollLoanImportItem;
use App\Models\Payroll\PayrollMraReport;
use App\Services\Payroll\PayrollLoanImportService;
use App\Services\Payroll\PayrollLoanReferenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PayrollGeneration extends Component
{
    use WithFileUploads;

    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public string $search = '';

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public int $currentStep = 1;

    public array $deductionDayOverrides = [];

    public array $deductionProgramSelections = [];

    public bool $showLoanImportModal = false;

    public $loanFile;

    public ?string $pendingLoanImportPath = null;

    public ?string $pendingLoanImportOriginalFilename = null;

    public array $loanImportPreview = [];

    public array $steps = [
        1 => 'MRA Validation',
        2 => 'Allowances Computation',
        3 => 'Deductions and Adjustments',
        4 => 'Statutory',
        5 => 'Deduction Programs',
        6 => 'Imported Deductions',
        7 => 'Review',
    ];

    public array $loanColumnGroups = [];


    public function mount(): void
    {
        $this->departmentId = auth()->user()?->employee?->department_id;
        $this->period = CarbonImmutable::today()->format('Y-m');
        $this->loanColumnGroups = app(PayrollLoanReferenceService::class)->columnGroups();
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
        $this->currentStep = max(1, min(count($this->steps), $step));
    }

    public function nextStep(): void
    {
        $this->goToStep($this->currentStep + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->currentStep - 1);
    }

    public function applyDeductionProgram(int $programId): void
    {
        $this->deductionProgramSelections[(string) $programId]['enabled'] = true;
    }

    public function removeDeductionProgram(int $programId): void
    {
        $this->deductionProgramSelections[(string) $programId]['enabled'] = false;
    }

    public function openLoanImportModal(): void
    {
        $this->resetLoanImportState();
        $this->showLoanImportModal = true;
    }

    public function closeLoanImportModal(): void
    {
        $this->showLoanImportModal = false;
        $this->resetLoanImportState();
    }

    public function previewLoanImport(): void
    {
        $data = $this->validate([
            'loanFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file = $data['loanFile'];
        $storedPath = $file->store('payroll/loan-imports');
        $this->pendingLoanImportPath = $storedPath;
        $this->pendingLoanImportOriginalFilename = $file->getClientOriginalName();
        $this->loanImportPreview = app(PayrollLoanImportService::class)->preview(Storage::path($storedPath));
    }

    public function saveLoanImport(): void
    {
        if (! $this->pendingLoanImportPath || empty($this->loanImportPreview)) {
            $this->addError('loanFile', 'Preview the loan file before saving the import.');

            return;
        }

        $this->loanImportPreview = app(PayrollLoanImportService::class)->preview(Storage::path($this->pendingLoanImportPath));
        if (($this->loanImportPreview['invalid_rows'] ?? 0) > 0) {
            $this->addError('loanFile', 'Fix invalid rows before saving the loan import.');

            return;
        }

        $import = app(PayrollLoanImportService::class)->savePreview(
            $this->loanImportPreview,
            $this->pendingLoanImportOriginalFilename ?? 'loan_import.xlsx',
            $this->pendingLoanImportPath,
            auth()->user()?->emp_id,
        );

        $this->showLoanImportModal = false;
        $this->resetLoanImportState();

        session()->flash(
            'loan_import_status',
            "Imported {$import->total_rows} loan row(s): {$import->valid_rows} ready, {$import->invalid_rows} needing review."
        );
    }

    private function resetLoanImportState(): void
    {
        $this->loanFile = null;
        $this->pendingLoanImportPath = null;
        $this->pendingLoanImportOriginalFilename = null;
        $this->loanImportPreview = [];
        $this->resetValidation('loanFile');
    }

    public function render()
    {
        $compensations = $this->compensations();
        $deductionPrograms = $this->deductionPrograms();
        $this->syncDeductionProgramSelections($deductionPrograms);
        $rows = $this->payrollRows($compensations, $deductionPrograms);
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);

        return view('livewire.payroll.payroll-generation', [
            'departments' => Department::query()->orderBy('department')->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'compensations' => $compensations,
            'deductionPrograms' => $deductionPrograms,
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
                'loan_columns' => collect(array_keys($this->blankLoanColumns()))
                    ->mapWithKeys(fn (string $key) => [$key => $rows->sum(fn ($row) => $row['loan_deductions']['columns'][$key] ?? 0)])
                    ->all(),
                'gross' => $rows->sum('gross'),
                'net_before_other_deductions' => $rows->sum('net_before_other_deductions'),
                'program_deductions' => $rows->sum('program_deductions.total'),
                'loan_deductions' => $rows->sum('loan_deductions.total'),
                'net_after_loan_deductions' => $rows->sum('net_after_loan_deductions'),
                'fifteenth' => $rows->sum('fifteenth'),
                'thirtieth' => $rows->sum('thirtieth'),
            ],
        ]);
    }

    private function payrollRows(Collection $compensations, Collection $deductionPrograms): Collection
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
        $loanItems = PayrollLoanImportItem::query()
            ->with('import')
            ->where('validation_status', 'valid')
            ->whereDate('due_month', $periodStart->toDateString())
            ->whereIn('matched_emp_id', $empIds)
            ->orderByDesc('id')
            ->get()
            ->unique(fn (PayrollLoanImportItem $item) => implode('|', [
                strtoupper($item->entity),
                $item->due_month?->toDateString(),
                $item->matched_emp_id,
                strtoupper($item->loan_account_no),
            ]))
            ->groupBy('matched_emp_id');
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

        return $employees->map(function (Employee $employee) use ($compensations, $deductionPrograms, $salaryMatrix, $labels, $adjustments, $mraAdjustments, $labelOptions, $previousMraReport, $loanItems) {
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
            $programDeductionItems = $this->programDeductionsFor($employee, $deductionPrograms, $basicSalary);
            $programDeductionTotal = round(collect($programDeductionItems)->sum('amount'), 2);
            $employeeLoanItems = $loanItems->get($employee->emp_id, collect());
            $loanTotal = round($employeeLoanItems->sum('amount_due'), 2);
            $loanColumns = $this->blankLoanColumns();
            foreach ($employeeLoanItems as $loanItem) {
                $key = $this->loanColumnKey($loanItem);
                $loanColumns[$key] = round(($loanColumns[$key] ?? 0) + (float) $loanItem->amount_due, 2);
            }
            $loanByEntity = $employeeLoanItems
                ->groupBy('entity')
                ->map(fn (Collection $items, string $entity) => [
                    'entity' => $entity,
                    'count' => $items->count(),
                    'amount' => round($items->sum('amount_due'), 2),
                ])
                ->values()
                ->all();
            $netAfterProgramDeductions = round($netBeforeOtherDeductions - $programDeductionTotal, 2);
            $netAfterLoanDeductions = round($netAfterProgramDeductions - $loanTotal, 2);
            $fifteenth = round($netAfterLoanDeductions / 2, 2);
            $thirtieth = round($netAfterLoanDeductions - $fifteenth, 2);

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
                'loan_deductions' => [
                    'total' => $loanTotal,
                    'columns' => $loanColumns,
                    'items' => $employeeLoanItems->map(fn (PayrollLoanImportItem $item) => [
                        'entity' => $item->entity,
                        'loan_account_no' => $item->loan_account_no,
                        'loan_type' => $item->loan_type,
                        'amount_due' => (float) $item->amount_due,
                        'imported_at' => $item->import?->imported_at?->format('M d, Y'),
                    ])->values()->all(),
                    'by_entity' => $loanByEntity,
                ],
                'program_deductions' => [
                    'total' => $programDeductionTotal,
                    'items' => $programDeductionItems,
                ],
                'gross' => $gross,
                'net_before_other_deductions' => $netBeforeOtherDeductions,
                'net_after_program_deductions' => $netAfterProgramDeductions,
                'net_after_loan_deductions' => $netAfterLoanDeductions,
                'fifteenth' => $fifteenth,
                'thirtieth' => $thirtieth,
            ];
        });
    }

    private function resetGenerationState(): void
    {
        $this->currentStep = 1;
        $this->deductionDayOverrides = [];
        $this->deductionProgramSelections = [];
    }

    private function deductionPrograms(): Collection
    {
        return PayrollDeduction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function syncDeductionProgramSelections(Collection $programs): void
    {
        foreach ($programs as $program) {
            $id = (string) $program->id;
            $this->deductionProgramSelections[$id] = array_merge([
                'enabled' => false,
                'mode' => 'all',
                'employee_ids' => [],
                'amount_mode' => 'program',
                'employee_amounts' => [],
            ], $this->deductionProgramSelections[$id] ?? []);
        }
    }

    private function programDeductionsFor(Employee $employee, Collection $programs, float $basicSalary): array
    {
        return $programs
            ->filter(fn (PayrollDeduction $program) => $this->programAppliesToEmployee($program, $employee->emp_id))
            ->map(fn (PayrollDeduction $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'amount' => $this->computeDeductionProgram($program, $employee->emp_id, $basicSalary),
            ])
            ->values()
            ->all();
    }

    private function programAppliesToEmployee(PayrollDeduction $program, string $empId): bool
    {
        $selection = $this->deductionProgramSelections[(string) $program->id] ?? [];
        if (! filter_var($selection['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $mode = $selection['mode'] ?? 'all';
        $employeeIds = collect($selection['employee_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values();

        return match ($mode) {
            'include' => $employeeIds->contains($empId),
            'exclude' => ! $employeeIds->contains($empId),
            default => true,
        };
    }

    private function computeDeductionProgram(PayrollDeduction $program, string $empId, float $basicSalary): float
    {
        $selection = $this->deductionProgramSelections[(string) $program->id] ?? [];
        $employeeAmount = $selection['employee_amounts'][$empId] ?? null;
        $useEmployeeAmount = ($selection['amount_mode'] ?? 'program') === 'employee'
            && $employeeAmount !== null
            && $employeeAmount !== ''
            && is_numeric($employeeAmount);
        $value = $useEmployeeAmount ? (float) $employeeAmount : (float) $program->value;

        if ($program->is_percentage) {
            return round($basicSalary * ($value > 1 ? $value / 100 : $value), 2);
        }

        return round($value, 2);
    }

    private function blankLoanColumns(): array
    {
        return collect($this->loanColumnGroups)
            ->flatMap(fn (array $columns) => array_keys($columns))
            ->mapWithKeys(fn (string $key) => [$key => 0.0])
            ->all();
    }

    private function loanColumnKey(PayrollLoanImportItem $item): string
    {
        return app(PayrollLoanReferenceService::class)->columnKeyFor($item);
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
