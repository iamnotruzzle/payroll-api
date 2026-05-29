<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\SalaryGrade;
use App\Models\Payroll\PayrollAdditional;
use App\Models\Payroll\PayrollAuditLog;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;
use App\Models\Payroll\PayrollDeduction;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollEmployeePayrollLine;
use App\Models\Payroll\PayrollEmployeeSnapshot;
use App\Models\Payroll\PayrollGenerationDraft;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Models\Payroll\PayrollLoanImportItem;
use App\Models\Payroll\PayrollMraReport;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\PayrollTimekeepingSummary;
use App\Models\Payroll\PayrollType;
use App\Services\Payroll\PayrollLoanImportService;
use App\Services\Payroll\PayrollLoanReferenceService;
use App\Services\Payroll\PayrollTaxService;
use App\Services\Payroll\RegularPayrollTemplateExportService;
use App\Services\Payroll\StatutoryContributionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PayrollGeneration extends Component
{
    use WithFileUploads;

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public string $period;

    public int $workingDays = 22;

    public string $search = '';

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    public int $currentStep = 1;

    public array $deductionDayOverrides = [];

    public array $leaveDeductionOverrides = [];

    public array $compensationAdjustments = [];

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
        7 => 'Tax Calculation',
        8 => 'Review',
    ];

    public array $loanColumnGroups = [];

    public ?int $finalizedRunId = null;

    public array $finalizedSummary = [];

    public ?int $activeDraftId = null;

    public ?string $draftSavedAt = null;

    public ?string $draftNotice = null;

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('department_id', $userDepartmentId)->value('division_id')
            : null;

        $this->divisionId = request()->integer('division_id') ?: $userDivisionId;
        $this->departmentId = request()->integer('department_id') ?: null;

        if ($this->departmentId && $this->divisionId && ! Department::query()
            ->where('department_id', $this->departmentId)
            ->where('division_id', $this->divisionId)
            ->exists()) {
            $this->departmentId = null;
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: $this->workingDays));

        $employeeType = request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA);
        $this->employeeTypeFilter = array_key_exists($employeeType, Employee::employeeTypeOptions())
            ? $employeeType
            : Employee::EMPLOYEE_TYPE_PLANTILLA;
        $this->search = (string) request()->query('search', '');
        $this->loanColumnGroups = app(PayrollLoanReferenceService::class)->columnGroups();
        $this->restoreDraft();
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

    public function saveDraft(): void
    {
        if (! $this->divisionId && ! $this->departmentId) {
            $this->addError('draft', 'Choose a division before saving this payroll draft.');

            return;
        }

        $this->resetValidation('draft');

        $draft = PayrollGenerationDraft::query()->updateOrCreate(
            ['configuration_key' => $this->draftConfigurationKey()],
            [
                'division_id' => $this->divisionId,
                'department_id' => $this->departmentId,
                'payroll_type_code' => PayrollType::CODE_GENERAL,
                'payroll_period' => $this->period,
                'working_days' => $this->workingDays,
                'employee_type' => $this->employeeTypeFilter,
                'current_step' => $this->currentStep,
                'state_json' => [
                    'deduction_day_overrides' => $this->deductionDayOverrides,
                    'leave_deduction_overrides' => $this->leaveDeductionOverrides,
                    'compensation_adjustments' => $this->compensationAdjustments,
                    'deduction_program_selections' => $this->deductionProgramSelections,
                ],
                'saved_by' => auth()->user()?->emp_id ?? 'web',
                'saved_at' => now(),
            ]
        );

        $this->activeDraftId = $draft->id;
        $this->draftSavedAt = $draft->saved_at?->format('M d, Y g:i A');
        $this->draftNotice = null;

        session()->flash('draft_success', 'Payroll draft saved. Reopening this same configuration will resume these entries.');
    }

    private function resetLoanImportState(): void
    {
        $this->loanFile = null;
        $this->pendingLoanImportPath = null;
        $this->pendingLoanImportOriginalFilename = null;
        $this->loanImportPreview = [];
        $this->resetValidation('loanFile');
    }

    public function exportRegularPayrollTemplate(RegularPayrollTemplateExportService $exporter)
    {
        if (! $this->divisionId && ! $this->departmentId) {
            $this->addError('finalize', 'Choose a division before exporting payroll.');

            return null;
        }

        $compensations = $this->compensations();
        $deductionPrograms = $this->deductionPrograms();
        $rows = $this->payrollRows($compensations, $deductionPrograms);

        if ($rows->isEmpty()) {
            $this->addError('finalize', 'No payroll rows found.');

            return null;
        }

        if (! $this->hasCompleteAdjustmentRemarks($rows)) {
            return null;
        }

        $path = $exporter->export($rows, $compensations, $deductionPrograms, $this->period);
        $filename = 'MMMHMC_REGULAR_PAYROLL_'.$this->period.'.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function render()
    {
        $compensations = $this->compensations();
        $deductionPrograms = $this->deductionPrograms();
        $this->syncDeductionProgramSelections($deductionPrograms);
        $rows = $this->payrollRows($compensations, $deductionPrograms);
        $totals = $this->payrollTotals($rows, $compensations);
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);

        return view('livewire.payroll.payroll-generation', [
            'departments' => Department::query()->orderBy('department')->get(),
            'divisions' => Division::query()->orderBy('division')->get(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'compensations' => $compensations,
            'deductionPrograms' => $deductionPrograms,
            'rows' => $rows,
            'previousMraPeriod' => $previousMraPeriod,
            'previousMraReport' => $previousMraReport,
            'totals' => $totals,
        ]);
    }

    private function payrollTotals(Collection $rows, Collection $compensations): array
    {
        return [
            'basic_salary' => $rows->sum('basic_salary'),
            'compensations' => $compensations->mapWithKeys(
                fn ($item) => [$item->id => $rows->sum(fn ($row) => $row['compensations'][$item->id]['amount'] ?? 0)]
            ),
            'statutory_deductions' => [
                'life_retirement' => $rows->sum('statutory_deductions.life_retirement'),
                'phic' => $rows->sum('statutory_deductions.phic'),
                'mandatory_pagibig' => $rows->sum('statutory_deductions.mandatory_pagibig'),
            ],
            'statutory_government_shares' => [
                'government_life_retirement' => $rows->sum('statutory_government_shares.government_life_retirement'),
                'government_phic' => $rows->sum('statutory_government_shares.government_phic'),
                'government_pagibig' => $rows->sum('statutory_government_shares.government_pagibig'),
            ],
            'withholding_tax' => $rows->sum('tax.monthly_tax_due'),
            'loan_columns' => collect(array_keys($this->blankLoanColumns()))
                ->mapWithKeys(fn (string $key) => [$key => $rows->sum(fn ($row) => $row['loan_deductions']['columns'][$key] ?? 0)])
                ->all(),
            'gross' => $rows->sum('gross'),
            'compensation_adjustments' => [
                'basic_salary' => $rows->sum('compensation_adjustments.basic_salary'),
                'subsistence' => $rows->sum('compensation_adjustments.subsistence'),
                'laundry' => $rows->sum('compensation_adjustments.laundry'),
                'pera' => $rows->sum('compensation_adjustments.pera'),
                'total' => $rows->sum('compensation_adjustments.total'),
            ],
            'net_compensation' => $rows->sum('net_compensation'),
            'net_before_other_deductions' => $rows->sum('net_before_other_deductions'),
            'net_after_tax' => $rows->sum('net_after_tax'),
            'program_deductions' => $rows->sum('program_deductions.total'),
            'loan_deductions' => $rows->sum('loan_deductions.total'),
            'net_after_loan_deductions' => $rows->sum('net_after_loan_deductions'),
            'fifteenth' => $rows->sum('fifteenth'),
            'thirtieth' => $rows->sum('thirtieth'),
        ];
    }

    private function payrollRows(Collection $compensations, Collection $deductionPrograms): Collection
    {
        if (! $this->divisionId && ! $this->departmentId) {
            return collect();
        }

        $employees = Employee::query()
            ->with(['position', 'department.division'])
            ->when(
                $this->departmentId,
                fn ($query) => $query->where('department_id', $this->departmentId),
                fn ($query) => $query->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('division_id', $this->divisionId))
            )
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
        $leaveQuery = EmployeeLeave::query()
            ->whereIn('emp_id', $empIds)
            ->where('status', 0)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString());

        $leaves = $leaveQuery->get()->groupBy('emp_id');
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

        return $employees->map(function (Employee $employee) use ($compensations, $deductionPrograms, $salaryMatrix, $leaves, $labels, $adjustments, $mraAdjustments, $labelOptions, $loanItems, $periodStart, $periodEnd) {
            $salaryGrade = (int) ($employee->position?->salary_grade ?? 0);
            $step = max(1, min(8, (int) ($employee->step ?: 1)));
            $basicSalary = (float) ($salaryMatrix[$salaryGrade][$step] ?? 0);
            $leaveDeduction = $this->leaveDeductionDetails(
                $leaves->get($employee->emp_id, collect()),
                $periodStart,
                $periodEnd,
            );
            $leaveDeduction = $this->editableLeaveDeductionFor($employee->emp_id, $leaveDeduction);
            $fallbackDeductionDays = $this->deductionDays(
                $labels->get($employee->emp_id, collect()),
                $adjustments->get($employee->emp_id, collect()),
                $labelOptions,
            );
            $mraAdjustment = $mraAdjustments->get($employee->emp_id);
            $mraDeductionDays = (float) ($mraAdjustment?->adjustment_days ?? $fallbackDeductionDays);
            $payrollLeaveDays = $leaveDeduction['laundry_days'];
            $deductionDays = $this->deductionDaysFor($employee->emp_id, $payrollLeaveDays);
            $variables = [
                'basic_salary' => $basicSalary,
                'salary' => $basicSalary,
                'sg' => $salaryGrade,
                'step' => $step,
                'hazard_rate' => $this->hazardRate($salaryGrade),
                'working_days' => max(1, $this->workingDays),
                'leave_days' => $deductionDays,
                'subsistence_deduct_days' => $leaveDeduction['subsistence_days'],
                'pera_deduct_days' => $leaveDeduction['pera_days'],
                'laundry_deduct_days' => $leaveDeduction['laundry_days'],
                'tev_deduct_days' => $leaveDeduction['tev_days'],
                'is_part_time' => $this->isPartTimeEmployee($employee),
                'paid_days' => max(0, $this->workingDays - $deductionDays),
            ];
            $hazardLeaveDays = $this->hazardLeaveDays($leaveDeduction, $deductionDays);
            $taxableHazardPay = $this->taxableHazardPay($basicSalary, $salaryGrade, $hazardLeaveDays);

            $computed = [];
            foreach ($compensations as $item) {
                $isHazardCompensation = $this->isHazardCompensation($item);
                $computedAmount = $isHazardCompensation
                    ? $this->taxableHazardPay($basicSalary, $salaryGrade, $hazardLeaveDays)
                    : $this->computeCompensation($item, $variables);
                $amount = $this->includeCompensationInNetPay($item) ? $computedAmount : 0.0;
                $taxDetails = $this->compensationTaxDetails($item, $computedAmount);
                $key = $item->variable_name ?: str($item->name)->snake()->toString();
                $variables[$key] = $amount;

                $computed[$item->id] = [
                    'name' => $item->name,
                    'amount' => $amount,
                    'computed_amount' => $computedAmount,
                    'taxable_amount' => $taxDetails['taxable_amount'],
                    'supplemental_tax_due' => $taxDetails['supplemental_tax_due'],
                    'computation_type' => $item->computation_type ?: ($item->is_percentage ? 'percentage' : 'fixed'),
                    'configured_value' => (float) $item->value,
                    'formula' => $item->formula,
                    'variable_name' => $item->variable_name,
                    'include_in_net_pay' => $this->includeCompensationInNetPay($item),
                    'excluded_from_net_pay' => ! $this->includeCompensationInNetPay($item),
                    'tax_treatment' => $taxDetails['tax_treatment'],
                    'annual_exempt_limit' => $taxDetails['annual_exempt_limit'],
                    'supplemental_tax_rate' => $taxDetails['supplemental_tax_rate'],
                ];
            }

            $statutoryContributions = $this->statutoryContributions($basicSalary);
            $statutoryDeductions = $statutoryContributions['employee'];
            $statutoryGovernmentShares = $statutoryContributions['employer'];
            $gross = $basicSalary + collect($computed)->sum('amount');
            $compensationAdjustments = $this->compensationAdjustmentsFor($employee->emp_id);
            $netCompensation = round($gross + $compensationAdjustments['total'], 2);
            $netBeforeOtherDeductions = $netCompensation - collect($statutoryDeductions)->sum();
            $computedHazardPay = $this->compensationAmountByName($computed, ['hazard'], 'computed_amount');
            $hazardForTaxDisplay = $computedHazardPay ?: $taxableHazardPay;
            $regularTaxableCompensation = collect($computed)->sum('taxable_amount');
            $supplementalTaxDue = collect($computed)->sum('supplemental_tax_due');
            $leaveWithoutPayMonths = $this->leaveWithoutPayMonths($deductionDays);
            $netMonths = max(0, PayrollTaxService::ANNUALIZED_MONTHS - $leaveWithoutPayMonths);
            $tax = $this->taxCalculation(
                $basicSalary + $regularTaxableCompensation + $compensationAdjustments['total'],
                collect($statutoryDeductions)->sum(),
                $netMonths,
                [
                    'entry_date' => $employee->date_hired?->format('Y-m-d'),
                    'salary_grade' => $salaryGrade ?: null,
                    'salary' => $basicSalary,
                    'subsistence' => $this->compensationAmountByName($computed, ['subsistence']),
                    'hazard' => $hazardForTaxDisplay,
                    'hazard_rate' => $this->hazardRate($salaryGrade),
                    'hazard_leave_days' => $hazardLeaveDays,
                    'hazard_eligible' => $taxableHazardPay > 0,
                    'hazard_disqualification_days' => 10,
                    'taxable_compensations' => $regularTaxableCompensation,
                    'supplemental_tax_due' => $supplementalTaxDue,
                    'tax_adjustment' => $compensationAdjustments['total'],
                    'total_months' => PayrollTaxService::ANNUALIZED_MONTHS,
                    'leave_without_pay_months' => $leaveWithoutPayMonths,
                ],
            );
            $withholdingTax = $tax['monthly_tax_due'];
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
            $netAfterTax = round($netBeforeOtherDeductions - $withholdingTax, 2);
            $netAfterProgramDeductions = round($netAfterTax - $programDeductionTotal, 2);
            $netAfterLoanDeductions = round($netAfterProgramDeductions - $loanTotal, 2);
            $fifteenth = round($netAfterLoanDeductions / 2, 2);
            $thirtieth = round($netAfterLoanDeductions - $fifteenth, 2);

            return [
                'emp_id' => $employee->emp_id,
                'first_name' => $employee->firstname,
                'middle_name' => $employee->middlename,
                'last_name' => $employee->lastname,
                'extension' => $employee->extension,
                'employee_name' => $employee->full_name,
                'department' => $employee->department?->department,
                'division' => $employee->department?->division?->division,
                'department_id' => $employee->department_id,
                'tin_no' => $employee->tin_no,
                'gsis_no' => $employee->gsis_no,
                'phic_no' => $employee->phic_no,
                'hdmf_no' => $employee->pagibig_no,
                'fund_type' => null,
                'position_id' => $employee->position_id,
                'position' => $employee->position?->position_title,
                'salary_grade' => $salaryGrade ?: null,
                'step' => $step,
                'sg_step' => $salaryGrade ? 'SG '.$salaryGrade.' / Step '.$step : '-',
                'deduction_days' => $deductionDays,
                'mra_deduction_days' => $payrollLeaveDays,
                'mra_adjustment_days' => $mraDeductionDays,
                'mra_minutes' => (int) ($mraAdjustment?->undertime_tardy_minutes ?? 0),
                'has_mra_adjustment' => $mraAdjustment !== null,
                'leave_deduction' => $leaveDeduction,
                'basic_salary' => $basicSalary,
                'compensations' => $computed,
                'compensation_adjustments' => $compensationAdjustments,
                'net_compensation' => $netCompensation,
                'statutory_deductions' => $statutoryDeductions,
                'statutory_government_shares' => $statutoryGovernmentShares,
                'statutory_contribution_details' => $statutoryContributions['details'],
                'tax' => $tax,
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
                'net_after_tax' => $netAfterTax,
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
        $this->leaveDeductionOverrides = [];
        $this->compensationAdjustments = [];
        $this->deductionProgramSelections = [];
        $this->finalizedRunId = null;
        $this->finalizedSummary = [];
        $this->activeDraftId = null;
        $this->draftSavedAt = null;
        $this->draftNotice = null;
    }

    private function restoreDraft(): void
    {
        $draft = PayrollGenerationDraft::query()
            ->where('configuration_key', $this->draftConfigurationKey())
            ->first();

        if (! $draft) {
            return;
        }

        $state = $draft->state_json ?? [];
        $this->currentStep = max(1, min(count($this->steps), (int) $draft->current_step));
        $this->deductionDayOverrides = (array) ($state['deduction_day_overrides'] ?? []);
        $this->leaveDeductionOverrides = (array) ($state['leave_deduction_overrides'] ?? []);
        $this->compensationAdjustments = (array) ($state['compensation_adjustments'] ?? []);
        $this->deductionProgramSelections = (array) ($state['deduction_program_selections'] ?? []);
        $this->activeDraftId = $draft->id;
        $this->draftSavedAt = $draft->saved_at?->format('M d, Y g:i A');
        $this->draftNotice = 'A saved draft for this configuration was restored.';
    }

    private function draftConfigurationKey(): string
    {
        return PayrollGenerationDraft::configurationKey(
            $this->divisionId,
            $this->departmentId,
            PayrollType::CODE_GENERAL,
            $this->period,
            $this->workingDays,
            $this->employeeTypeFilter,
        );
    }

    private function deductionPrograms(): Collection
    {
        return PayrollDeduction::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->reject(fn (PayrollDeduction $program) => $this->isBuiltInStatutoryDeductionProgram($program))
            ->values();
    }

    private function isBuiltInStatutoryDeductionProgram(PayrollDeduction $program): bool
    {
        $name = str($program->name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return str_contains($name, 'pag ibig')
            || str_contains($name, 'gsis')
            || str_contains($name, 'philhealth')
            || str_contains($name, 'phic')
            || str_contains($name, 'withholding tax');
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

    private function leaveDeductionDetails(Collection $leaves, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $calendarDates = [];
        $workingDates = [];
        $periods = [];

        foreach ($leaves as $leave) {
            if (! $leave->start_date || ! $leave->end_date) {
                continue;
            }

            $start = CarbonImmutable::parse($leave->start_date)->max($periodStart);
            $end = CarbonImmutable::parse($leave->end_date)->min($periodEnd);

            if ($start->greaterThan($end)) {
                continue;
            }

            $periods[] = $this->formatLeavePeriod($start, $end);

            for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                $key = $date->toDateString();
                $calendarDates[$key] = true;

                if ($date->isWeekday()) {
                    $workingDates[$key] = true;
                }
            }
        }

        return [
            'periods' => array_values(array_unique($periods)),
            'calendar_days' => count($calendarDates),
            'working_days' => count($workingDates),
            'subsistence_days' => count($calendarDates),
            'pera_days' => 0,
            'laundry_days' => count($workingDates),
            'tev_days' => 0,
        ];
    }

    private function editableLeaveDeductionFor(string $empId, array $defaults): array
    {
        foreach (['subsistence_days', 'pera_days', 'laundry_days', 'tev_days'] as $field) {
            $this->leaveDeductionOverrides[$empId][$field] ??= $defaults[$field] ?? 0;
            $defaults[$field] = $this->numericLeaveDeductionValue($this->leaveDeductionOverrides[$empId][$field]);
        }

        $defaults['calendar_days'] = $defaults['subsistence_days'];
        $defaults['working_days'] = $defaults['laundry_days'];

        return $defaults;
    }

    private function numericLeaveDeductionValue(mixed $value): float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return round(max(0, (float) $value), 3);
    }

    private function compensationAdjustmentsFor(string $empId): array
    {
        $adjustments = [];

        foreach (['basic_salary', 'subsistence', 'laundry', 'pera'] as $field) {
            $this->compensationAdjustments[$empId][$field] ??= 0;
            $adjustments[$field] = $this->signedMoneyValue($this->compensationAdjustments[$empId][$field]);
        }

        $this->compensationAdjustments[$empId]['remarks'] ??= '';
        $adjustments['remarks'] = trim((string) $this->compensationAdjustments[$empId]['remarks']);
        $adjustments['total'] = round(collect($adjustments)->only([
            'basic_salary',
            'subsistence',
            'laundry',
            'pera',
        ])->sum(), 2);
        $adjustments['remarks_missing'] = $adjustments['total'] !== 0.0 && $adjustments['remarks'] === '';

        return $adjustments;
    }

    private function signedMoneyValue(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function hasCompleteAdjustmentRemarks(Collection $rows): bool
    {
        $missingRemarks = $rows
            ->filter(fn (array $row) => (bool) ($row['compensation_adjustments']['remarks_missing'] ?? false))
            ->pluck('employee_name');

        if ($missingRemarks->isEmpty()) {
            $this->resetValidation('adjustments');

            return true;
        }

        $this->currentStep = 3;
        $names = $missingRemarks->take(3)->implode(', ');
        $suffix = $missingRemarks->count() > 3 ? ' and others' : '';
        $this->addError('adjustments', "Enter adjustment remarks for {$names}{$suffix} before exporting or finalizing.");

        return false;
    }

    private function formatLeavePeriod(CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('M j').' - '.$end->format('j');
        }

        return $start->format('M j').' - '.$end->format('M j');
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

    private function statutoryContributions(float $basicSalary): array
    {
        return app(StatutoryContributionService::class)->calculate(
            $basicSalary,
            $this->selectedPeriodStart(),
        );
    }

    private function taxCalculation(float $monthlyGrossIncome, float $monthlyMandatoryDeductions, float $netMonths, array $context = []): array
    {
        $calculation = app(PayrollTaxService::class)->calculation($monthlyGrossIncome, $monthlyMandatoryDeductions, $netMonths);
        $supplementalTaxDue = round((float) ($context['supplemental_tax_due'] ?? 0), 2);
        $regularMonthlyTaxDue = (float) ($calculation['monthly_tax_due'] ?? 0);

        return [
            ...$context,
            ...$calculation,
            'monthly_net_income' => round($monthlyGrossIncome - $monthlyMandatoryDeductions, 2),
            'regular_monthly_tax_due' => $regularMonthlyTaxDue,
            'supplemental_tax_due' => $supplementalTaxDue,
            'monthly_tax_due' => round($regularMonthlyTaxDue + $supplementalTaxDue, 2),
        ];
    }

    private function leaveWithoutPayMonths(float $deductionDays): float
    {
        return round(max(0, $deductionDays) / max(1, $this->workingDays), 4);
    }

    private function compensationAmountByName(array $computed, array $needles, string $amountKey = 'amount'): float
    {
        foreach ($computed as $item) {
            $name = strtolower((string) ($item['name'] ?? ''));

            foreach ($needles as $needle) {
                if (str_contains($name, strtolower($needle))) {
                    return round((float) ($item[$amountKey] ?? 0), 2);
                }
            }
        }

        return 0.0;
    }

    private function hazardLeaveDays(array $leaveDeduction, float $deductionDays): float
    {
        return round(max(
            (float) ($leaveDeduction['calendar_days'] ?? 0),
            (float) ($leaveDeduction['working_days'] ?? 0),
            $deductionDays,
        ), 3);
    }

    private function taxableHazardPay(float $basicSalary, int $salaryGrade, float $hazardLeaveDays): float
    {
        if ($basicSalary <= 0 || $salaryGrade <= 0 || $hazardLeaveDays > 10) {
            return 0.0;
        }

        return round($basicSalary * $this->hazardRate($salaryGrade), 2);
    }

    private function includeCompensationInNetPay(PayrollAdditional $item): bool
    {
        if ($this->isHazardCompensation($item)) {
            return false;
        }

        return $item->include_in_net_pay ?? true;
    }

    private function compensationTaxDetails(PayrollAdditional $item, float $amount): array
    {
        $treatment = $item->tax_treatment ?: 'regular_taxable';
        $annualExemptLimit = $item->annual_exempt_limit !== null ? (float) $item->annual_exempt_limit : null;
        $supplementalTaxRate = $item->supplemental_tax_rate !== null ? (float) $item->supplemental_tax_rate : null;

        $taxableAmount = match ($treatment) {
            'non_taxable' => 0.0,
            'de_minimis_annual_limit' => $this->monthlyTaxableAfterAnnualExemptLimit($amount, $annualExemptLimit),
            'supplemental_flat_rate' => 0.0,
            default => $amount,
        };
        $supplementalTaxDue = $treatment === 'supplemental_flat_rate'
            ? round($amount * max(0, $supplementalTaxRate ?? 0), 2)
            : 0.0;

        return [
            'tax_treatment' => $treatment,
            'taxable_amount' => round(max(0, $taxableAmount), 2),
            'supplemental_tax_due' => $supplementalTaxDue,
            'annual_exempt_limit' => $annualExemptLimit,
            'supplemental_tax_rate' => $supplementalTaxRate,
        ];
    }

    private function monthlyTaxableAfterAnnualExemptLimit(float $amount, ?float $annualExemptLimit): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $monthlyExemptLimit = max(0, (float) ($annualExemptLimit ?? 0)) / PayrollTaxService::ANNUALIZED_MONTHS;

        return round(max(0, $amount - $monthlyExemptLimit), 2);
    }

    private function isHazardCompensation(PayrollAdditional $item): bool
    {
        $text = strtolower(implode(' ', [
            (string) $item->name,
            (string) $item->variable_name,
            (string) $item->formula,
        ]));

        return str_contains($text, 'hazard');
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
        $formulaVariables = [
            ...$variables,
            'configured_value' => $value,
        ];

        $amount = match ($type) {
            'percentage' => round($variables['basic_salary'] * ($value > 1 ? $value / 100 : $value), 2),
            'formula' => round($this->evaluateFormula((string) $item->formula, $formulaVariables), 2),
            default => round($value, 2),
        };

        return $amount;
    }

    private function isPartTimeEmployee(Employee $employee): bool
    {
        $values = [
            $employee->getAttribute('employment_type'),
            $employee->getAttribute('employee_type'),
            $employee->getAttribute('emp_type'),
            $employee->position?->position_title,
            $employee->position?->remarks,
        ];

        return collect($values)
            ->filter()
            ->contains(fn ($value) => str_contains(strtolower((string) $value), 'part-time')
                || str_contains(strtolower((string) $value), 'part time')
                || strtoupper((string) $value) === 'PART_TIME');
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

    public function snapshotPayroll(): void
    {
        $this->finalizePayroll();
    }

    public function finalizePayroll(): void
    {
        if (! $this->divisionId && ! $this->departmentId) {
            $this->addError('finalize', 'Choose a division before finalizing payroll.');

            return;
        }

        $compensations = $this->compensations();

        $deductionPrograms = $this->deductionPrograms();

        $rows = $this->payrollRows($compensations, $deductionPrograms);
        $totals = $this->payrollTotals($rows, $compensations);

        if ($rows->isEmpty()) {
            $this->addError('finalize', 'No payroll rows found.');

            return;
        }

        if (! $this->hasCompleteAdjustmentRemarks($rows)) {
            return;
        }

        $run = DB::connection('payroll')->transaction(function () use (
            $rows,
            $compensations,
            $deductionPrograms,
            $totals
        ) {
            $periodStart = $this->selectedPeriodStart();
            $periodEnd = $periodStart->endOfMonth();
            $departmentName = Department::query()
                ->where('department_id', $this->departmentId)
                ->value('department');
            $divisionName = Division::query()
                ->where('division_id', $this->divisionId)
                ->value('division');
            $scopeName = $departmentName ?: $divisionName;
            $generatedBy = auth()->user()?->emp_id ?? 'web';
            $payrollType = PayrollType::query()->firstOrCreate(
                ['code' => PayrollType::CODE_GENERAL],
                [
                    'name' => 'General',
                    'description' => 'General monthly salary payroll.',
                    'sort_order' => 10,
                    'is_active' => true,
                ]
            );

            $period = PayrollPeriod::create([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'period_type' => 'monthly',
                'is_locked' => true,
                'locked_at' => now(),
            ]);

            $run = PayrollRun::create([
                'payroll_period_id' => $period->id,
                'payroll_date' => $periodEnd->toDateString(),
                'payroll_type_id' => $payrollType->id,
                'department_id' => $this->departmentId,
                'department_name' => $scopeName,
                'status' => 1,
                'generated_by' => $generatedBy,
                'gross_pay' => $totals['net_compensation'],
                'total_additions' => collect($totals['compensations'])->sum() + $totals['compensation_adjustments']['total'],
                'total_deductions' => $totals['net_compensation'] - $totals['net_after_loan_deductions'],
                'net_pay' => $totals['net_after_loan_deductions'],
            ]);

            $batch = PayrollBatch::create([
                'department_id' => $this->departmentId,
                'division_id' => $this->divisionId,
                'payroll_period' => $this->period,
                'payroll_type' => $payrollType->name,
                'payroll_type_code' => $payrollType->code,
                'working_days' => $this->workingDays,
                'employee_type' => $this->employeeTypeFilter,
                'generated_by' => $generatedBy,
                'snapshot_created_at' => now(),
                'remarks' => "Payroll run #{$run->id} finalized from Payroll Generation module.",
            ]);

            foreach ($rows as $row) {
                PayrollEmployeeSnapshot::create([
                    'payroll_generate_id' => $run->id,
                    'emp_id' => $row['emp_id'],
                    'employee_name' => $row['employee_name'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'],
                    'last_name' => $row['last_name'],
                    'extension' => $row['extension'],
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department'],
                    'position_id' => $row['position_id'],
                    'position_title' => $row['position'],
                    'salary_grade' => $row['salary_grade'],
                    'step' => $row['step'],
                    'monthly_salary' => $row['basic_salary'],
                    'created_at' => now(),
                ]);

                PayrollTimekeepingSummary::create([
                    'payroll_generate_id' => $run->id,
                    'emp_id' => $row['emp_id'],
                    'total_work_days' => $this->workingDays,
                    'days_with_dtr' => $this->workingDays,
                    'regular_hours' => max(0, $this->workingDays - $row['deduction_days']) * 8,
                    'undertime_hours' => round(($row['mra_minutes'] ?? 0) / 60, 4),
                    'tardy_hours' => 0,
                    'mra_hours' => round(($row['mra_minutes'] ?? 0) / 60, 4),
                    'leave_days_with_pay' => 0,
                    'leave_days_without_pay' => $row['deduction_days'],
                    'absent_days' => 0,
                    'created_at' => now(),
                ]);

                foreach ($this->payrollLinesForRow($row) as $line) {
                    PayrollEmployeePayrollLine::create([
                        ...$line,
                        'payroll_generate_id' => $run->id,
                    ]);
                }

                PayrollBatchRecord::create([
                    'payroll_batch_id' => $batch->id,
                    'emp_id' => $row['emp_id'],
                    'department_id' => $this->departmentId,

                    'gross' => $row['net_compensation'],
                    'net' => $row['net_after_loan_deductions'],

                    'fifteenth' => $row['fifteenth'],
                    'thirtieth' => $row['thirtieth'],

                    'snapshot_json' => $this->payrollSnapshotForRow($row, $compensations, $deductionPrograms, $run->id),
                ]);
            }

            PayrollAuditLog::create([
                'payroll_generate_id' => $run->id,
                'action' => 'payroll.finalized',
                'performed_by' => $generatedBy,
                'remarks' => "Finalized {$this->period} payroll for {$scopeName}.",
                'created_at' => now(),
            ]);

            return $run->fresh();
        });

        $this->finalizedRunId = $run->id;
        PayrollGenerationDraft::query()
            ->where('configuration_key', $this->draftConfigurationKey())
            ->delete();
        $this->activeDraftId = null;
        $this->draftSavedAt = null;
        $this->draftNotice = null;
        $this->finalizedSummary = [
            'employees' => $rows->count(),
            'gross' => $totals['net_compensation'],
            'net' => $totals['net_after_loan_deductions'],
            'period' => $this->selectedPeriodStart()->format('F Y'),
            'department' => $run->department_name,
        ];

        session()->flash('success', "Payroll run #{$run->id} finalized and saved.");
    }

    private function payrollLinesForRow(array $row): array
    {
        $lines = [[
            'emp_id' => $row['emp_id'],
            'line_group' => 'EARNING',
            'code' => 'basic_salary',
            'name' => 'Basic Pay',
            'amount' => $row['basic_salary'],
            'remarks' => $this->period,
        ]];

        foreach ($row['compensations'] as $id => $compensation) {
            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'EARNING',
                'code' => "compensation_{$id}",
                'name' => $compensation['name'],
                'amount' => $compensation['amount'],
                'remarks' => $this->period,
            ];
        }

        foreach ([
            'basic_salary' => 'Basic Salary',
            'subsistence' => 'Subsistence',
            'laundry' => 'Laundry',
            'pera' => 'PERA',
        ] as $code => $label) {
            $amount = (float) ($row['compensation_adjustments'][$code] ?? 0);
            if ($amount === 0.0) {
                continue;
            }

            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'EARNING',
                'code' => 'adjustment_'.$code,
                'name' => $label.' Adjustment',
                'amount' => $amount,
                'remarks' => $row['compensation_adjustments']['remarks'],
            ];
        }

        foreach ($row['statutory_deductions'] as $code => $amount) {
            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'DEDUCTION',
                'code' => $code,
                'name' => str($code)->replace('_', ' ')->title()->toString(),
                'amount' => $amount,
                'remarks' => 'Statutory deduction',
            ];
        }

        if (($row['tax']['monthly_tax_due'] ?? 0) > 0) {
            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'DEDUCTION',
                'code' => 'withholding_tax',
                'name' => 'Withholding Tax',
                'amount' => $row['tax']['monthly_tax_due'],
                'remarks' => 'Annualized tax calculation',
            ];
        }

        foreach ($row['program_deductions']['items'] ?? [] as $item) {
            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'DEDUCTION',
                'code' => 'program_'.$item['id'],
                'name' => $item['name'],
                'amount' => $item['amount'],
                'remarks' => 'Deduction program',
            ];
        }

        foreach (($row['loan_deductions']['columns'] ?? []) as $code => $amount) {
            if ((float) $amount <= 0) {
                continue;
            }

            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'DEDUCTION',
                'code' => $code,
                'name' => $this->loanColumnLabel($code),
                'amount' => $amount,
                'remarks' => 'Imported deduction',
            ];
        }

        return $lines;
    }

    private function payrollSnapshotForRow(array $row, Collection $compensations, Collection $deductionPrograms, int $runId): array
    {
        return [
            'payroll_run_id' => $runId,
            'employee' => [
                'emp_id' => $row['emp_id'],
                'employee_name' => $row['employee_name'],
                'department' => $row['department'],
                'position' => $row['position'],
                'salary_grade' => $row['salary_grade'],
                'step' => $row['step'],
                'sg_step' => $row['sg_step'],
            ],
            'pay_basis' => [
                'salary_grade' => $row['salary_grade'],
                'step' => $row['step'],
                'deduction_days' => $row['deduction_days'],
                'working_days' => $this->workingDays,
                'leave_deduction' => $row['leave_deduction'] ?? [],
            ],
            'earnings' => [
                'basic_salary' => $row['basic_salary'],
                'compensations' => $row['compensations'],
                'gross' => $row['gross'],
                'adjustments' => $row['compensation_adjustments'],
                'net_compensation' => $row['net_compensation'],
            ],
            'statutory_deductions' => $row['statutory_deductions'],
            'statutory_government_shares' => $row['statutory_government_shares'],
            'statutory_contribution_details' => $row['statutory_contribution_details'],
            'tax' => $row['tax'],
            'program_deductions' => $row['program_deductions'],
            'loan_deductions' => $row['loan_deductions'],
            'totals' => [
                'gross' => $row['gross'],
                'net_compensation' => $row['net_compensation'],
                'net_before_other_deductions' => $row['net_before_other_deductions'],
                'net_after_tax' => $row['net_after_tax'],
                'net_after_program_deductions' => $row['net_after_program_deductions'],
                'net_after_loan_deductions' => $row['net_after_loan_deductions'],
                'fifteenth' => $row['fifteenth'],
                'thirtieth' => $row['thirtieth'],
            ],
            'column_groups' => $this->snapshotColumnGroups($compensations, $deductionPrograms),
            'columns' => $this->snapshotColumns($compensations, $deductionPrograms),
        ];
    }

    private function snapshotColumnGroups(Collection $compensations, Collection $deductionPrograms): array
    {
        return [
            ['label' => 'Employee Information', 'columns' => ['emp_id', 'employee_name', 'position']],
            ['label' => 'Pay Basis', 'columns' => ['salary_grade', 'step', 'subsistence_deduct_days', 'pera_deduct_days', 'laundry_deduct_days', 'tev_deduct_days', 'deduction_days']],
            ['label' => 'Earnings', 'columns' => array_merge(['basic_salary'], $compensations->map(fn ($item) => 'compensation_'.$item->id)->all(), ['gross'])],
            ['label' => 'Compensation Adjustments', 'columns' => ['adjustment_basic_salary', 'adjustment_subsistence', 'adjustment_laundry', 'adjustment_pera', 'adjustment_remarks', 'net_compensation']],
            ['label' => 'Statutory Deductions', 'columns' => ['life_retirement', 'phic', 'mandatory_pagibig']],
            ['label' => 'Government Shares', 'columns' => ['government_life_retirement', 'government_phic', 'government_pagibig']],
            ['label' => 'Tax Calculation', 'columns' => [
                'entry_date',
                'tax_salary_grade',
                'tax_salary',
                'tax_subsistence',
                'tax_hazard',
                'tax_deductions',
                'tax_monthly_net_income',
                'tax_adjustment',
                'tax_total_months',
                'tax_leave_without_pay_months',
                'tax_net_months',
                'tax_total_gross_income',
                'tax_total_deductions',
                'annual_taxable_income',
                'annual_tax_due',
                'regular_monthly_tax_due',
                'supplemental_tax_due',
                'withholding_tax',
                'net_after_tax',
            ]],
            ['label' => 'Deduction Programs', 'columns' => array_merge($deductionPrograms->map(fn ($program) => 'program_'.$program->id)->all(), ['program_total'])],
            ...collect($this->loanColumnGroups)->map(fn (array $columns, string $label) => ['label' => $label, 'columns' => array_keys($columns)])->values()->all(),
            ['label' => 'Net Pay Distribution', 'columns' => ['net_before_other_deductions', 'loan_total', 'net_after_loan_deductions', 'fifteenth', 'thirtieth']],
        ];
    }

    private function snapshotColumns(Collection $compensations, Collection $deductionPrograms): array
    {
        $columns = [
            'emp_id' => ['label' => 'Employee No.', 'enabled' => true],
            'employee_name' => ['label' => 'Employee Name', 'enabled' => true],
            'position' => ['label' => 'Position', 'enabled' => true],
            'salary_grade' => ['label' => 'Salary Grade', 'enabled' => true],
            'step' => ['label' => 'Step', 'enabled' => true],
            'subsistence_deduct_days' => ['label' => 'Subsistence', 'enabled' => true],
            'pera_deduct_days' => ['label' => 'PERA', 'enabled' => true],
            'laundry_deduct_days' => ['label' => 'Laundry', 'enabled' => true],
            'tev_deduct_days' => ['label' => 'TEV', 'enabled' => true],
            'deduction_days' => ['label' => 'Deduct Days', 'enabled' => true],
            'basic_salary' => ['label' => 'Basic Pay', 'enabled' => true],
            'gross' => ['label' => 'Gross Pay', 'enabled' => true],
            'adjustment_basic_salary' => ['label' => 'Basic Salary Adjustment', 'enabled' => true],
            'adjustment_subsistence' => ['label' => 'Subsistence Adjustment', 'enabled' => true],
            'adjustment_laundry' => ['label' => 'Laundry Adjustment', 'enabled' => true],
            'adjustment_pera' => ['label' => 'PERA Adjustment', 'enabled' => true],
            'adjustment_remarks' => ['label' => 'Adjustment Remarks', 'enabled' => true],
            'net_compensation' => ['label' => 'Net Compensation', 'enabled' => true],
            'life_retirement' => ['label' => 'Life & Retirement', 'enabled' => true],
            'phic' => ['label' => 'PhilHealth', 'enabled' => true],
            'mandatory_pagibig' => ['label' => 'Pag-IBIG', 'enabled' => true],
            'government_life_retirement' => ['label' => 'Govt. Life & Retirement', 'enabled' => true],
            'government_phic' => ['label' => 'Govt. PhilHealth', 'enabled' => true],
            'government_pagibig' => ['label' => 'Govt. Pag-IBIG', 'enabled' => true],
            'annual_taxable_income' => ['label' => 'Taxable Income (Year)', 'enabled' => true],
            'annual_tax_due' => ['label' => 'Tax Due (Year)', 'enabled' => true],
            'regular_monthly_tax_due' => ['label' => 'Regular Tax', 'enabled' => true],
            'supplemental_tax_due' => ['label' => 'Supplemental Tax', 'enabled' => true],
            'withholding_tax' => ['label' => 'Withholding Tax', 'enabled' => true],
            'net_after_tax' => ['label' => 'Net After Tax', 'enabled' => true],
            'entry_date' => ['label' => 'Entry Date', 'enabled' => true],
            'tax_salary_grade' => ['label' => 'SG', 'enabled' => true],
            'tax_salary' => ['label' => 'Salary', 'enabled' => true],
            'tax_subsistence' => ['label' => 'Subsistence', 'enabled' => true],
            'tax_hazard' => ['label' => 'Hazard', 'enabled' => true],
            'tax_deductions' => ['label' => 'Deductions', 'enabled' => true],
            'tax_monthly_net_income' => ['label' => 'Net Monthly Income', 'enabled' => true],
            'tax_adjustment' => ['label' => 'Tax Adjustment', 'enabled' => true],
            'tax_total_months' => ['label' => 'Total Months', 'enabled' => true],
            'tax_leave_without_pay_months' => ['label' => 'Leave W/O Pay (Months)', 'enabled' => true],
            'tax_net_months' => ['label' => 'Net, Months', 'enabled' => true],
            'tax_total_gross_income' => ['label' => 'Total Gross Income', 'enabled' => true],
            'tax_total_deductions' => ['label' => 'Total Deductions', 'enabled' => true],
            'program_total' => ['label' => 'Program Total', 'enabled' => true],
            'net_before_other_deductions' => ['label' => 'Net Before Other Deductions', 'enabled' => true],
            'loan_total' => ['label' => 'Total Other Deductions', 'enabled' => true],
            'net_after_loan_deductions' => ['label' => 'Final Net Pay', 'enabled' => true],
            'fifteenth' => ['label' => '15th Payroll', 'enabled' => true],
            'thirtieth' => ['label' => '30th Payroll', 'enabled' => true],
        ];

        foreach ($compensations as $item) {
            $columns['compensation_'.$item->id] = ['label' => $item->name, 'enabled' => true];
        }

        foreach ($deductionPrograms as $program) {
            $columns['program_'.$program->id] = ['label' => $program->name, 'enabled' => true];
        }

        foreach ($this->loanColumnGroups as $group) {
            foreach ($group as $key => $label) {
                $columns[$key] = ['label' => $label, 'enabled' => true];
            }
        }

        return $columns;
    }

    private function loanColumnLabel(string $key): string
    {
        foreach ($this->loanColumnGroups as $group) {
            if (array_key_exists($key, $group)) {
                return $group[$key];
            }
        }

        return str($key)->replace('_', ' ')->title()->toString();
    }
}
