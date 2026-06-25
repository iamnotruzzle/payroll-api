<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use App\Models\Payroll\PayrollAdjustmentType;
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
use App\Models\Payroll\PayrollLoanType;
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
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class PayrollGeneration extends Component
{
    use WithFileUploads;

    private const DEFAULT_UNCHECKED_LEAVE_TYPE_IDS = [4, 14, 15, 16, 20, 22];

    private const EXCLUDED_LEAVE_LOG_ACTIONS = [2, 3];

    private const EMPLOYEE_MANDATORY_DEDUCTION_KEYS = [
        'life_retirement',
        'phic',
        'mandatory_pagibig',
        'hdmf_ps_2_ms',
        'ea_deduction',
    ];

    private const GOVERNMENT_MANDATORY_DEDUCTION_KEYS = [
        'government_life_retirement',
        'ec',
        'government_phic',
        'government_pagibig',
    ];

    private const ADDITIONAL_PREMIUM_ENTITY_CODES = ['ADDITIONAL_PREMIUM', 'ADDITIONAL PREMIUMS'];

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public array $selectedDivisionIds = [];

    public array $selectedDepartmentIds = [];

    public string $period;

    public int $workingDays = 22;

    public int $gsisDays = 30;

    public array $selectedLeaveTypeIds = [];

    public array $employeeFilterIds = [];

    public array $appliedEmployeeFilterIds = [];

    public string $employeeTypeFilter = Employee::EMPLOYEE_TYPE_PLANTILLA;

    #[Url(as: 'step', except: 1)]
    public int $currentStep = 1;

    public array $deductionDayOverrides = [];

    public array $logbookLwopDayOverrides = [];

    public array $leaveDeductionOverrides = [];

    public array $leaveDateOverrides = [];

    public array $payBasisOverrides = [];

    public array $compensationAdjustments = [];

    public array $mandatoryDeductionAdjustments = [];

    public array $taxAnnualizationOverrides = [];

    public $taxAnnualizationFile;

    public ?string $taxAnnualizationImportMessage = null;

    public array $selectedAdjustmentTypeIds = [];

    public array $deductionProgramSelections = [];

    public bool $showLoanImportModal = false;

    public $loanFile;

    public ?string $pendingLoanImportPath = null;

    public ?string $pendingLoanImportOriginalFilename = null;

    public array $loanImportPreview = [];

    public bool $showLoanDeductionModal = false;

    public ?int $editingLoanItemId = null;

    public array $loanDeductionForm = [
        'emp_id' => '',
        'loan_type_id' => '',
        'loan_account_no' => '',
        'monthly_amortization' => '',
        'amount_due' => '',
        'outstanding_balance' => '',
        'principal_due' => '',
        'interest_due' => '',
        'penalty_due' => '',
        'remarks' => '',
    ];

    public ?array $recentLoanSuggestion = null;

    public array $steps = [
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

    public array $loanColumnGroups = [];

    public ?int $finalizedRunId = null;

    public array $finalizedSummary = [];

    public ?int $activeDraftId = null;

    public ?string $draftSavedAt = null;

    public ?string $draftNotice = null;

    private ?StatutoryContributionService $statutoryContributionService = null;

    public function mount(): void
    {
        $userDepartmentId = auth()->user()?->employee?->department_id;
        $userDivisionId = $userDepartmentId
            ? Department::query()->where('department_id', $userDepartmentId)->value('division_id')
            : null;

        $this->selectedDivisionIds = $this->parseIdList(request()->query('division_ids', request()->query('division_id')));
        if ($this->selectedDivisionIds === [] && $userDivisionId) {
            $this->selectedDivisionIds = [(int) $userDivisionId];
        }
        $this->selectedDepartmentIds = $this->parseIdList(request()->query('department_ids', request()->query('department_id')));
        $this->syncLegacyScopeIds();

        if ($this->selectedDepartmentIds !== [] && $this->selectedDivisionIds !== []) {
            $this->selectedDepartmentIds = Department::query()
                ->whereIn('department_id', $this->selectedDepartmentIds)
                ->whereIn('division_id', $this->selectedDivisionIds)
                ->pluck('department_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $this->syncLegacyScopeIds();
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: $this->workingDays));
        $this->gsisDays = max(0, min(31, request()->integer('gsis_days') ?: $this->gsisDays));
        $this->selectedLeaveTypeIds = $this->hasExplicitLeaveTypeSelection(request()->query('leave_type_ids'))
            ? $this->parseSelectedLeaveTypeIds(request()->query('leave_type_ids', []))
            : $this->defaultSelectedLeaveTypeIds();

        $employeeType = request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA);
        $this->employeeTypeFilter = array_key_exists($employeeType, Employee::employeeTypeOptions())
            ? $employeeType
            : Employee::EMPLOYEE_TYPE_PLANTILLA;
        $this->employeeFilterIds = $this->parseEmployeeIdList(request()->query('employee_ids', []));
        $this->appliedEmployeeFilterIds = $this->employeeFilterIds;
        $this->loanColumnGroups = app(PayrollLoanReferenceService::class)->columnGroups();
        $this->restoreDraft();
        $this->currentStep = max(1, min(count($this->steps), request()->integer('step') ?: $this->currentStep));
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

    public function updatedLeaveDateOverrides(mixed $value, string $key): void
    {
        $leaveId = (int) str($key)->before('.')->toString();
        if ($leaveId <= 0) {
            return;
        }

        $empId = EmployeeLeave::query()->whereKey($leaveId)->value('emp_id');
        if (! $empId) {
            return;
        }

        unset($this->leaveDeductionOverrides[$empId], $this->deductionDayOverrides[$empId]);
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

    public function applyEmployeeFilter(): void
    {
        $this->employeeFilterIds = $this->parseEmployeeIdList($this->employeeFilterIds);
        $this->appliedEmployeeFilterIds = $this->employeeFilterIds;
    }

    public function clearEmployeeFilter(): void
    {
        $this->employeeFilterIds = [];
        $this->appliedEmployeeFilterIds = [];
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

    public function openLoanDeductionModal(string $empId = '', ?int $loanItemId = null): void
    {
        $this->resetLoanDeductionForm();
        $this->editingLoanItemId = $loanItemId;

        if ($loanItemId) {
            $item = PayrollLoanImportItem::query()->find($loanItemId);
            if (! $item) {
                $this->addError('loanDeductionForm', 'Loan deduction not found.');

                return;
            }

            $loanType = $this->loanTypeForItem($item);
            $this->loanDeductionForm = [
                'emp_id' => (string) $item->matched_emp_id,
                'loan_type_id' => $loanType?->id ? (string) $loanType->id : '',
                'loan_account_no' => (string) $item->loan_account_no,
                'monthly_amortization' => (string) $item->monthly_amortization,
                'amount_due' => (string) $item->amount_due,
                'outstanding_balance' => (string) ($item->outstanding_balance ?? ''),
                'principal_due' => (string) ($item->principal_due ?? ''),
                'interest_due' => (string) ($item->interest_due ?? ''),
                'penalty_due' => (string) ($item->penalty_due ?? ''),
                'remarks' => (string) ($item->remarks ?? ''),
            ];
        } else {
            $this->loanDeductionForm['emp_id'] = $empId;
        }

        $this->refreshRecentLoanSuggestion();
        $this->resetValidation('loanDeductionForm');
        $this->showLoanDeductionModal = true;
    }

    public function closeLoanDeductionModal(): void
    {
        $this->showLoanDeductionModal = false;
        $this->resetLoanDeductionForm();
        $this->resetValidation('loanDeductionForm');
    }

    public function saveLoanDeduction(): void
    {
        foreach (['monthly_amortization', 'outstanding_balance', 'principal_due', 'interest_due', 'penalty_due'] as $field) {
            if (($this->loanDeductionForm[$field] ?? null) === '') {
                $this->loanDeductionForm[$field] = null;
            }
        }

        $data = $this->validate([
            'loanDeductionForm.emp_id' => ['required', 'string'],
            'loanDeductionForm.loan_type_id' => ['required', 'integer'],
            'loanDeductionForm.loan_account_no' => ['nullable', 'string', 'max:120'],
            'loanDeductionForm.monthly_amortization' => ['nullable', 'numeric', 'min:0'],
            'loanDeductionForm.amount_due' => ['required', 'numeric', 'min:0'],
            'loanDeductionForm.outstanding_balance' => ['nullable', 'numeric', 'min:0'],
            'loanDeductionForm.principal_due' => ['nullable', 'numeric', 'min:0'],
            'loanDeductionForm.interest_due' => ['nullable', 'numeric', 'min:0'],
            'loanDeductionForm.penalty_due' => ['nullable', 'numeric', 'min:0'],
            'loanDeductionForm.remarks' => ['nullable', 'string'],
        ], [
            'loanDeductionForm.emp_id.required' => 'Choose an employee.',
            'loanDeductionForm.loan_type_id.required' => 'Choose a loan type.',
            'loanDeductionForm.amount_due.required' => 'Enter the amount due.',
        ])['loanDeductionForm'];

        $employee = Employee::query()->where('emp_id', $data['emp_id'])->first();
        $loanType = PayrollLoanType::query()->with('entity')->find((int) $data['loan_type_id']);

        if (! $employee || ! $loanType) {
            $this->addError('loanDeductionForm', 'Choose a valid employee and loan type.');

            return;
        }

        $periodStart = $this->selectedPeriodStart();
        $amountDue = $this->moneyValue($data['amount_due']);
        $monthlyAmortization = $data['monthly_amortization'] === null || $data['monthly_amortization'] === ''
            ? $amountDue
            : $this->moneyValue($data['monthly_amortization']);
        $existingItem = $this->editingLoanItemId
            ? PayrollLoanImportItem::query()->find($this->editingLoanItemId)
            : null;
        $import = $existingItem?->import ?: $this->manualLoanImportFor($periodStart);
        $payload = [
            'import_id' => $import->id,
            'entity' => $loanType->entity?->name ?? $loanType->entity?->code ?? 'Manual',
            'due_month' => $periodStart->toDateString(),
            'employee_id' => $employee->emp_id,
            'matched_emp_id' => $employee->emp_id,
            'employee_name' => $this->formatPayrollEmployeeName($employee),
            'loan_account_no' => trim((string) ($data['loan_account_no'] ?? '')),
            'loan_type' => $loanType->name,
            'monthly_amortization' => $monthlyAmortization,
            'amount_due' => $amountDue,
            'outstanding_balance' => $this->nullableMoneyValue($data['outstanding_balance'] ?? null),
            'principal_due' => $this->nullableMoneyValue($data['principal_due'] ?? null),
            'interest_due' => $this->nullableMoneyValue($data['interest_due'] ?? null),
            'penalty_due' => $this->nullableMoneyValue($data['penalty_due'] ?? null),
            'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
            'validation_status' => 'valid',
            'validation_errors' => null,
        ];

        if ($existingItem) {
            $existingItem->update($payload);
        } else {
            $payload['row_number'] = ((int) PayrollLoanImportItem::query()->where('import_id', $import->id)->max('row_number')) + 1;
            PayrollLoanImportItem::query()->create($payload);
        }

        $this->refreshLoanImportCounts($import->id);
        $this->closeLoanDeductionModal();
        session()->flash('loan_import_status', 'Loan deduction saved.');
        $this->dispatch('loan-deduction-saved');
    }

    public function saveLoanDeductionsBatch(array $forms): void
    {
        if ($forms === []) {
            $this->addError('loanDeductionForm', 'Add at least one loan deduction before saving the batch.');

            return;
        }

        $periodStart = $this->selectedPeriodStart();
        $import = $this->manualLoanImportFor($periodStart);
        $nextRowNumber = ((int) PayrollLoanImportItem::query()->where('import_id', $import->id)->max('row_number')) + 1;
        $preparedRows = [];
        $saved = 0;

        foreach (array_values($forms) as $index => $form) {
            $validator = validator($this->normalizeManualLoanForm((array) $form), [
                'emp_id' => ['required', 'string'],
                'loan_type_id' => ['required', 'integer'],
                'loan_account_no' => ['nullable', 'string', 'max:120'],
                'monthly_amortization' => ['nullable', 'numeric', 'min:0'],
                'amount_due' => ['required', 'numeric', 'min:0'],
                'outstanding_balance' => ['nullable', 'numeric', 'min:0'],
                'principal_due' => ['nullable', 'numeric', 'min:0'],
                'interest_due' => ['nullable', 'numeric', 'min:0'],
                'penalty_due' => ['nullable', 'numeric', 'min:0'],
                'remarks' => ['nullable', 'string'],
            ], [
                'emp_id.required' => 'Choose an employee for row '.($index + 1).'.',
                'loan_type_id.required' => 'Choose a loan type for row '.($index + 1).'.',
                'amount_due.required' => 'Enter the amount due for row '.($index + 1).'.',
            ]);

            if ($validator->fails()) {
                $this->addError('loanDeductionForm', $validator->errors()->first());

                return;
            }

            $data = $validator->validated();

            $employee = Employee::query()->where('emp_id', $data['emp_id'])->first();
            $loanType = PayrollLoanType::query()->with('entity')->find((int) $data['loan_type_id']);
            if (! $employee || ! $loanType) {
                $this->addError('loanDeductionForm', 'Choose a valid employee and loan type for row '.($index + 1).'.');

                return;
            }

            $amountDue = $this->moneyValue($data['amount_due']);
            $monthlyAmortization = $data['monthly_amortization'] === null || $data['monthly_amortization'] === ''
                ? $amountDue
                : $this->moneyValue($data['monthly_amortization']);

            $preparedRows[] = [
                'import_id' => $import->id,
                'entity' => $loanType->entity?->name ?? $loanType->entity?->code ?? 'Manual',
                'due_month' => $periodStart->toDateString(),
                'employee_id' => $employee->emp_id,
                'matched_emp_id' => $employee->emp_id,
                'employee_name' => $this->formatPayrollEmployeeName($employee),
                'loan_account_no' => trim((string) ($data['loan_account_no'] ?? '')),
                'loan_type' => $loanType->name,
                'monthly_amortization' => $monthlyAmortization,
                'amount_due' => $amountDue,
                'outstanding_balance' => $this->nullableMoneyValue($data['outstanding_balance'] ?? null),
                'principal_due' => $this->nullableMoneyValue($data['principal_due'] ?? null),
                'interest_due' => $this->nullableMoneyValue($data['interest_due'] ?? null),
                'penalty_due' => $this->nullableMoneyValue($data['penalty_due'] ?? null),
                'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
                'validation_status' => 'valid',
                'validation_errors' => null,
            ];
        }

        DB::connection('payroll')->transaction(function () use ($preparedRows, &$nextRowNumber, &$saved) {
            foreach ($preparedRows as $row) {
                $row['row_number'] = $nextRowNumber++;
                PayrollLoanImportItem::query()->create($row);
                $saved++;
            }
        });

        $this->refreshLoanImportCounts($import->id);
        session()->flash('loan_import_status', "Saved {$saved} loan deduction(s).");
        $this->dispatch('loan-deduction-batch-saved');
    }

    public function saveLoanDeductionFromModal(?int $editingLoanItemId, array $form): void
    {
        $this->editingLoanItemId = $editingLoanItemId;
        $this->loanDeductionForm = array_merge($this->blankLoanDeductionForm(), array_intersect_key($form, $this->blankLoanDeductionForm()));

        $this->saveLoanDeduction();
    }

    public function updatedLoanDeductionForm($value, string $key): void
    {
        if (in_array($key, ['emp_id', 'loan_type_id'], true)) {
            $this->refreshRecentLoanSuggestion();
        }
    }

    private function normalizeManualLoanForm(array $form): array
    {
        $form = array_merge($this->blankLoanDeductionForm(), array_intersect_key($form, $this->blankLoanDeductionForm()));
        foreach (['monthly_amortization', 'outstanding_balance', 'principal_due', 'interest_due', 'penalty_due'] as $field) {
            if (($form[$field] ?? null) === '') {
                $form[$field] = null;
            }
        }

        return $form;
    }

    public function clearLoanReferenceAndAmount(): void
    {
        $this->loanDeductionForm['loan_account_no'] = '';
        $this->loanDeductionForm['amount_due'] = '';
    }

    public function recentLoanSuggestionsForModal(Collection $rows, Collection $loanTypes): array
    {
        $empIds = $rows->pluck('emp_id')->filter()->values()->all();

        if (empty($empIds) || $loanTypes->isEmpty()) {
            return [];
        }

        $loanTypesByName = $loanTypes->keyBy(fn (PayrollLoanType $type) => strtolower($type->name));
        $suggestions = [];

        PayrollLoanImportItem::query()
            ->where('validation_status', 'valid')
            ->whereIn('matched_emp_id', $empIds)
            ->whereDate('due_month', '<', $this->selectedPeriodStart()->toDateString())
            ->orderByDesc('due_month')
            ->orderByDesc('id')
            ->get()
            ->each(function (PayrollLoanImportItem $item) use (&$suggestions, $loanTypesByName) {
                $loanType = $loanTypesByName->get(strtolower((string) $item->loan_type));
                if (! $loanType) {
                    return;
                }

                $key = $item->matched_emp_id.'|'.$loanType->id;
                if (isset($suggestions[$key])) {
                    return;
                }

                $suggestions[$key] = [
                    'loan_account_no' => (string) $item->loan_account_no,
                    'monthly_amortization' => (string) $item->monthly_amortization,
                    'amount_due' => (string) $item->amount_due,
                    'outstanding_balance' => $item->outstanding_balance !== null ? (string) $item->outstanding_balance : '',
                    'principal_due' => $item->principal_due !== null ? (string) $item->principal_due : '',
                    'interest_due' => $item->interest_due !== null ? (string) $item->interest_due : '',
                    'penalty_due' => $item->penalty_due !== null ? (string) $item->penalty_due : '',
                    'due_month' => $item->due_month?->format('M Y'),
                ];
            });

        return $suggestions;
    }


    public function previewLoanImport(): void
    {
        $data = $this->validate([
            'loanFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm,csv', 'max:65536'],
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

    public function importTaxAnnualizationLookup(): void
    {
        $data = $this->validate([
            'taxAnnualizationFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:20480'],
        ]);

        $file = $data['taxAnnualizationFile'];
        $path = $file->getRealPath();
        $columns = [
            'IG' => 'gross_withholding_tax_adjustment',
            'GC' => 'withholding_tax_adjustment',
            'IX' => 'future_months',
            'IY' => 'annualization_leave_without_pay_months',
            'IZ' => 'hazard_subsistence_deduction_months',
            'JA' => 'previous_basic',
            'JF' => 'previous_hazard',
            'JJ' => 'previous_subsistence',
            'JN' => 'previous_mandatory_deductions',
            'JO' => 'current_mandatory_deductions',
            'JU' => 'previous_tax_withheld',
        ];
        $readColumns = ['B', ...array_keys($columns)];
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class($readColumns) implements IReadFilter {
            public function __construct(private array $columns) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 4 || in_array($columnAddress, $this->columns, true);
            }
        });

        $imported = 0;
        foreach (['hopss_finance-done', 'SUMMARY SALARY (2)', 'SUMMARY SALARY'] as $sheetName) {
            try {
                $reader->setLoadSheetsOnly([$sheetName]);
                $spreadsheet = $reader->load($path);
            } catch (\Throwable) {
                continue;
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheetImported = 0;
            for ($row = 5; $row <= $sheet->getHighestDataRow(); $row++) {
                $empId = $this->normalizeImportedEmpId($this->spreadsheetCellValue($sheet, "B{$row}"));
                if ($empId === '') {
                    continue;
                }

                $values = [];
                foreach ($columns as $column => $key) {
                    $value = $this->spreadsheetCellValue($sheet, "{$column}{$row}");
                    if ($value === null || $value === '' || ! is_numeric($value)) {
                        continue;
                    }

                    $values[$key] = round((float) $value, 4);
                }

                if ($values === []) {
                    continue;
                }

                $this->taxAnnualizationOverrides[$empId] = [
                    ...($this->taxAnnualizationOverrides[$empId] ?? []),
                    ...$values,
                ];
                $sheetImported++;
            }

            if ($sheetImported > 0) {
                $imported = $sheetImported;
                break;
            }
        }

        $this->taxAnnualizationFile = null;
        $this->taxAnnualizationImportMessage = $imported > 0
            ? "Imported annualization lookup values for {$imported} employee(s). Click Save as Draft to keep them in the draft."
            : 'No annualization lookup rows were found in the selected workbook.';
    }

    public function saveDraft(): void
    {
        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
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
                'gsis_days' => $this->gsisDays,
                'included_leave_type_ids' => $this->selectedLeaveTypeIds,
                'employee_type' => $this->employeeTypeFilter,
                'current_step' => $this->currentStep,
                'state_json' => [
                    'selected_division_ids' => $this->selectedDivisionIds,
                    'selected_department_ids' => $this->selectedDepartmentIds,
                    'deduction_day_overrides' => $this->deductionDayOverrides,
                    'logbook_lwop_day_overrides' => $this->logbookLwopDayOverrides,
                    'leave_deduction_overrides' => $this->leaveDeductionOverrides,
                    'leave_date_overrides' => $this->leaveDateOverrides,
                    'pay_basis_overrides' => $this->payBasisOverrides,
                    'compensation_adjustments' => $this->compensationAdjustments,
                    'mandatory_deduction_adjustments' => $this->mandatoryDeductionAdjustments,
                    'tax_annualization_overrides' => $this->taxAnnualizationOverrides,
                    'selected_adjustment_type_ids' => $this->selectedAdjustmentTypeIds,
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

    public function saveStepChanges(): void
    {
        $this->saveDraft();
    }

    private function resetLoanImportState(): void
    {
        $this->loanFile = null;
        $this->pendingLoanImportPath = null;
        $this->pendingLoanImportOriginalFilename = null;
        $this->loanImportPreview = [];
        $this->resetValidation('loanFile');
    }

    private function resetLoanDeductionForm(): void
    {
        $this->editingLoanItemId = null;
        $this->recentLoanSuggestion = null;
        $this->loanDeductionForm = $this->blankLoanDeductionForm();
    }

    private function blankLoanDeductionForm(): array
    {
        return [
            'emp_id' => '',
            'loan_type_id' => '',
            'loan_account_no' => '',
            'monthly_amortization' => '',
            'amount_due' => '',
            'outstanding_balance' => '',
            'principal_due' => '',
            'interest_due' => '',
            'penalty_due' => '',
            'remarks' => '',
        ];
    }

    private function refreshRecentLoanSuggestion(): void
    {
        $this->recentLoanSuggestion = null;

        if ($this->editingLoanItemId || empty($this->loanDeductionForm['emp_id']) || empty($this->loanDeductionForm['loan_type_id'])) {
            return;
        }

        $loanType = PayrollLoanType::query()->find((int) $this->loanDeductionForm['loan_type_id']);
        if (! $loanType) {
            return;
        }

        $recent = PayrollLoanImportItem::query()
            ->where('validation_status', 'valid')
            ->where('matched_emp_id', $this->loanDeductionForm['emp_id'])
            ->where('loan_type', $loanType->name)
            ->whereDate('due_month', '<', $this->selectedPeriodStart()->toDateString())
            ->orderByDesc('due_month')
            ->orderByDesc('id')
            ->first();

        if (! $recent) {
            return;
        }

        $this->recentLoanSuggestion = [
            'loan_account_no' => (string) $recent->loan_account_no,
            'monthly_amortization' => (float) $recent->monthly_amortization,
            'amount_due' => (float) $recent->amount_due,
            'outstanding_balance' => $recent->outstanding_balance !== null ? (float) $recent->outstanding_balance : null,
            'principal_due' => $recent->principal_due !== null ? (float) $recent->principal_due : null,
            'interest_due' => $recent->interest_due !== null ? (float) $recent->interest_due : null,
            'penalty_due' => $recent->penalty_due !== null ? (float) $recent->penalty_due : null,
            'due_month' => $recent->due_month?->format('M Y'),
        ];

        foreach (['loan_account_no', 'monthly_amortization', 'amount_due', 'outstanding_balance', 'principal_due', 'interest_due', 'penalty_due'] as $field) {
            if (($this->loanDeductionForm[$field] ?? '') === '' && $this->recentLoanSuggestion[$field] !== null) {
                $this->loanDeductionForm[$field] = (string) $this->recentLoanSuggestion[$field];
            }
        }
    }

    public function saveEmployeeAdjustment(string $empId, int $typeId, string $operator, mixed $amount): void
    {
        if (! is_numeric($amount) || (float) $amount < 0) {
            $this->addError('adjustments', 'Enter a valid adjustment amount.');

            return;
        }

        $type = $this->adjustmentTypes()->firstWhere('id', $typeId);
        if (! $type) {
            $this->addError('adjustments', 'Choose an active adjustment type.');

            return;
        }

        $this->resetValidation('adjustments');
        $this->compensationAdjustments[$empId]['extra_items'][(string) $type->id] = [
            'operator' => strtoupper($operator) === 'LESS' ? 'LESS' : 'ADD',
            'amount' => $this->moneyValue($amount),
        ];
        $this->selectedAdjustmentTypeIds = collect($this->selectedAdjustmentTypeIds)
            ->push((int) $type->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function removeEmployeeAdjustmentType(string $empId, int $typeId): void
    {
        unset($this->compensationAdjustments[$empId]['extra_items'][(string) $typeId]);
        $this->selectedAdjustmentTypeIds = $this->selectedAdjustmentTypeIdsFromAdjustments($this->compensationAdjustments);
    }

    public function exportRegularPayrollTemplate(RegularPayrollTemplateExportService $exporter)
    {
        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
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
        $allAdjustmentTypes = $this->adjustmentTypes();
        $this->syncSelectedAdjustmentTypeIds($allAdjustmentTypes);
        $adjustmentTypes = $this->selectedAdjustmentTypes($allAdjustmentTypes);
        $this->syncDeductionProgramSelections($deductionPrograms);
        $rows = $this->payrollRows($compensations, $deductionPrograms);
        $totals = $this->payrollTotals($rows, $compensations);
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);

        return view('livewire.payroll.payroll-generation', [
            'departments' => Department::query()->orderBy('department')->get(),
            'divisions' => Division::query()->orderBy('division')->get(),
            'employeeFilterOptions' => $this->employeeFilterOptions(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'compensations' => $compensations,
            'deductionPrograms' => $deductionPrograms,
            'allAdjustmentTypes' => $allAdjustmentTypes,
            'adjustmentTypes' => $adjustmentTypes,
            'loanTypes' => $this->loanTypes(false),
            'additionalPremiumTypes' => $this->loanTypes(true),
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
                'hdmf_ps_2_ms' => $rows->sum('statutory_deductions.hdmf_ps_2_ms'),
                'ea_deduction' => $rows->sum('statutory_deductions.ea_deduction'),
            ],
            'statutory_government_shares' => [
                'government_life_retirement' => $rows->sum('statutory_government_shares.government_life_retirement'),
                'ec' => $rows->sum('statutory_government_shares.ec'),
                'government_phic' => $rows->sum('statutory_government_shares.government_phic'),
                'government_pagibig' => $rows->sum('statutory_government_shares.government_pagibig'),
            ],
            'mandatory_deduction_adjustments' => $this->mandatoryDeductionAdjustmentTotals($rows),
            'total_mandatory_deductions' => $rows->sum('total_mandatory_deductions'),
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
                'extra_additions' => $rows->sum('compensation_adjustments.extra_additions'),
                'extra_deductions' => $rows->sum('compensation_adjustments.extra_deductions'),
                'extra_total' => $rows->sum('compensation_adjustments.extra_total'),
                'total' => $rows->sum('compensation_adjustments.total'),
            ],
            'net_compensation' => $rows->sum('net_compensation'),
            'net_before_other_deductions' => $rows->sum('net_before_other_deductions'),
            'total_other_deductions' => $rows->sum('total_other_deductions'),
            'net_after_tax' => $rows->sum('net_after_tax'),
            'program_deductions' => $rows->sum('program_deductions.total'),
            'additional_premiums' => $rows->sum('additional_premiums.total'),
            'loan_deductions' => $rows->sum('loan_deductions.total'),
            'net_after_loan_deductions' => $rows->sum('net_after_loan_deductions'),
            'fifteenth' => $rows->sum('fifteenth'),
            'thirtieth' => $rows->sum('thirtieth'),
        ];
    }

    private function payrollRows(Collection $compensations, Collection $deductionPrograms): Collection
    {
        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
            return collect();
        }

        $adjustmentTypes = $this->selectedAdjustmentTypes();

        $employees = Employee::query()
            ->with(['position', 'department.division'])
            ->when(true, fn ($query) => $this->applyEmployeeScope($query))
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->when($this->appliedEmployeeFilterIds !== [], fn ($query) => $query->whereIn('emp_id', $this->appliedEmployeeFilterIds))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        $salaryMatrix = $this->salaryMatrix();
        $loanReferenceRows = $this->loanReferenceRows();
        $loanReferenceByEntity = $this->loanReferenceByEntity($loanReferenceRows);
        $loanReferenceLookup = $this->loanReferenceLookup($loanReferenceRows);
        $periodStart = $this->selectedPeriodStart();
        $periodEnd = $periodStart->endOfMonth();
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);
        $leavePeriodStart = $previousMraPeriod['start'];
        $leavePeriodEnd = $previousMraPeriod['end'];
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
        $previousTaxAnnualization = $this->previousTaxAnnualizationByEmployee($empIds, $periodStart);
        $leaveQuery = EmployeeLeave::query()
            ->with('leaveType')
            ->whereIn('emp_id', $empIds)
            ->where('status', 0)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $leavePeriodEnd->toDateString())
            ->whereDate('end_date', '>=', $leavePeriodStart->toDateString())
            ->whereDoesntHave('logs', fn ($query) => $query->whereIn('action', self::EXCLUDED_LEAVE_LOG_ACTIONS));

        if ($this->selectedLeaveTypeIds === []) {
            $leaveQuery->whereRaw('1 = 0');
        } else {
            $leaveQuery->whereIn('leave_type', $this->selectedLeaveTypeIds);
        }

        $leaves = $leaveQuery->get()->groupBy('emp_id');
        $excludedLeaveDates = $this->excludedLeaveDates($empIds, $periodStart, $periodEnd);
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

        return $employees->map(function (Employee $employee) use ($compensations, $deductionPrograms, $adjustmentTypes, $salaryMatrix, $loanReferenceByEntity, $loanReferenceLookup, $leaves, $excludedLeaveDates, $labels, $adjustments, $mraAdjustments, $labelOptions, $loanItems, $previousTaxAnnualization, $periodStart, $periodEnd, $leavePeriodStart, $leavePeriodEnd) {
            $payBasis = $this->editablePayBasisFor($employee);
            $salaryGrade = $payBasis['salary_grade'];
            $step = $payBasis['step'];
            $baseBasicSalary = (float) ($salaryMatrix[$salaryGrade][$step] ?? 0);
            $leaveDeduction = $this->leaveDeductionDetails(
                $leaves->get($employee->emp_id, collect()),
                $leavePeriodStart,
                $leavePeriodEnd,
            );
            $leaveDeduction = $this->editableLeaveDeductionFor($employee->emp_id, $leaveDeduction);
            $fallbackDeductionDays = $this->deductionDays(
                $labels->get($employee->emp_id, collect()),
                $adjustments->get($employee->emp_id, collect()),
                $labelOptions,
                $excludedLeaveDates->get($employee->emp_id, collect()),
            );
            $mraAdjustment = $mraAdjustments->get($employee->emp_id);
            $mraDeductionDays = (float) ($mraAdjustment?->adjustment_days ?? $fallbackDeductionDays);
            $payrollLeaveDays = $leaveDeduction['laundry_days'];
            $unauthorizedDays = $this->deductionDaysFor($employee->emp_id, 0);
            $hrisLwopDays = (float) ($leaveDeduction['without_pay_days'] ?? 0);
            $logbookLwopDays = $this->logbookLwopDaysFor($employee->emp_id);
            $lwopDays = round($hrisLwopDays + $logbookLwopDays, 3);
            $effectiveBasicDeductDays = round($lwopDays + $unauthorizedDays, 3);
            $effectiveSubsistenceDeductDays = round($lwopDays + $unauthorizedDays + $leaveDeduction['subsistence_days'] + $leaveDeduction['tev_days'], 3);
            $effectiveLaundryDeductDays = round($lwopDays + $unauthorizedDays + $leaveDeduction['laundry_days'], 3);
            $effectivePeraDeductDays = round(max($leaveDeduction['pera_days'], $effectiveBasicDeductDays), 3);
            $employeePaidDays = round(max(0, max(1, $this->workingDays) - $effectiveBasicDeductDays), 3);
            $employeeGsisDays = round(max(0, max(0, $this->gsisDays) - $effectiveBasicDeductDays), 3);
            $basicSalary = round(($baseBasicSalary / max(1, $this->workingDays)) * $employeePaidDays, 2);
            $variables = [
                'basic_salary' => $basicSalary,
                'gross_basic_salary' => $baseBasicSalary,
                'salary' => $basicSalary,
                'sg' => $salaryGrade,
                'step' => $step,
                'hazard_rate' => $this->hazardRate($salaryGrade),
                'working_days' => max(1, $this->workingDays),
                'gsis_days' => max(0, $this->gsisDays),
                'leave_days' => $effectiveBasicDeductDays,
                'basic_deduct_days' => $effectiveBasicDeductDays,
                'subsistence_deduct_days' => $effectiveSubsistenceDeductDays,
                'pera_deduct_days' => $effectivePeraDeductDays,
                'laundry_deduct_days' => $effectiveLaundryDeductDays,
                'tev_deduct_days' => $leaveDeduction['tev_days'],
                'is_part_time' => $this->isPartTimeEmployee($employee),
                'paid_days' => $employeePaidDays,
                'employee_gsis_days' => $employeeGsisDays,
            ];
            $hazardLeaveDays = $this->hazardLeaveDays($leaveDeduction, $unauthorizedDays);
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

            $statutoryContributions = $this->statutoryContributions($baseBasicSalary, $employeeGsisDays);
            $baseStatutoryDeductions = $statutoryContributions['employee'];
            $baseStatutoryGovernmentShares = $statutoryContributions['employer'];
            $mandatoryDeductionAdjustmentDetails = $this->mandatoryDeductionAdjustmentsFor($employee->emp_id);
            $statutoryDeductions = $this->adjustMandatoryDeductionValues(
                $baseStatutoryDeductions,
                $mandatoryDeductionAdjustmentDetails['items'],
                self::EMPLOYEE_MANDATORY_DEDUCTION_KEYS,
            );
            $statutoryGovernmentShares = $this->adjustMandatoryDeductionValues(
                $baseStatutoryGovernmentShares,
                $mandatoryDeductionAdjustmentDetails['items'],
                self::GOVERNMENT_MANDATORY_DEDUCTION_KEYS,
            );
            $gross = $basicSalary + collect($computed)->sum('amount');
            $compensationAdjustments = $this->compensationAdjustmentsFor($employee->emp_id, $adjustmentTypes);
            $netCompensation = round($gross + $compensationAdjustments['total'], 2);
            $baseMandatoryDeductions = round(collect($baseStatutoryDeductions)->sum(), 2);
            $totalMandatoryDeductions = round(collect($statutoryDeductions)->sum(), 2);
            $netBeforeOtherDeductions = round($netCompensation - $totalMandatoryDeductions, 2);
            $computedHazardPay = $this->compensationAmountByName($computed, ['hazard'], 'computed_amount');
            $hazardForTaxDisplay = $computedHazardPay ?: $taxableHazardPay;
            $regularTaxableCompensation = collect($computed)->sum('taxable_amount');
            $supplementalTaxDue = collect($computed)->sum('supplemental_tax_due');
            $leaveWithoutPayMonths = $this->leaveWithoutPayMonths($effectiveBasicDeductDays);
            $netMonths = max(0, PayrollTaxService::ANNUALIZED_MONTHS - $leaveWithoutPayMonths);
            $taxSubsistence = $this->compensationAmountByName($computed, ['subsistence']);
            $monthlyWithholdingTaxableIncome = round(
                $basicSalary
                + $taxSubsistence
                - (
                    (float) ($statutoryDeductions['life_retirement'] ?? 0)
                    + (float) ($statutoryDeductions['phic'] ?? 0)
                    + (float) ($statutoryDeductions['mandatory_pagibig'] ?? 0)
                    + (float) ($statutoryDeductions['ea_deduction'] ?? 0)
                ),
                2
            );
            $currentTaxMandatoryDeductions = round(
                (float) ($statutoryDeductions['life_retirement'] ?? 0)
                + (float) ($statutoryDeductions['phic'] ?? 0)
                + (float) ($statutoryDeductions['mandatory_pagibig'] ?? 0)
                + (float) ($statutoryDeductions['ea_deduction'] ?? 0),
                2
            );
            $currentTaxMandatoryDeductions = $this->taxAnnualizationOverrideValue($employee->emp_id, 'current_mandatory_deductions', $currentTaxMandatoryDeductions);
            $monthlyWithholdingTaxableIncome = round($basicSalary + $taxSubsistence - $currentTaxMandatoryDeductions, 2);
            $previousAnnualization = $previousTaxAnnualization[$employee->emp_id] ?? [];
            $fallbackPreviousMonths = max(0, $periodStart->month - 1);
            $fallbackPreviousBasic = round($basicSalary * $fallbackPreviousMonths, 2);
            $fallbackPreviousHazard = round($hazardForTaxDisplay * $fallbackPreviousMonths, 2);
            $fallbackPreviousSubsistence = round($taxSubsistence * $fallbackPreviousMonths, 2);
            $fallbackPreviousMandatoryDeductions = round($currentTaxMandatoryDeductions * $fallbackPreviousMonths, 2);
            $taxService = app(PayrollTaxService::class);
            $fallbackPreviousBasicTax = $taxService->monthlyWithholdingTaxDue($monthlyWithholdingTaxableIncome);
            $fallbackPreviousHazardTax = round(max(
                0,
                $taxService->monthlyWithholdingTaxDue($monthlyWithholdingTaxableIncome + $hazardForTaxDisplay) - $fallbackPreviousBasicTax
            ), 2);
            $fallbackPreviousTaxWithheld = round(
                $fallbackPreviousMonths > 0 && (
                    $fallbackPreviousBasic
                    + $fallbackPreviousHazard
                    + $fallbackPreviousSubsistence
                    - $fallbackPreviousMandatoryDeductions
                ) > 250000
                    ? ($fallbackPreviousBasicTax + PayrollTaxService::MONTHLY_WITHHOLDING_TAX_ADJUSTMENT + $fallbackPreviousHazardTax) * $fallbackPreviousMonths
                    : 0,
                2
            );
            $previousBasic = $this->taxAnnualizationOverrideValue($employee->emp_id, 'previous_basic', $previousAnnualization['basic'] ?? $fallbackPreviousBasic);
            $previousHazard = $this->taxAnnualizationOverrideValue($employee->emp_id, 'previous_hazard', $previousAnnualization['hazard'] ?? $fallbackPreviousHazard);
            $previousSubsistence = $this->taxAnnualizationOverrideValue($employee->emp_id, 'previous_subsistence', $previousAnnualization['subsistence'] ?? $fallbackPreviousSubsistence);
            $previousMandatoryDeductions = $this->taxAnnualizationOverrideValue($employee->emp_id, 'previous_mandatory_deductions', $previousAnnualization['mandatory_deductions'] ?? $fallbackPreviousMandatoryDeductions);
            $previousTaxWithheld = $this->taxAnnualizationOverrideValue($employee->emp_id, 'previous_tax_withheld', $previousAnnualization['tax_withheld'] ?? $fallbackPreviousTaxWithheld);
            $futureMonths = $this->taxAnnualizationOverrideValue($employee->emp_id, 'future_months', $this->futureMonthsForTax($employee->date_hired, $periodStart));
            $annualizationLeaveWithoutPayMonths = $this->taxAnnualizationOverrideValue($employee->emp_id, 'annualization_leave_without_pay_months', 0);
            $hazardSubsistenceDeductionMonths = $this->taxAnnualizationOverrideValue($employee->emp_id, 'hazard_subsistence_deduction_months', 0);
            $grossWithholdingTaxAdjustment = $this->taxAnnualizationOverrideValue(
                $employee->emp_id,
                'gross_withholding_tax_adjustment',
                PayrollTaxService::MONTHLY_WITHHOLDING_TAX_ADJUSTMENT
            );
            $withholdingTaxAdjustment = $this->taxAnnualizationOverrideValue($employee->emp_id, 'withholding_tax_adjustment', 0);
            $tax = $this->taxCalculation(
                $basicSalary + $regularTaxableCompensation + $compensationAdjustments['total'],
                $totalMandatoryDeductions,
                $netMonths,
                [
                    'entry_date' => $employee->date_hired?->format('Y-m-d'),
                    'salary_grade' => $salaryGrade ?: null,
                    'salary' => $basicSalary,
                    'subsistence' => $taxSubsistence,
                    'hazard' => $hazardForTaxDisplay,
                    'hazard_rate' => $this->hazardRate($salaryGrade),
                    'hazard_leave_days' => $hazardLeaveDays,
                    'hazard_eligible' => $taxableHazardPay > 0,
                    'hazard_disqualification_days' => 10,
                    'taxable_compensations' => $regularTaxableCompensation,
                    'monthly_withholding_taxable_income' => $monthlyWithholdingTaxableIncome,
                    'current_tax_mandatory_deductions' => $currentTaxMandatoryDeductions,
                    'previous_basic' => $previousBasic,
                    'previous_hazard' => $previousHazard,
                    'previous_subsistence' => $previousSubsistence,
                    'previous_mandatory_deductions' => $previousMandatoryDeductions,
                    'previous_tax_withheld' => $previousTaxWithheld,
                    'future_months' => $futureMonths,
                    'annualization_leave_without_pay_months' => $annualizationLeaveWithoutPayMonths,
                    'hazard_subsistence_deduction_months' => $hazardSubsistenceDeductionMonths,
                    'gross_withholding_tax_adjustment' => $grossWithholdingTaxAdjustment,
                    'withholding_tax_adjustment' => $withholdingTaxAdjustment,
                    'supplemental_tax_due' => $supplementalTaxDue,
                    'tax_adjustment' => $compensationAdjustments['total'],
                    'total_months' => PayrollTaxService::ANNUALIZED_MONTHS,
                    'leave_without_pay_months' => $leaveWithoutPayMonths,
                ],
            );
            $withholdingTax = $tax['monthly_tax_due'];
            $programDeductionItems = $this->programDeductionsFor($employee, $deductionPrograms, $basicSalary);
            $programDeductionTotal = round(collect($programDeductionItems)->sum('amount'), 2);
            $employeeDeductionItems = $loanItems->get($employee->emp_id, collect());
            [$employeePremiumItems, $employeeLoanItems] = $employeeDeductionItems->partition(
                fn (PayrollLoanImportItem $item) => $this->isAdditionalPremiumItem($item)
            );
            $additionalPremiumTotal = round($employeePremiumItems->sum('amount_due'), 2);
            $loanTotal = round($employeeLoanItems->sum('amount_due'), 2);
            $totalOtherDeductions = round($programDeductionTotal + $additionalPremiumTotal + $loanTotal, 2);
            $loanColumns = $this->blankLoanColumns();
            foreach ($employeeLoanItems as $loanItem) {
                $key = $this->loanColumnKeyFromReference($loanItem, $loanReferenceByEntity);
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
            $netAfterAdditionalPremiums = round($netAfterProgramDeductions - $additionalPremiumTotal, 2);
            $netAfterLoanDeductions = round($netAfterAdditionalPremiums - $loanTotal, 2);
            $fifteenth = round($netAfterLoanDeductions / 2, 2);
            $thirtieth = round($netAfterLoanDeductions - $fifteenth, 2);

            return [
                'emp_id' => $employee->emp_id,
                'first_name' => $employee->firstname,
                'middle_name' => $employee->middlename,
                'last_name' => $employee->lastname,
                'extension' => $employee->extension,
                'employee_name' => $this->formatPayrollEmployeeName($employee),
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
                'hris_salary_grade' => (int) ($employee->position?->salary_grade ?? 0) ?: null,
                'hris_step' => max(1, min(8, (int) ($employee->step ?: 1))),
                'salary_grade' => $salaryGrade ?: null,
                'step' => $step,
                'sg_step' => $salaryGrade ? 'SG '.$salaryGrade.' / Step '.$step : '-',
                'deduction_days' => $effectiveBasicDeductDays,
                'lwop_days' => $lwopDays,
                'hris_lwop_days' => $hrisLwopDays,
                'logbook_lwop_days' => $logbookLwopDays,
                'unauthorized_days' => $unauthorizedDays,
                'paid_days' => $employeePaidDays,
                'employee_gsis_days' => $employeeGsisDays,
                'mra_deduction_days' => $unauthorizedDays,
                'mra_adjustment_days' => $mraDeductionDays,
                'mra_minutes' => (int) ($mraAdjustment?->undertime_tardy_minutes ?? 0),
                'has_mra_adjustment' => $mraAdjustment !== null,
                'leave_deduction' => $leaveDeduction,
                'gross_basic_salary' => $baseBasicSalary,
                'basic_salary' => $basicSalary,
                'compensations' => $computed,
                'compensation_adjustments' => $compensationAdjustments,
                'net_compensation' => $netCompensation,
                'base_statutory_deductions' => $baseStatutoryDeductions,
                'base_statutory_government_shares' => $baseStatutoryGovernmentShares,
                'statutory_deductions' => $statutoryDeductions,
                'statutory_government_shares' => $statutoryGovernmentShares,
                'base_mandatory_deductions' => $baseMandatoryDeductions,
                'mandatory_deduction_adjustments' => $mandatoryDeductionAdjustmentDetails,
                'mandatory_deduction_adjustment' => $mandatoryDeductionAdjustmentDetails['employee_total'],
                'total_mandatory_deductions' => $totalMandatoryDeductions,
                'statutory_contribution_details' => $statutoryContributions['details'],
                'tax' => $tax,
                'loan_deductions' => [
                    'total' => $loanTotal,
                    'columns' => $loanColumns,
                    'items' => $employeeLoanItems->map(fn (PayrollLoanImportItem $item) => $this->deductionImportItemPayload($item, $loanReferenceByEntity, $loanReferenceLookup))->values()->all(),
                    'by_entity' => $loanByEntity,
                ],
                'program_deductions' => [
                    'total' => $programDeductionTotal,
                    'items' => $programDeductionItems,
                ],
                'additional_premiums' => [
                    'total' => $additionalPremiumTotal,
                    'items' => $employeePremiumItems->map(fn (PayrollLoanImportItem $item) => $this->deductionImportItemPayload($item, $loanReferenceByEntity, $loanReferenceLookup))->values()->all(),
                ],
                'gross' => $gross,
                'net_before_other_deductions' => $netBeforeOtherDeductions,
                'total_other_deductions' => $totalOtherDeductions,
                'net_after_tax' => $netAfterTax,
                'net_after_program_deductions' => $netAfterProgramDeductions,
                'net_after_additional_premiums' => $netAfterAdditionalPremiums,
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
        $this->logbookLwopDayOverrides = [];
        $this->leaveDeductionOverrides = [];
        $this->leaveDateOverrides = [];
        $this->payBasisOverrides = [];
        $this->compensationAdjustments = [];
        $this->mandatoryDeductionAdjustments = [];
        $this->taxAnnualizationOverrides = [];
        $this->employeeFilterIds = [];
        $this->appliedEmployeeFilterIds = [];
        $this->selectedAdjustmentTypeIds = [];
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
        $this->selectedDivisionIds = $this->normalizedIds($state['selected_division_ids'] ?? $this->selectedDivisionIds);
        $this->selectedDepartmentIds = $this->normalizedIds($state['selected_department_ids'] ?? $this->selectedDepartmentIds);
        $this->syncLegacyScopeIds();
        $this->deductionDayOverrides = (array) ($state['deduction_day_overrides'] ?? []);
        $this->logbookLwopDayOverrides = (array) ($state['logbook_lwop_day_overrides'] ?? []);
        $this->leaveDeductionOverrides = (array) ($state['leave_deduction_overrides'] ?? []);
        $this->leaveDateOverrides = (array) ($state['leave_date_overrides'] ?? []);
        $this->payBasisOverrides = (array) ($state['pay_basis_overrides'] ?? []);
        $this->compensationAdjustments = (array) ($state['compensation_adjustments'] ?? []);
        $this->mandatoryDeductionAdjustments = (array) ($state['mandatory_deduction_adjustments'] ?? []);
        $this->taxAnnualizationOverrides = (array) ($state['tax_annualization_overrides'] ?? []);
        $this->selectedAdjustmentTypeIds = array_key_exists('selected_adjustment_type_ids', $state)
            ? array_values((array) $state['selected_adjustment_type_ids'])
            : $this->selectedAdjustmentTypeIdsFromAdjustments($this->compensationAdjustments);
        $this->deductionProgramSelections = (array) ($state['deduction_program_selections'] ?? []);
        $this->activeDraftId = $draft->id;
        $this->draftSavedAt = $draft->saved_at?->format('M d, Y g:i A');
        $this->draftNotice = 'A saved draft for this configuration was restored.';
    }

    private function draftConfigurationKey(): string
    {
        return PayrollGenerationDraft::configurationKeyForScope(
            $this->selectedDivisionIds,
            $this->selectedDepartmentIds,
            PayrollType::CODE_GENERAL,
            $this->period,
            $this->workingDays,
            $this->employeeTypeFilter,
            $this->gsisDays,
            $this->selectedLeaveTypeIds,
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
            || $name === 'ea'
            || str_contains($name, 'ea deduction')
            || str_contains($name, 'employees association')
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

    private function loanReferenceRows(): Collection
    {
        return DB::connection('payroll')
            ->table('payroll_loan_types as types')
            ->join('payroll_loan_entities as entities', 'entities.id', '=', 'types.entity_id')
            ->where('types.is_active', true)
            ->select([
                'types.id',
                'types.name',
                'types.review_column_key',
                'types.review_column_label',
                'types.match_keywords',
                'types.sort_order',
                'entities.code as entity_code',
                'entities.name as entity_name',
            ])
            ->orderBy('types.sort_order')
            ->orderBy('types.name')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'review_column_key' => (string) $row->review_column_key,
                'review_column_label' => (string) $row->review_column_label,
                'match_keywords' => array_values((array) json_decode((string) $row->match_keywords, true)),
                'entity_code' => strtoupper((string) $row->entity_code),
                'entity_name' => strtoupper((string) $row->entity_name),
            ]);
    }

    private function loanReferenceByEntity(Collection $loanReferenceRows): Collection
    {
        return $loanReferenceRows->reduce(function (Collection $groups, array $type) {
            foreach (array_unique([$type['entity_code'], $type['entity_name']]) as $entity) {
                $groups[$entity] = $groups->get($entity, collect())->push($type);
            }

            return $groups;
        }, collect())->map(fn (Collection $items) => $items->unique('id')->values());
    }

    private function loanReferenceLookup(Collection $loanReferenceRows): Collection
    {
        return $loanReferenceRows
            ->flatMap(fn (array $type) => collect([
                $this->loanLookupKey('name', $type['name']) => $type,
                $this->loanLookupKey('label', $type['review_column_label']) => $type,
                $this->loanLookupKey('key', $type['review_column_key']) => $type,
            ]))
            ->filter(fn ($type, string $key) => $key !== '');
    }

    private function loanTypes(bool $additionalPremiums): Collection
    {
        return PayrollLoanType::query()
            ->with('entity')
            ->where('is_active', true)
            ->whereHas('entity', function ($query) use ($additionalPremiums) {
                if ($additionalPremiums) {
                    $query->where(function ($entityQuery) {
                        foreach (self::ADDITIONAL_PREMIUM_ENTITY_CODES as $code) {
                            $entityQuery->orWhereRaw('UPPER(code) = ?', [strtoupper($code)])
                                ->orWhereRaw('UPPER(name) = ?', [strtoupper($code)]);
                        }
                    });

                    return;
                }

                $query->where(function ($entityQuery) {
                    foreach (self::ADDITIONAL_PREMIUM_ENTITY_CODES as $code) {
                        $entityQuery->whereRaw('UPPER(code) != ?', [strtoupper($code)])
                            ->whereRaw('UPPER(name) != ?', [strtoupper($code)]);
                    }
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function isAdditionalPremiumItem(PayrollLoanImportItem $item): bool
    {
        $entity = strtoupper(trim((string) $item->entity));

        return in_array($entity, self::ADDITIONAL_PREMIUM_ENTITY_CODES, true);
    }

    private function deductionImportItemPayload(PayrollLoanImportItem $item, Collection $loanReferenceByEntity, Collection $loanReferenceLookup): array
    {
        $loanColumnKey = $this->loanColumnKeyFromReference($item, $loanReferenceByEntity);
        $loanType = $this->loanTypeForItemFromReference($item, $loanColumnKey, $loanReferenceLookup);

        return [
            'id' => $item->id,
            'emp_id' => $item->matched_emp_id,
            'entity' => $item->entity,
            'loan_type_id' => $loanType ? (string) $loanType['id'] : '',
            'loan_account_no' => $item->loan_account_no,
            'loan_type' => $item->loan_type,
            'monthly_amortization' => (string) $item->monthly_amortization,
            'amount_due' => (string) $item->amount_due,
            'outstanding_balance' => $item->outstanding_balance !== null ? (string) $item->outstanding_balance : '',
            'principal_due' => $item->principal_due !== null ? (string) $item->principal_due : '',
            'interest_due' => $item->interest_due !== null ? (string) $item->interest_due : '',
            'penalty_due' => $item->penalty_due !== null ? (string) $item->penalty_due : '',
            'remarks' => $item->remarks,
            'imported_at' => $item->import?->imported_at?->format('M d, Y'),
            'source' => $item->import?->original_filename === 'manual-loan-deductions' ? 'Manual' : 'Imported',
        ];
    }

    private function loanLookupKey(string $field, mixed $value): string
    {
        $value = trim(strtolower((string) $value));

        return $value === '' ? '' : $field.'|'.$value;
    }

    private function loanColumnKeyFromReference(PayrollLoanImportItem $item, Collection $loanReferenceByEntity): string
    {
        $entity = strtoupper((string) $item->entity);
        $typeText = strtoupper((string) $item->loan_type.' '.$item->loan_account_no.' '.$item->remarks);
        $types = $loanReferenceByEntity->get($entity, collect());

        foreach ($types as $type) {
            foreach (($type['match_keywords'] ?: []) as $keyword) {
                if ($keyword !== '' && str_contains($typeText, strtoupper((string) $keyword))) {
                    return $type['review_column_key'];
                }
            }
        }

        return ($types->first()['review_column_key'] ?? null)
            ?? match ($entity) {
                'UCPB' => 'ucpb',
                'DBP' => 'dbp',
                'LBP' => 'lbp',
                'COCO' => 'coco',
                default => 'other_loans',
            };
    }

    private function loanTypeForItemFromReference(PayrollLoanImportItem $item, string $loanColumnKey, Collection $loanReferenceLookup): ?array
    {
        return $loanReferenceLookup->get($this->loanLookupKey('name', $item->loan_type))
            ?? $loanReferenceLookup->get($this->loanLookupKey('label', $item->loan_type))
            ?? $loanReferenceLookup->get($this->loanLookupKey('key', $loanColumnKey));
    }

    private function manualLoanImportFor(CarbonImmutable $periodStart): \App\Models\Payroll\PayrollLoanImport
    {
        return \App\Models\Payroll\PayrollLoanImport::query()->firstOrCreate(
            [
                'source_entity' => 'Manual Entry',
                'billing_period' => $periodStart->toDateString(),
                'original_filename' => 'manual-loan-deductions',
            ],
            [
                'stored_path' => null,
                'imported_by' => auth()->user()?->emp_id ?? 'web',
                'imported_at' => now(),
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'status' => 'validated',
            ]
        );
    }

    private function refreshLoanImportCounts(int $importId): void
    {
        $items = PayrollLoanImportItem::query()->where('import_id', $importId)->get();

        \App\Models\Payroll\PayrollLoanImport::query()->whereKey($importId)->update([
            'total_rows' => $items->count(),
            'valid_rows' => $items->where('validation_status', 'valid')->count(),
            'invalid_rows' => $items->where('validation_status', '!=', 'valid')->count(),
        ]);
    }

    private function loanTypeForItem(PayrollLoanImportItem $item): ?PayrollLoanType
    {
        return PayrollLoanType::query()
            ->where('name', $item->loan_type)
            ->orWhere('review_column_label', $item->loan_type)
            ->orWhere('review_column_key', $this->loanColumnKey($item))
            ->first();
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
        if (count($this->selectedDepartmentIds) !== 1) {
            return null;
        }

        return PayrollMraReport::query()
            ->where('department_id', $this->selectedDepartmentIds[0])
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

    private function logbookLwopDaysFor(string $empId): float
    {
        $override = $this->logbookLwopDayOverrides[$empId] ?? null;

        if ($override === null || $override === '' || ! is_numeric($override)) {
            return 0.0;
        }

        return round(max(0, (float) $override), 3);
    }

    private function editablePayBasisFor(Employee $employee): array
    {
        $empId = $employee->emp_id;
        $defaultSalaryGrade = (int) ($employee->position?->salary_grade ?? 0);
        $defaultStep = max(1, min(8, (int) ($employee->step ?: 1)));

        $this->payBasisOverrides[$empId]['salary_grade'] ??= $defaultSalaryGrade ?: '';
        $this->payBasisOverrides[$empId]['step'] ??= $defaultStep;

        return [
            'salary_grade' => $this->salaryGradeValue($this->payBasisOverrides[$empId]['salary_grade'], $defaultSalaryGrade),
            'step' => $this->stepValue($this->payBasisOverrides[$empId]['step'], $defaultStep),
        ];
    }

    private function salaryGradeValue(mixed $value, int $default): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return max(0, $default);
        }

        return max(0, (int) $value);
    }

    private function stepValue(mixed $value, int $default): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return max(1, min(8, $default));
        }

        return max(1, min(8, (int) $value));
    }

    private function leaveDeductionDetails(Collection $leaves, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $calendarDates = [];
        $workingDates = [];
        $periods = [];
        $items = [];
        $withoutPayDays = 0.0;

        foreach ($leaves as $leave) {
            if (! $leave->start_date || ! $leave->end_date) {
                continue;
            }

            $item = $this->editableLeaveDateFor($leave, $periodStart, $periodEnd);
            $items[] = $item;

            if ($item['excluded']) {
                continue;
            }

            $withoutPayDays += (float) ($item['days_without_pay'] ?? 0);

            $start = CarbonImmutable::parse($item['start_date']);
            $end = CarbonImmutable::parse($item['end_date']);

            if ($start->greaterThan($end)) {
                continue;
            }

            $periods[] = $item['period'];

            if (($item['days_without_pay'] ?? 0) > 0 && ($item['days_with_pay'] ?? 0) <= 0) {
                continue;
            }

            for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                $key = $date->toDateString();
                $calendarDates[$key] = true;

                if ($date->isWeekday()) {
                    $workingDates[$key] = true;
                }
            }
        }

        return [
            'items' => $items,
            'periods' => array_values(array_unique($periods)),
            'calendar_days' => count($calendarDates),
            'working_days' => count($workingDates),
            'without_pay_days' => round($withoutPayDays, 3),
            'subsistence_days' => count($calendarDates),
            'pera_days' => 0,
            'laundry_days' => count($workingDates),
            'tev_days' => 0,
        ];
    }

    private function editableLeaveDateFor(EmployeeLeave $leave, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $key = (string) $leave->leave_id;
        $defaultStart = CarbonImmutable::parse($leave->start_date)->max($periodStart);
        $defaultEnd = CarbonImmutable::parse($leave->end_date)->min($periodEnd);
        $this->leaveDateOverrides[$key]['start_date'] ??= $defaultStart->toDateString();
        $this->leaveDateOverrides[$key]['end_date'] ??= $defaultEnd->toDateString();
        $this->leaveDateOverrides[$key]['excluded'] ??= false;

        $start = $this->leaveDateValue($this->leaveDateOverrides[$key]['start_date'] ?? null, $defaultStart)
            ->max($periodStart)
            ->min($periodEnd);
        $end = $this->leaveDateValue($this->leaveDateOverrides[$key]['end_date'] ?? null, $defaultEnd)
            ->max($periodStart)
            ->min($periodEnd);

        if ($end->lessThan($start)) {
            $end = $start;
        }

        $this->leaveDateOverrides[$key]['start_date'] = $start->toDateString();
        $this->leaveDateOverrides[$key]['end_date'] = $end->toDateString();

        return [
            'id' => $leave->leave_id,
            'leave_type' => $leave->leave_type_name ?: $leave->leaveType?->leave_name ?: 'Leave',
            'original_period' => $this->formatLeavePeriod($defaultStart, $defaultEnd),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'period' => $this->formatLeavePeriod($start, $end),
            'excluded' => filter_var($this->leaveDateOverrides[$key]['excluded'] ?? false, FILTER_VALIDATE_BOOL),
            'days_without_pay' => $this->numericLeaveDeductionValue($leave->days_wopay ?? 0),
            'days_with_pay' => $this->numericLeaveDeductionValue($leave->days_wpay ?? 0),
        ];
    }

    private function leaveDateValue(mixed $value, CarbonImmutable $default): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value) : $default;
        } catch (\Throwable) {
            return $default;
        }
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

    private function deductMonthlyAmountByDays(float $amount, int $divisor, float $days): float
    {
        if ($amount <= 0 || $days <= 0) {
            return round(max(0, $amount), 2);
        }

        return round(max(0, $amount - (($amount / max(1, $divisor)) * $days)), 2);
    }

    private function compensationAdjustmentsFor(string $empId, ?Collection $adjustmentTypes = null): array
    {
        $adjustmentTypes ??= $this->adjustmentTypes();
        $adjustments = [];

        foreach (['basic_salary', 'subsistence', 'laundry', 'pera'] as $field) {
            $this->compensationAdjustments[$empId][$field] ??= 0;
            $adjustments[$field] = $this->signedMoneyValue($this->compensationAdjustments[$empId][$field]);
        }

        $this->compensationAdjustments[$empId]['remarks'] ??= '';
        $adjustments['remarks'] = trim((string) $this->compensationAdjustments[$empId]['remarks']);

        $this->compensationAdjustments[$empId]['extra_items'] ??= [];
        $extraItems = [];
        $extraAdditions = 0.0;
        $extraDeductions = 0.0;

        foreach ($adjustmentTypes as $type) {
            $key = (string) $type->id;
            if (! array_key_exists($key, $this->compensationAdjustments[$empId]['extra_items'])) {
                continue;
            }

            $item = (array) ($this->compensationAdjustments[$empId]['extra_items'][$key] ?? []);
            $this->compensationAdjustments[$empId]['extra_items'][$key]['operator'] ??= 'ADD';
            $this->compensationAdjustments[$empId]['extra_items'][$key]['amount'] ??= 0;

            $amount = $this->moneyValue($item['amount'] ?? 0);
            $operator = strtoupper((string) ($item['operator'] ?? 'ADD')) === 'LESS' ? 'LESS' : 'ADD';
            $typeName = $type->name;
            $typeCode = $type->code;
            $signedAmount = $operator === 'LESS' ? -$amount : $amount;

            if ($operator === 'LESS') {
                $extraDeductions += $amount;
            } else {
                $extraAdditions += $amount;
            }

            $extraItems[$key] = [
                'key' => $key,
                'type_id' => $type->id,
                'type' => $typeName,
                'code' => $typeCode,
                'operator' => $operator,
                'amount' => $amount,
                'signed_amount' => $signedAmount,
            ];
        }

        $fixedTotal = round(collect($adjustments)->only([
            'basic_salary',
            'subsistence',
            'laundry',
            'pera',
        ])->sum(), 2);
        $adjustments['extra_items'] = $extraItems;
        $adjustments['extra_additions'] = round($extraAdditions, 2);
        $adjustments['extra_deductions'] = round($extraDeductions, 2);
        $adjustments['extra_total'] = round($extraAdditions - $extraDeductions, 2);
        $adjustments['total'] = round($fixedTotal + $adjustments['extra_total'], 2);
        $adjustments['remarks_missing'] = $fixedTotal !== 0.0 && $adjustments['remarks'] === '';

        return $adjustments;
    }

    private function mandatoryDeductionAdjustmentsFor(string $empId): array
    {
        if (! isset($this->mandatoryDeductionAdjustments[$empId]) || ! is_array($this->mandatoryDeductionAdjustments[$empId])) {
            $legacyValue = $this->mandatoryDeductionAdjustments[$empId] ?? 0;
            $this->mandatoryDeductionAdjustments[$empId] = array_fill_keys($this->mandatoryDeductionKeys(), 0);
            $this->mandatoryDeductionAdjustments[$empId]['ea_deduction'] = $this->signedMoneyValue($legacyValue);
        }

        $items = [];
        foreach ($this->mandatoryDeductionKeys() as $key) {
            $this->mandatoryDeductionAdjustments[$empId][$key] ??= 0;
            $items[$key] = $this->signedMoneyValue($this->mandatoryDeductionAdjustments[$empId][$key]);
        }

        return [
            'items' => $items,
            'employee_total' => round(collect(self::EMPLOYEE_MANDATORY_DEDUCTION_KEYS)->sum(fn (string $key) => $items[$key] ?? 0), 2),
            'government_total' => round(collect(self::GOVERNMENT_MANDATORY_DEDUCTION_KEYS)->sum(fn (string $key) => $items[$key] ?? 0), 2),
        ];
    }

    private function mandatoryDeductionAdjustmentTotals(Collection $rows): array
    {
        return collect($this->mandatoryDeductionKeys())
            ->mapWithKeys(fn (string $key) => [$key => $rows->sum(fn (array $row) => $row['mandatory_deduction_adjustments']['items'][$key] ?? 0)])
            ->merge([
                'employee_total' => $rows->sum('mandatory_deduction_adjustments.employee_total'),
                'government_total' => $rows->sum('mandatory_deduction_adjustments.government_total'),
            ])
            ->all();
    }

    private function adjustMandatoryDeductionValues(array $values, array $adjustments, array $keys): array
    {
        foreach ($keys as $key) {
            $values[$key] = round(max(0, (float) ($values[$key] ?? 0) + (float) ($adjustments[$key] ?? 0)), 2);
        }

        return $values;
    }

    private function mandatoryDeductionKeys(): array
    {
        return array_merge(self::EMPLOYEE_MANDATORY_DEDUCTION_KEYS, self::GOVERNMENT_MANDATORY_DEDUCTION_KEYS);
    }

    private function moneyValue(mixed $value): float
    {
        return is_numeric($value) ? round(max(0, (float) $value), 2) : 0.0;
    }

    private function nullableMoneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? round(max(0, (float) $value), 2) : null;
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
        if ($missingRemarks->isNotEmpty()) {
            $names = $missingRemarks->take(3)->implode(', ');
            $suffix = $missingRemarks->count() > 3 ? ' and others' : '';
            $this->addError('adjustments', "Enter adjustment remarks for {$names}{$suffix} before exporting or finalizing.");

            return false;
        }

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

    private function deductionDays(Collection $labels, Collection $adjustments, Collection $labelOptions, Collection $excludedDates): float
    {
        $leaveDays = $labels
            ->filter(function (PayrollDtrLabel $label) use ($labelOptions, $excludedDates) {
                $code = strtoupper((string) $label->label);
                $name = strtoupper((string) ($labelOptions->get($label->label)?->name ?? $label->label));

                if ($excludedDates->contains($label->dtr_date->toDateString())) {
                    return false;
                }

                return str_contains($name, 'LEAVE')
                    || in_array($code, ['VL', 'SL', 'FL', 'SPL', 'LWOP', 'LEAVE_WITHOUT_PAY'], true);
            })
            ->count();

        $undertimeDays = $adjustments->sum('minutes') / 480;

        return round($leaveDays + $undertimeDays, 3);
    }

    private function statutoryContributions(float $grossBasicSalary, float $employeeGsisDays): array
    {
        $service = $this->statutoryContributionService();
        $contributions = $service->calculate(
            $grossBasicSalary,
            $this->selectedPeriodStart(),
        );

        $gsisBaseSalary = round($grossBasicSalary * (max(0, min(30, $employeeGsisDays)) / 30), 2);
        $gsisContributions = $service->calculate(
            $gsisBaseSalary,
            $this->selectedPeriodStart(),
        );

        $contributions['employee']['life_retirement'] = $gsisContributions['employee']['life_retirement'] ?? 0.0;
        $contributions['employer']['government_life_retirement'] = $gsisContributions['employer']['government_life_retirement'] ?? 0.0;
        $contributions['details']['gsis_life_retirement'] = $gsisContributions['details']['gsis_life_retirement'] ?? ($contributions['details']['gsis_life_retirement'] ?? []);
        $contributions['employee_total'] = round(array_sum($contributions['employee']), 2);
        $contributions['employer_total'] = round(array_sum($contributions['employer']), 2);

        return $contributions;
    }

    private function statutoryContributionService(): StatutoryContributionService
    {
        return $this->statutoryContributionService ??= app(StatutoryContributionService::class);
    }

    private function excludedLeaveDates(array $empIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Collection
    {
        return EmployeeLeave::query()
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->whereHas('logs', fn ($query) => $query->whereIn('action', self::EXCLUDED_LEAVE_LOG_ACTIONS))
            ->get()
            ->flatMap(function (EmployeeLeave $leave) use ($periodStart, $periodEnd) {
                $dates = [];
                $start = CarbonImmutable::parse($leave->start_date)->max($periodStart);
                $end = CarbonImmutable::parse($leave->end_date)->min($periodEnd);

                for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                    $dates[] = [
                        'emp_id' => $leave->emp_id,
                        'date' => $date->toDateString(),
                    ];
                }

                return $dates;
            })
            ->groupBy('emp_id')
            ->map(fn (Collection $items) => $items->pluck('date')->unique()->values());
    }

    private function parseSelectedLeaveTypeIds(mixed $value): array
    {
        if ($value === 'none') {
            return [];
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return $this->normalizedLeaveTypeIds($values);
    }

    private function hasExplicitLeaveTypeSelection(mixed $value): bool
    {
        return $value === 'none' || (is_string($value) && trim($value) !== '') || (is_array($value) && $value !== []);
    }

    private function parseIdList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return $this->normalizedIds(is_array($value) ? $value : explode(',', (string) $value));
    }

    private function parseEmployeeIdList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedIds(array $values): array
    {
        return collect($values)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function syncLegacyScopeIds(): void
    {
        $this->selectedDivisionIds = $this->normalizedIds($this->selectedDivisionIds);
        $this->selectedDepartmentIds = $this->normalizedIds($this->selectedDepartmentIds);
        $this->divisionId = $this->selectedDivisionIds[0] ?? null;
        $this->departmentId = $this->selectedDepartmentIds[0] ?? null;
    }

    private function applyEmployeeScope($query)
    {
        if ($this->selectedDepartmentIds !== []) {
            return $query->whereIn('department_id', $this->selectedDepartmentIds);
        }

        return $query->whereHas(
            'department',
            fn ($departmentQuery) => $departmentQuery->whereIn('division_id', $this->selectedDivisionIds)
        );
    }

    private function employeeFilterOptions(): Collection
    {
        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
            return collect();
        }

        return Employee::query()
            ->select([
                'emp_id',
                'firstname',
                'middlename',
                'lastname',
                'extension',
                'suffix',
                'position_id',
                'department_id',
                'is_active',
            ])
            ->with(['position:position_id,position_title'])
            ->when(true, fn ($query) => $this->applyEmployeeScope($query))
            ->where('is_active', 'Y')
            ->employeeType($this->employeeTypeFilter)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get()
            ->map(fn (Employee $employee) => [
                'emp_id' => (string) $employee->emp_id,
                'label' => trim($employee->emp_id.' - '.$this->formatPayrollEmployeeName($employee).' - '.($employee->position?->position_title ?? 'No position'), ' -'),
            ]);
    }

    private function scopeName(): string
    {
        if (count($this->selectedDepartmentIds) === 1) {
            return Department::query()
                ->where('department_id', $this->selectedDepartmentIds[0])
                ->value('department') ?: 'Selected Department';
        }

        if (count($this->selectedDepartmentIds) > 1) {
            return count($this->selectedDepartmentIds).' Departments';
        }

        if (count($this->selectedDivisionIds) === 1) {
            $division = Division::query()
                ->where('division_id', $this->selectedDivisionIds[0])
                ->value('division');

            return $division ? "{$division} Division" : 'Selected Division';
        }

        return count($this->selectedDivisionIds).' Divisions';
    }

    private function normalizedLeaveTypeIds(array $values): array
    {
        $validLeaveTypeIds = $this->validLeaveTypeIds();

        return collect($values)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $validLeaveTypeIds, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function defaultSelectedLeaveTypeIds(): array
    {
        return collect($this->validLeaveTypeIds())
            ->reject(fn (int $id) => in_array($id, self::DEFAULT_UNCHECKED_LEAVE_TYPE_IDS, true))
            ->values()
            ->all();
    }

    private function validLeaveTypeIds(): array
    {
        return LeaveType::query()
            ->pluck('leave_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function taxCalculation(float $monthlyGrossIncome, float $monthlyMandatoryDeductions, float $netMonths, array $context = []): array
    {
        $taxService = app(PayrollTaxService::class);
        $calculation = $taxService->calculation($monthlyGrossIncome, $monthlyMandatoryDeductions, $netMonths);
        $annualization = $taxService->annualization([
            'current_basic' => $context['salary'] ?? $monthlyGrossIncome,
            'current_hazard' => $context['hazard'] ?? 0,
            'current_subsistence' => $context['subsistence'] ?? 0,
            'current_mandatory_deductions' => $context['current_tax_mandatory_deductions'] ?? $monthlyMandatoryDeductions,
            'previous_basic' => $context['previous_basic'] ?? 0,
            'previous_hazard' => $context['previous_hazard'] ?? 0,
            'previous_subsistence' => $context['previous_subsistence'] ?? 0,
            'previous_mandatory_deductions' => $context['previous_mandatory_deductions'] ?? 0,
            'previous_tax_withheld' => $context['previous_tax_withheld'] ?? 0,
            'future_months' => $context['future_months'] ?? 0,
            'leave_without_pay_months' => $context['annualization_leave_without_pay_months'] ?? 0,
            'hazard_subsistence_deduction_months' => $context['hazard_subsistence_deduction_months'] ?? 0,
            'hazard_rate' => $context['hazard_rate'] ?? 0,
            'gross_withholding_tax_adjustment' => $context['gross_withholding_tax_adjustment'] ?? PayrollTaxService::MONTHLY_WITHHOLDING_TAX_ADJUSTMENT,
            'supplemental_tax_due' => $context['supplemental_tax_due'] ?? 0,
            'withholding_tax_adjustment' => $context['withholding_tax_adjustment'] ?? 0,
        ]);

        return [
            ...$context,
            ...$calculation,
            ...$annualization,
            'monthly_net_income' => round($monthlyGrossIncome - $monthlyMandatoryDeductions, 2),
        ];
    }

    private function previousTaxAnnualizationByEmployee(array $empIds, CarbonImmutable $periodStart): array
    {
        if ($empIds === []) {
            return [];
        }

        return PayrollBatchRecord::query()
            ->with('batch')
            ->whereIn('emp_id', $empIds)
            ->whereHas('batch', function ($query) use ($periodStart) {
                $query
                    ->where('payroll_type_code', PayrollType::CODE_GENERAL)
                    ->where('payroll_period', '>=', $periodStart->format('Y-01'))
                    ->where('payroll_period', '<', $periodStart->format('Y-m'));
            })
            ->get()
            ->groupBy('emp_id')
            ->map(function (Collection $records) {
                return $records->reduce(function (array $carry, PayrollBatchRecord $record) {
                    $snapshot = $record->snapshot_json ?? [];
                    $tax = $snapshot['tax'] ?? [];
                    $earnings = $snapshot['earnings'] ?? [];
                    $carry['basic'] += (float) ($tax['current_basic'] ?? $earnings['basic_salary'] ?? $tax['salary'] ?? 0);
                    $carry['hazard'] += (float) ($tax['current_hazard'] ?? $tax['hazard'] ?? 0);
                    $carry['subsistence'] += (float) ($tax['current_subsistence'] ?? $tax['subsistence'] ?? 0);
                    $carry['mandatory_deductions'] += (float) ($tax['current_mandatory_deductions'] ?? $tax['monthly_mandatory_deductions'] ?? 0);
                    $carry['tax_withheld'] += (float) (
                        $tax['current_tax_withheld']
                        ?? (($tax['monthly_tax_due'] ?? 0) + ($tax['current_hazard_tax_due'] ?? 0))
                    );

                    return $carry;
                }, [
                    'basic' => 0.0,
                    'hazard' => 0.0,
                    'subsistence' => 0.0,
                    'mandatory_deductions' => 0.0,
                    'tax_withheld' => 0.0,
                ]);
            })
            ->all();
    }

    private function taxAnnualizationOverrideValue(string $empId, string $key, float $default): float
    {
        $value = $this->taxAnnualizationOverrides[$empId][$key] ?? null;

        if ($value === null || $value === '') {
            return round($default, 2);
        }

        return round((float) $value, 2);
    }

    private function spreadsheetCellValue($sheet, string $coordinate): mixed
    {
        $cell = $sheet->getCell($coordinate);
        if ($cell->isFormula()) {
            $cachedValue = $cell->getOldCalculatedValue();
            if ($cachedValue !== null && $cachedValue !== '') {
                return $cachedValue;
            }
        }

        try {
            return $cell->getCalculatedValue();
        } catch (\Throwable) {
            return $cell->getValue();
        }
    }

    private function normalizeImportedEmpId(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (is_numeric($text)) {
            return str_pad((string) (int) $text, 6, '0', STR_PAD_LEFT);
        }

        return $text;
    }

    private function futureMonthsForTax(mixed $appointmentDate, CarbonImmutable $periodStart): float
    {
        if ($periodStart->month >= 12) {
            return 0.0;
        }

        $futureStart = $periodStart->addMonthNoOverflow()->startOfMonth();
        $futureEnd = $periodStart->endOfYear();

        if ($appointmentDate) {
            $appointment = CarbonImmutable::parse($appointmentDate)->startOfDay();
            if ($appointment->greaterThan($futureEnd)) {
                return 0.0;
            }

            if ($appointment->greaterThan($futureStart)) {
                $futureStart = $appointment;
            }
        }

        $weekdays = 0;
        for ($date = $futureStart; $date->lessThanOrEqualTo($futureEnd); $date = $date->addDay()) {
            if ($date->isWeekday()) {
                $weekdays++;
            }
        }

        return round(min(12 - $periodStart->month, max(0, $weekdays / 22)), 4);
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

    private function isSubsistenceCompensation(PayrollAdditional $item): bool
    {
        return str_contains($this->compensationSearchText($item), 'subsistence');
    }

    private function isLaundryCompensation(PayrollAdditional $item): bool
    {
        return str_contains($this->compensationSearchText($item), 'laundry');
    }

    private function isPeraCompensation(PayrollAdditional $item): bool
    {
        $text = $this->compensationSearchText($item);

        return str_contains($text, 'pera')
            || str_contains($text, 'personal economic relief');
    }

    private function compensationSearchText(PayrollAdditional $item): string
    {
        return strtolower(implode(' ', [
            (string) $item->name,
            (string) $item->variable_name,
        ]));
    }

    private function compensations(): Collection
    {
        return PayrollAdditional::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (PayrollAdditional $item) => [
                $this->compensationDisplayRank($item),
                (int) ($item->sort_order ?? 0),
                (string) $item->name,
            ])
            ->values();
    }

    private function compensationDisplayRank(PayrollAdditional $item): int
    {
        $name = str($item->name)->lower()->toString();

        return match (true) {
            str_contains($name, 'subsistence') => 10,
            str_contains($name, 'laundry') => 20,
            str_contains($name, 'pera') || str_contains($name, 'personal economic relief') => 30,
            default => 100,
        };
    }

    private function adjustmentTypes(): Collection
    {
        return PayrollAdjustmentType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function selectedAdjustmentTypes(?Collection $adjustmentTypes = null): Collection
    {
        $adjustmentTypes ??= $this->adjustmentTypes();
        $selectedIds = collect($this->selectedAdjustmentTypeIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        return $adjustmentTypes
            ->filter(fn (PayrollAdjustmentType $type) => $selectedIds->contains((int) $type->id))
            ->values();
    }

    private function syncSelectedAdjustmentTypeIds(?Collection $adjustmentTypes = null): void
    {
        $adjustmentTypes ??= $this->adjustmentTypes();
        $validIds = $adjustmentTypes->pluck('id')->map(fn ($id) => (int) $id);

        $this->selectedAdjustmentTypeIds = collect($this->selectedAdjustmentTypeIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $validIds->contains($id))
            ->unique()
            ->values()
            ->all();
    }

    private function selectedAdjustmentTypeIdsFromAdjustments(array $compensationAdjustments): array
    {
        return collect($compensationAdjustments)
            ->flatMap(fn ($adjustments) => (array) ($adjustments['extra_items'] ?? []))
            ->map(fn ($item, $key) => (int) ($item['type_id'] ?? $key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function formatPayrollEmployeeName(Employee $employee): string
    {
        $lastName = trim(implode(' ', array_filter([
            $employee->lastname,
            $employee->extension,
            $employee->suffix,
        ])));
        $firstName = trim((string) $employee->firstname);
        $middleInitial = $employee->middlename
            ? mb_strtoupper(mb_substr(trim((string) $employee->middlename), 0, 1)).'.'
            : null;

        $givenName = trim(implode(' ', array_filter([$firstName, $middleInitial])));

        return trim($lastName.', '.$givenName, ' ,');
    }

    private function salaryMatrix(): array
    {
        $grades = DB::connection('mysql')
            ->table('tbl_salary_grade')
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

        if ($this->isSubsistenceCompensation($item)) {
            return $this->applyPartTimeMultiplier(
                $this->deductMonthlyAmountByDays($value, 30, (float) ($variables['subsistence_deduct_days'] ?? 0)),
                $variables
            );
        }

        if ($this->isLaundryCompensation($item)) {
            return $this->applyPartTimeMultiplier(
                $this->deductMonthlyAmountByDays($value, 22, (float) ($variables['laundry_deduct_days'] ?? 0)),
                $variables
            );
        }

        if ($this->isPeraCompensation($item)) {
            return $this->applyPartTimeMultiplier(
                $this->deductMonthlyAmountByDays($value, 22, (float) ($variables['pera_deduct_days'] ?? 0)),
                $variables
            );
        }

        $amount = match ($type) {
            'percentage' => round($variables['basic_salary'] * ($value > 1 ? $value / 100 : $value), 2),
            'formula' => round($this->evaluateFormula((string) $item->formula, $formulaVariables), 2),
            default => round($value, 2),
        };

        return $amount;
    }

    private function applyPartTimeMultiplier(float $amount, array $variables): float
    {
        return round($amount * (1 - (0.5 * (float) ($variables['is_part_time'] ?? 0))), 2);
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
        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
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
            $scopeName = $this->scopeName();
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
                'total_additions' => collect($totals['compensations'])->sum()
                    + max(0, $totals['compensation_adjustments']['basic_salary'])
                    + max(0, $totals['compensation_adjustments']['subsistence'])
                    + max(0, $totals['compensation_adjustments']['laundry'])
                    + max(0, $totals['compensation_adjustments']['pera'])
                    + $totals['compensation_adjustments']['extra_additions'],
                'total_deductions' => ($totals['net_compensation'] - $totals['net_after_loan_deductions'])
                    + abs(min(0, $totals['compensation_adjustments']['basic_salary']))
                    + abs(min(0, $totals['compensation_adjustments']['subsistence']))
                    + abs(min(0, $totals['compensation_adjustments']['laundry']))
                    + abs(min(0, $totals['compensation_adjustments']['pera']))
                    + $totals['compensation_adjustments']['extra_deductions'],
                'net_pay' => $totals['net_after_loan_deductions'],
            ]);

            $batch = PayrollBatch::create([
                'department_id' => $this->departmentId,
                'division_id' => $this->divisionId,
                'payroll_period' => $this->period,
                'payroll_type' => $payrollType->name,
                'payroll_type_code' => $payrollType->code,
                'working_days' => $this->workingDays,
                'gsis_days' => $this->gsisDays,
                'included_leave_type_ids' => $this->selectedLeaveTypeIds,
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
                    'days_with_dtr' => $row['paid_days'] ?? max(0, $this->workingDays - $row['deduction_days']),
                    'regular_hours' => ($row['paid_days'] ?? max(0, $this->workingDays - $row['deduction_days'])) * 8,
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
                    'department_id' => $row['department_id'],

                    'gross' => $row['net_compensation'],
                    'net' => $row['net_after_loan_deductions'],

                    'fifteenth' => $row['fifteenth'],
                    'thirtieth' => $row['thirtieth'],

                    'snapshot_json' => $this->payrollSnapshotForRow($row, $compensations, $deductionPrograms, $run->id),
                ]);
            }

            $this->syncHrisPayBasis($rows);

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

    private function syncHrisPayBasis(Collection $rows): void
    {
        foreach ($rows as $row) {
            $step = $this->stepValue($row['step'] ?? null, (int) ($row['hris_step'] ?? 1));
            Employee::query()
                ->where('emp_id', $row['emp_id'])
                ->where(function ($query) use ($step) {
                    $query->whereNull('step')->orWhere('step', '!=', $step);
                })
                ->update(['step' => $step]);

            $positionId = $row['position_id'] ?? null;
            $salaryGrade = $this->salaryGradeValue($row['salary_grade'] ?? null, (int) ($row['hris_salary_grade'] ?? 0));
            if (! $positionId || $salaryGrade <= 0) {
                continue;
            }

            Position::query()
                ->where('position_id', $positionId)
                ->where(function ($query) use ($salaryGrade) {
                    $query->whereNull('salary_grade')->orWhere('salary_grade', '!=', $salaryGrade);
                })
                ->update(['salary_grade' => $salaryGrade]);
        }
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
                'line_group' => $amount < 0 ? 'DEDUCTION' : 'EARNING',
                'code' => 'adjustment_'.$code,
                'name' => $label.' Adjustment',
                'amount' => abs($amount),
                'remarks' => $row['compensation_adjustments']['remarks'],
            ];
        }

        foreach (($row['compensation_adjustments']['extra_items'] ?? []) as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => ($item['operator'] ?? 'ADD') === 'LESS' ? 'DEDUCTION' : 'EARNING',
                'code' => 'adjustment_extra_'.str($item['type'] ?: 'other')->slug('_')->limit(40, ''),
                'name' => $item['type'] ?: 'Other Adjustment',
                'amount' => $amount,
                'remarks' => $row['compensation_adjustments']['remarks'] ?: null,
            ];
        }

        foreach ($row['statutory_deductions'] as $code => $amount) {
            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'DEDUCTION',
                'code' => $code,
                'name' => str($code)->replace('_', ' ')->title()->toString(),
                'amount' => $amount,
                'remarks' => 'Mandatory deduction',
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

        foreach ($row['additional_premiums']['items'] ?? [] as $item) {
            $amount = (float) ($item['amount_due'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'DEDUCTION',
                'code' => 'additional_premium_'.$item['id'],
                'name' => $item['loan_type'] ?: 'Additional Premium',
                'amount' => $amount,
                'remarks' => $item['loan_account_no'] ?: 'Additional premium',
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
                'remarks' => 'Loan deduction',
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
                'lwop_days' => $row['lwop_days'] ?? 0,
                'hris_lwop_days' => $row['hris_lwop_days'] ?? 0,
                'logbook_lwop_days' => $row['logbook_lwop_days'] ?? 0,
                'unauthorized_days' => $row['unauthorized_days'] ?? 0,
                'paid_days' => $row['paid_days'] ?? null,
                'employee_gsis_days' => $row['employee_gsis_days'] ?? null,
                'working_days' => $this->workingDays,
                'gsis_days' => $this->gsisDays,
                'included_leave_type_ids' => $this->selectedLeaveTypeIds,
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
            'mandatory_deduction_adjustments' => $row['mandatory_deduction_adjustments'],
            'statutory_contribution_details' => $row['statutory_contribution_details'],
            'tax' => $row['tax'],
            'program_deductions' => $row['program_deductions'],
            'additional_premiums' => $row['additional_premiums'],
            'loan_deductions' => $row['loan_deductions'],
            'totals' => [
                'gross' => $row['gross'],
                'net_compensation' => $row['net_compensation'],
                'base_mandatory_deductions' => $row['base_mandatory_deductions'],
                'mandatory_deduction_adjustment' => $row['mandatory_deduction_adjustment'],
                'total_mandatory_deductions' => $row['total_mandatory_deductions'],
                'net_before_other_deductions' => $row['net_before_other_deductions'],
                'total_other_deductions' => $row['total_other_deductions'],
                'net_after_tax' => $row['net_after_tax'],
                'net_after_program_deductions' => $row['net_after_program_deductions'],
                'net_after_additional_premiums' => $row['net_after_additional_premiums'],
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
            ['label' => 'Compensation Adjustments', 'columns' => array_merge(
                ['adjustment_basic_salary', 'adjustment_subsistence', 'adjustment_laundry', 'adjustment_pera'],
                $this->selectedAdjustmentTypes()->map(fn ($type) => 'adjustment_type_'.$type->id)->all(),
                ['adjustment_remarks', 'net_compensation'],
            )],
            ['label' => 'Mandatory Deductions', 'columns' => ['life_retirement', 'government_life_retirement', 'ec', 'phic', 'government_phic', 'mandatory_pagibig', 'hdmf_ps_2_ms', 'government_pagibig', 'ea_deduction', 'total_mandatory_deductions']],
            ['label' => 'Tax Calculation', 'columns' => [
                'entry_date',
                'tax_salary_grade',
                'tax_salary',
                'tax_subsistence',
                'tax_hazard',
                'tax_gross_compensation',
                'tax_deductions',
                'tax_other_deductions',
                'tax_refunds',
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
                'withholding_tax_gross',
                'withholding_tax_adjustment',
                'withholding_tax',
                'net_after_loan_deductions',
                'fifteenth',
                'thirtieth',
            ]],
            ['label' => 'Deduction Programs', 'columns' => array_merge($deductionPrograms->map(fn ($program) => 'program_'.$program->id)->all(), ['program_total'])],
            ...collect($this->loanColumnGroups)->map(fn (array $columns, string $label) => ['label' => $label, 'columns' => array_keys($columns)])->values()->all(),
            ['label' => 'Net Pay Distribution', 'columns' => ['net_before_other_deductions', 'loan_total']],
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
            'life_retirement' => ['label' => 'GSIS (PS)', 'enabled' => true],
            'government_life_retirement' => ['label' => 'GSIS (GS)', 'enabled' => true],
            'ec' => ['label' => 'EC', 'enabled' => true],
            'phic' => ['label' => 'PHIC (PS)', 'enabled' => true],
            'government_phic' => ['label' => 'PHIC (GS)', 'enabled' => true],
            'mandatory_pagibig' => ['label' => 'HDMF (PS) 1', 'enabled' => true],
            'hdmf_ps_2_ms' => ['label' => 'HDMF (PS) 2 MS', 'enabled' => true],
            'government_pagibig' => ['label' => 'HDMF (GS)', 'enabled' => true],
            'ea_deduction' => ['label' => 'EA Deduction', 'enabled' => true],
            'total_mandatory_deductions' => ['label' => 'Total Mandatory Deductions', 'enabled' => true],
            'annual_taxable_income' => ['label' => 'Taxable Income (Year)', 'enabled' => true],
            'annual_tax_due' => ['label' => 'Tax Due (Year)', 'enabled' => true],
            'regular_monthly_tax_due' => ['label' => 'Regular Tax', 'enabled' => true],
            'supplemental_tax_due' => ['label' => 'Tax Adj', 'enabled' => true],
            'withholding_tax' => ['label' => 'Withholding Tax', 'enabled' => true],
            'net_after_tax' => ['label' => 'Net After Tax', 'enabled' => true],
            'entry_date' => ['label' => 'Entry Date', 'enabled' => true],
            'tax_salary_grade' => ['label' => 'SG', 'enabled' => true],
            'tax_salary' => ['label' => 'Salary', 'enabled' => true],
            'tax_subsistence' => ['label' => 'Subsistence', 'enabled' => true],
            'tax_hazard' => ['label' => 'Hazard', 'enabled' => true],
            'tax_gross_compensation' => ['label' => 'Gross Compensation', 'enabled' => true],
            'tax_deductions' => ['label' => 'Mandatory Deductions', 'enabled' => true],
            'tax_other_deductions' => ['label' => 'Other Deductions', 'enabled' => true],
            'tax_refunds' => ['label' => 'Refunds', 'enabled' => true],
            'tax_monthly_net_income' => ['label' => 'Net Monthly Income', 'enabled' => true],
            'tax_adjustment' => ['label' => 'Comp. Adjustment', 'enabled' => true],
            'tax_total_months' => ['label' => 'Total Months', 'enabled' => true],
            'tax_leave_without_pay_months' => ['label' => 'Leave W/O Pay (Months)', 'enabled' => true],
            'tax_net_months' => ['label' => 'Net, Months', 'enabled' => true],
            'tax_total_gross_income' => ['label' => 'Total Gross Income', 'enabled' => true],
            'tax_total_deductions' => ['label' => 'Total Deductions', 'enabled' => true],
            'withholding_tax_gross' => ['label' => 'GB Withholding Tax (Gross)', 'enabled' => true],
            'withholding_tax_adjustment' => ['label' => 'GC Withholding Tax (Adjustment)', 'enabled' => true],
            'program_total' => ['label' => 'Program Total', 'enabled' => true],
            'net_before_other_deductions' => ['label' => 'Net Before Other Deductions', 'enabled' => true],
            'loan_total' => ['label' => 'TOTAL OTHER DEDUCTIONS', 'enabled' => true],
            'net_after_loan_deductions' => ['label' => 'GD Net Pay', 'enabled' => true],
            'fifteenth' => ['label' => 'GE 15th', 'enabled' => true],
            'thirtieth' => ['label' => 'GF 30th', 'enabled' => true],
        ];

        foreach ($compensations as $item) {
            $columns['compensation_'.$item->id] = ['label' => $item->name, 'enabled' => true];
        }

        foreach ($this->selectedAdjustmentTypes() as $type) {
            $columns['adjustment_type_'.$type->id] = ['label' => $type->name, 'enabled' => true];
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
