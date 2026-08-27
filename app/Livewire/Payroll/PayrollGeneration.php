<?php

namespace App\Livewire\Payroll;

use App\Models\Payroll\Canonical\Department;
use App\Models\Payroll\Canonical\Division;
use App\Models\Payroll\Canonical\Employee;
use App\Models\Payroll\Canonical\EmployeeLeave;
use App\Models\Payroll\Canonical\LeaveType;
use App\Models\Payroll\Canonical\SalaryRate;
use App\Models\Payroll\PayrollAdditional;
use App\Models\Payroll\PayrollAdjustmentType;
use App\Models\Payroll\PayrollAuditLog;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;
use App\Models\Payroll\PayrollDeduction;
use App\Models\Payroll\PayrollDeductionProgramMember;
use App\Models\Payroll\PayrollDtrAdjustment;
use App\Models\Payroll\PayrollDtrLabel;
use App\Models\Payroll\PayrollDtrLabelOption;
use App\Models\Payroll\PayrollEmployeePayrollLine;
use App\Models\Payroll\PayrollEmployeeSnapshot;
use App\Models\Payroll\PayrollExternalEmployeeOverride;
use App\Models\Payroll\PayrollGenerationDraft;
use App\Models\Payroll\PayrollLeaveCreditAdjustment;
use App\Models\Payroll\PayrollLoanImportItem;
use App\Models\Payroll\PayrollLoanType;
use App\Models\Payroll\PayrollMraReport;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollProcessedLeaveDate;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\PayrollTimekeepingSummary;
use App\Models\Payroll\PayrollType;
use App\Services\Payroll\DtrMraInputImportService;
use App\Services\Payroll\EmployeeRosterImportService;
use App\Services\Payroll\LegacyPayrollGenerationTestSource;
use App\Services\Payroll\PayrollLoanImportService;
use App\Services\Payroll\PayrollLoanReferenceService;
use App\Services\Payroll\PayrollOperatingModeService;
use App\Services\Payroll\PayrollReadinessService;
use App\Services\Payroll\PayrollTaxService;
use App\Services\Payroll\RegularPayrollTemplateExportService;
use App\Services\Payroll\StatutoryContributionService;
use App\Services\Payroll\TaxInputImportService;
use App\Support\Hris\LeaveDates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class PayrollGeneration extends Component
{
    use WithFileUploads;

    /** @var Collection<int, PayrollExternalEmployeeOverride>|null */
    private ?Collection $activeExternalEmployeeOverrides = null;

    private const DEFAULT_UNCHECKED_LEAVE_TYPE_IDS = [4, 14, 15, 16, 20, 22];

    private const EXCLUDED_LEAVE_LOG_ACTIONS = [2, 3];

    private const EMPLOYEE_MANDATORY_DEDUCTION_KEYS = [
        'life_retirement',
        'phic',
        'mandatory_pagibig',
    ];

    private const GOVERNMENT_MANDATORY_DEDUCTION_KEYS = [
        'government_life_retirement',
        'ec',
        'government_phic',
        'government_pagibig',
    ];

    private const ADDITIONAL_PREMIUM_ENTITY_CODES = ['ADDITIONAL_PREMIUM', 'ADDITIONAL PREMIUMS'];

    private const HR_GENERATION_PERMISSION = 'payroll.generation.hr';

    private const ACCOUNTING_GENERATION_PERMISSION = 'payroll.generation.accounting';

    public ?int $divisionId = null;

    public ?int $departmentId = null;

    public array $selectedDivisionIds = [];

    public array $selectedDepartmentIds = [];

    public string $period;

    public int $workingDays = 22;

    public int $gsisDays = 30;

    public array $selectedLeaveTypeIds = [];

    public string $leavePeriodStart = '';

    public string $leavePeriodEnd = '';

    public ?string $leavePeriodAppliedMessage = null;

    public array $employeeFilterIds = [];

    public array $appliedEmployeeFilterIds = [];

    /** Employees belonging to a generated workbook comparison; independent from the UI filter. */
    public array $comparisonEmployeeScopeIds = [];

    public array $employeeTypeFilter = [Employee::EMPLOYEE_TYPE_PLANTILLA];

    #[Url(as: 'step', except: 1)]
    public int $currentStep = 1;

    public array $deductionDayOverrides = [];

    public array $logbookLwopDayOverrides = [];

    public $dtrMraFile;

    public array $dtrMraImportPreview = [];

    public ?string $dtrMraImportMessage = null;

    public array $leaveDeductionOverrides = [];

    public array $leaveDateOverrides = [];

    public array $payBasisOverrides = [];

    public array $compensationAdjustments = [];

    public array $mandatoryDeductionAdjustments = [];

    public array $taxAnnualizationOverrides = [];

    public array $loanRefunds = [];

    /** Workbook loan values used only by generated historical comparison drafts. */
    public array $comparisonLoanOverrides = [];

    public $taxAnnualizationFile;

    public ?string $taxAnnualizationImportMessage = null;

    public array $taxInputImportPreview = [];

    public array $selectedAdjustmentTypeIds = [];

    public array $deductionProgramSelections = [];

    public array $otherDeductionRemarks = [];

    public $programRosterFile;

    public ?int $programRosterProgramId = null;

    public array $programRosterPreview = [];

    public $externalRosterFile;

    public array $externalRosterPreview = [];

    public string $tableSearch = '';

    public string $tableSort = 'employee_name';

    public string $tableSortDirection = 'asc';

    public string $programSearch = '';

    public bool $showLoanImportModal = false;

    public bool $showProgramManagerDrawer = false;

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

    public array $steps = PayrollGenerationDraft::WIZARD_STEPS;

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
            ? Department::query()->where('external_id', $userDepartmentId)->value('division_external_id')
            : null;

        $this->selectedDivisionIds = $this->parseIdList(request()->query('division_ids', request()->query('division_id')));
        if ($this->selectedDivisionIds === [] && $userDivisionId) {
            $this->selectedDivisionIds = [(int) $userDivisionId];
        }
        $this->selectedDepartmentIds = $this->parseIdList(request()->query('department_ids', request()->query('department_id')));
        $this->syncLegacyScopeIds();

        if ($this->selectedDepartmentIds !== [] && $this->selectedDivisionIds !== []) {
            $this->selectedDepartmentIds = Department::query()
                ->whereIn('external_id', $this->selectedDepartmentIds)
                ->whereIn('division_external_id', $this->selectedDivisionIds)
                ->pluck('external_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $this->syncLegacyScopeIds();
        }

        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
        $defaultLeavePeriod = $this->previousMraPeriod();
        $this->leavePeriodStart = (string) request()->query('leave_period_start', $defaultLeavePeriod['start']->toDateString());
        $this->leavePeriodEnd = (string) request()->query('leave_period_end', $defaultLeavePeriod['end']->toDateString());
        $this->workingDays = max(1, min(31, request()->integer('working_days') ?: $this->workingDays));
        $this->gsisDays = CarbonImmutable::createFromFormat('!Y-m', $this->period)->daysInMonth;
        $this->selectedLeaveTypeIds = $this->hasExplicitLeaveTypeSelection(request()->query('leave_type_ids'))
            ? $this->parseSelectedLeaveTypeIds(request()->query('leave_type_ids', []))
            : $this->defaultSelectedLeaveTypeIds();

        $this->employeeTypeFilter = Employee::normalizeEmployeeTypes(
            request()->query('employee_type', Employee::EMPLOYEE_TYPE_PLANTILLA)
        );
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

    public function openProgramManagerDrawer(): void
    {
        $this->showProgramManagerDrawer = true;
    }

    public function closeProgramManagerDrawer(): void
    {
        $this->showProgramManagerDrawer = false;
    }

    #[On('deduction-programs-changed')]
    public function refreshDeductionProgramOptions(): void
    {
        // The listener intentionally only invalidates this parent render. Normal
        // browser-side program selection does not make a request.
    }

    public function previousStep(): void
    {
        $this->goToStep($this->currentStep - 1);
    }

    public function payrollGenerationAccess(): array
    {
        $canEditHr = $this->canEditPayrollGenerationHr();
        $canEditAccounting = $this->canEditPayrollGenerationAccounting();
        $steps = [];

        foreach (array_keys($this->steps) as $step) {
            $steps[(int) $step] = [
                'can_edit' => $this->canEditPayrollGenerationStep((int) $step),
            ];
        }

        return [
            'can_edit_hr' => $canEditHr,
            'can_edit_accounting' => $canEditAccounting,
            'can_edit_current_step' => $this->canEditPayrollGenerationStep($this->currentStep),
            'can_edit_step1_hr_fields' => $this->canEditStep1HrFields(),
            'can_edit_step1_tev' => $this->canEditStep1TevField(),
            'steps' => $steps,
        ];
    }

    public function canEditPayrollGenerationHr(): bool
    {
        return (bool) auth()->user()?->can(self::HR_GENERATION_PERMISSION);
    }

    public function canEditPayrollGenerationAccounting(): bool
    {
        return (bool) auth()->user()?->can(self::ACCOUNTING_GENERATION_PERMISSION);
    }

    public function canEditPayrollGenerationStep(?int $step = null): bool
    {
        $step ??= $this->currentStep;

        return match ((int) $step) {
            1 => $this->canEditStep1HrFields() || $this->canEditStep1TevField(),
            2, 7, 8 => $this->canEditPayrollGenerationHr() || $this->canEditPayrollGenerationAccounting(),
            3, 4, 5, 6 => $this->canEditPayrollGenerationHr(),
            default => false,
        };
    }

    public function canEditStep1HrFields(): bool
    {
        return $this->canEditPayrollGenerationHr();
    }

    public function canEditStep1TevField(): bool
    {
        return $this->canEditPayrollGenerationAccounting();
    }

    private function ensureStepCanBeEdited(?int $step = null, ?string $message = null): bool
    {
        if ($this->canEditPayrollGenerationStep($step)) {
            return true;
        }

        $this->addError('authorization', $message ?? 'You can review this payroll step, but you do not have permission to edit it.');

        return false;
    }

    public function applyEmployeeFilter(): void
    {
        $this->employeeFilterIds = $this->parseEmployeeIdList($this->employeeFilterIds);
        $this->appliedEmployeeFilterIds = $this->employeeFilterIds;
    }

    public function clearEmployeeFilter(): void
    {
        $this->employeeFilterIds = [];
        $this->appliedEmployeeFilterIds = $this->comparisonEmployeeScopeIds;
    }

    public function sortTable(string $column): void
    {
        $allowed = ['emp_id', 'employee_name', 'position', 'basic_salary', 'gross', 'net_compensation', 'net_after_loan_deductions'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->tableSort === $column) {
            $this->tableSortDirection = $this->tableSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tableSort = $column;
            $this->tableSortDirection = 'asc';
        }
    }

    public function exportProgramRosterTemplate()
    {
        $program = PayrollDeduction::query()->findOrFail($this->programRosterProgramId);
        $path = app(EmployeeRosterImportService::class)->template($program->name.' membership roster');

        return response()->download($path, str($program->name)->slug('_').'_members.xlsx')->deleteFileAfterSend(true);
    }

    public function previewProgramRoster(): void
    {
        $this->validate([
            'programRosterProgramId' => ['required', 'integer'],
            'programRosterFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);
        PayrollDeduction::query()->findOrFail($this->programRosterProgramId);
        $this->programRosterPreview = app(EmployeeRosterImportService::class)
            ->preview($this->programRosterFile->getRealPath());
    }

    public function confirmProgramRoster(): void
    {
        if (! $this->ensureStepCanBeEdited(4)) {
            return;
        }

        $program = PayrollDeduction::query()->findOrFail($this->programRosterProgramId);
        $validRows = collect($this->programRosterPreview)->where('valid', true);
        if ($validRows->isEmpty()) {
            $this->addError('programRosterFile', 'The workbook has no valid employee rows.');

            return;
        }

        DB::connection('payroll')->transaction(function () use ($program, $validRows) {
            PayrollDeductionProgramMember::query()->where('deduction_program_id', $program->id)->delete();
            foreach ($validRows as $row) {
                PayrollDeductionProgramMember::query()->create([
                    'deduction_program_id' => $program->id,
                    'emp_id' => $row['emp_id'],
                    'employee_name' => $row['employee_name'],
                    'source' => 'excel',
                    'imported_by' => auth()->user()?->emp_id ?? auth()->user()?->username,
                ]);
            }
        });

        $ids = $validRows->pluck('emp_id')->map(fn ($id) => (string) $id)->values()->all();
        $this->deductionProgramSelections[(string) $program->id] = array_merge(
            $this->deductionProgramSelections[(string) $program->id] ?? [],
            ['enabled' => true, 'mode' => 'include', 'employee_ids' => $ids]
        );
        $this->programRosterPreview = [];
        $this->programRosterFile = null;
        session()->flash('program_roster_status', $program->name.' roster replaced with '.count($ids).' employees.');
    }

    public function exportExternalRosterTemplate()
    {
        $path = app(EmployeeRosterImportService::class)->template('External employee registry');

        return response()->download($path, 'external_employee_registry.xlsx')->deleteFileAfterSend(true);
    }

    public function previewExternalRoster(): void
    {
        $this->validate(['externalRosterFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);
        $this->externalRosterPreview = app(EmployeeRosterImportService::class)
            ->preview($this->externalRosterFile->getRealPath());
    }

    public function confirmExternalRoster(): void
    {
        $validRows = collect($this->externalRosterPreview)->where('valid', true);
        if ($validRows->isEmpty()) {
            $this->addError('externalRosterFile', 'The workbook has no valid employee rows.');

            return;
        }

        DB::connection('payroll')->transaction(function () use ($validRows) {
            PayrollExternalEmployeeOverride::query()->update(['is_active' => false]);
            foreach ($validRows as $row) {
                PayrollExternalEmployeeOverride::query()->updateOrCreate(
                    ['emp_id' => $row['emp_id']],
                    [
                        'employee_name' => $row['employee_name'],
                        'source' => 'excel',
                        'is_active' => true,
                        'imported_by' => auth()->user()?->emp_id ?? auth()->user()?->username,
                    ]
                );
            }
        });

        $this->externalRosterPreview = [];
        $this->externalRosterFile = null;
        session()->flash('external_roster_status', 'External employee registry replaced with '.$validRows->count().' employees.');
    }

    public function removeExternalEmployee(int $overrideId): void
    {
        PayrollExternalEmployeeOverride::query()->whereKey($overrideId)->update(['is_active' => false]);
        session()->flash('external_roster_status', 'Employee removed from the payroll-side external registry.');
    }

    public function applyDeductionProgram(int $programId): void
    {
        if (! $this->ensureStepCanBeEdited(4)) {
            return;
        }

        $selection = &$this->deductionProgramSelections[(string) $programId];
        $selection['enabled'] = true;

        if (($selection['amount_mode'] ?? 'program') === 'employee' && ($selection['mode'] ?? 'all') === 'all') {
            $selection['mode'] = 'include';
        }
    }

    public function removeDeductionProgram(int $programId): void
    {
        if (! $this->ensureStepCanBeEdited(4)) {
            return;
        }

        $this->deductionProgramSelections[(string) $programId]['enabled'] = false;
    }

    public function openLoanImportModal(): void
    {
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

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
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

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
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

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
        if (! $this->loanTypeMatchesCurrentDeductionStep($loanType)) {
            $this->addError('loanDeductionForm', 'Choose a valid '.$this->currentDeductionTypeLabel().' type.');

            return;
        }

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
        session()->flash('loan_import_status', $this->currentDeductionLabel().' saved.');
        $this->dispatch('loan-deduction-saved');
    }

    public function saveLoanDeductionsBatch(array $forms): void
    {
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

        if ($forms === []) {
            $this->addError('loanDeductionForm', 'Add at least one '.$this->currentDeductionLabel().' before saving the batch.');

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
            if (! $employee || ! $loanType || ! $this->loanTypeMatchesCurrentDeductionStep($loanType)) {
                $this->addError('loanDeductionForm', 'Choose a valid employee and '.$this->currentDeductionTypeLabel().' type for row '.($index + 1).'.');

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
        session()->flash('loan_import_status', "Saved {$saved} ".$this->currentDeductionLabel().'(s).');
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
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

        $data = $this->validate([
            'loanFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm,csv', 'max:65536'],
        ]);

        $file = $data['loanFile'];
        $storedPath = $file->store('payroll/loan-imports');
        $this->pendingLoanImportPath = $storedPath;
        $this->pendingLoanImportOriginalFilename = $file->getClientOriginalName();
        $this->loanImportPreview = app(PayrollLoanImportService::class)->preview(Storage::path($storedPath), $file->getClientOriginalName(), $this->currentDeductionImportMode());
    }

    public function saveLoanImport(): void
    {
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

        if (! $this->pendingLoanImportPath || empty($this->loanImportPreview)) {
            $this->addError('loanFile', 'Preview the loan file before saving the import.');

            return;
        }

        $this->loanImportPreview = app(PayrollLoanImportService::class)->preview(Storage::path($this->pendingLoanImportPath), $this->pendingLoanImportOriginalFilename, $this->currentDeductionImportMode());
        if (($this->loanImportPreview['invalid_rows'] ?? 0) > 0) {
            $this->addError('loanFile', 'Fix invalid rows before saving the import.');

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
            "Imported {$import->total_rows} ".$this->currentDeductionLabel()." row(s): {$import->valid_rows} ready, {$import->invalid_rows} needing review."
        );
        $this->dispatch('erp-overlay-close', name: 'payroll-loan-import');
    }

    public function importTaxAnnualizationLookup(): void
    {
        if (! $this->ensureStepCanBeEdited(7)) {
            return;
        }

        $data = $this->validate([
            'taxAnnualizationFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:20480'],
        ]);

        $this->taxInputImportPreview = app(TaxInputImportService::class)
            ->preview($data['taxAnnualizationFile']->getRealPath());
        $this->taxAnnualizationImportMessage = collect($this->taxInputImportPreview)->where('valid', true)->count()
            .' valid employee row(s) ready for confirmation.';
    }

    public function exportTaxInputTemplate()
    {
        $employees = Employee::query()
            ->when(true, fn ($query) => $this->applyEmployeeScope($query))
            ->where('is_active', true)
            ->when(true, fn ($query) => $this->applyPayrollEmployeeType($query))
            ->when($this->appliedEmployeeFilterIds !== [], fn ($query) => $query->whereIn('emp_id', $this->appliedEmployeeFilterIds))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix']);

        $path = app(TaxInputImportService::class)->template($employees);

        return response()->download($path, 'payroll_tax_inputs.xlsx')->deleteFileAfterSend(true);
    }

    public function confirmTaxInputImport(): void
    {
        if (! $this->ensureStepCanBeEdited(7)) {
            return;
        }

        $validRows = collect($this->taxInputImportPreview)->where('valid', true);
        if ($validRows->isEmpty()) {
            $this->addError('taxAnnualizationFile', 'The workbook has no valid tax input rows.');

            return;
        }

        foreach ($validRows as $row) {
            $empId = (string) $row['emp_id'];
            $this->taxAnnualizationOverrides[$empId] = [
                ...app(TaxInputImportService::class)->retainedOverrides($this->taxAnnualizationOverrides[$empId] ?? []),
                ...$row['values'],
            ];
        }

        $this->taxAnnualizationFile = null;
        $this->taxInputImportPreview = [];
        $this->taxAnnualizationImportMessage = "Applied tax inputs for {$validRows->count()} employee(s). Save the step to retain them in the draft.";
    }

    public function downloadDtrMraTemplate(DtrMraInputImportService $service)
    {
        if (! $this->ensureStepCanBeEdited(1) || ! $this->canEditStep1HrFields()) {
            abort(403);
        }

        $path = $service->template($this->employeeFilterOptions());

        return response()->download($path, "dtr-mra-inputs-{$this->period}.xlsx")->deleteFileAfterSend(true);
    }

    public function previewDtrMraImport(DtrMraInputImportService $service): void
    {
        if (! $this->ensureStepCanBeEdited(1) || ! $this->canEditStep1HrFields()) {
            return;
        }

        $this->validate([
            'dtrMraFile' => ['required', 'file', 'mimes:xlsx,xlsm,xls,csv,txt', 'max:30720'],
        ]);

        $this->dtrMraImportPreview = $service->preview(
            $this->dtrMraFile->getRealPath(),
            $this->employeeFilterOptions(),
        );
        $this->dtrMraImportMessage = null;
    }

    public function applyDtrMraImport(): void
    {
        if (! $this->ensureStepCanBeEdited(1) || ! $this->canEditStep1HrFields()) {
            return;
        }

        $validRows = collect($this->dtrMraImportPreview)->where('valid', true);
        if ($validRows->isEmpty()) {
            $this->addError('dtrMraFile', 'The file has no valid DTR/MRA input rows.');

            return;
        }

        foreach ($validRows as $row) {
            $empId = (string) $row['emp_id'];
            if ($row['deduction_days'] !== null) {
                $this->deductionDayOverrides[$empId] = $row['deduction_days'];
            }
            if ($row['logbook_lwop_days'] !== null) {
                $this->logbookLwopDayOverrides[$empId] = $row['logbook_lwop_days'];
            }
        }

        $this->dtrMraFile = null;
        $this->dtrMraImportPreview = [];
        $this->dtrMraImportMessage = "Applied DTR/MRA inputs for {$validRows->count()} employee(s). Save Step 1 to retain them.";
    }

    public function cancelDtrMraImport(): void
    {
        $this->dtrMraFile = null;
        $this->dtrMraImportPreview = [];
        $this->resetValidation('dtrMraFile');
    }

    public function saveDraft(): void
    {
        if (! $this->ensureStepCanBeEdited($this->currentStep)) {
            return;
        }

        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
            $this->addError('draft', 'Choose a division before saving this payroll draft.');

            return;
        }
        if ($this->currentStep === 1 && ! $this->validateStandaloneDtrMraInputs('draft')) {
            return;
        }

        $this->resetValidation('draft');
        $rules = [
            'leavePeriodStart' => ['required', 'date'],
            'leavePeriodEnd' => ['required', 'date', 'after_or_equal:leavePeriodStart'],
        ];

        if ($this->currentStep === 4) {
            $rules += [
                'loanRefunds.*.amount' => ['nullable', 'numeric', 'min:0'],
                'loanRefunds.*.loan_type' => ['nullable', 'string', 'max:255'],
                'loanRefunds.*.remarks' => ['nullable', 'string', 'max:500'],
            ];
        }

        if (in_array($this->currentStep, [3, 5], true)) {
            $rules += [
                'deductionProgramSelections.*.employee_overrides.*' => ['nullable', 'numeric', 'min:0'],
            ];
        }

        $this->validate($rules);

        if (in_array($this->currentStep, [3, 5], true)) {
            $this->persistRecurringProgramSelections();
        }

        $draftValues = [
            'division_id' => $this->divisionId,
            'department_id' => $this->departmentId,
            'payroll_type_code' => PayrollType::CODE_GENERAL,
            'payroll_period' => $this->period,
            'working_days' => $this->workingDays,
            'gsis_days' => $this->gsisDays,
            'included_leave_type_ids' => $this->selectedLeaveTypeIds,
            'employee_type' => Employee::employeeTypeQueryValue($this->employeeTypeFilter),
            'current_step' => $this->currentStep,
            'state_json' => $this->draftStateForCurrentStep(),
            'saved_by' => auth()->user()?->emp_id ?? 'web',
            'saved_at' => now(),
        ];
        $draft = $this->activeDraftId ? PayrollGenerationDraft::query()->find($this->activeDraftId) : null;
        if ($draft && data_get($draft->state_json, 'comparison_source')) {
            $draft->update($draftValues);
        } else {
            $draft = PayrollGenerationDraft::query()->updateOrCreate(
                ['configuration_key' => $this->draftConfigurationKey()],
                $draftValues,
            );
        }

        $this->activeDraftId = $draft->id;
        $this->draftSavedAt = $draft->saved_at?->format('M d, Y g:i A');
        $this->draftNotice = null;

        session()->flash('draft_success', 'Payroll draft saved. Reopening this same configuration will resume these entries.');
    }

    public function saveStepChanges(array $browserDeductionPrograms = []): void
    {
        $this->applyBrowserDeductionProgramState($browserDeductionPrograms);
        $this->saveDraft();

        if (! $this->getErrorBag()->any()) {
            $this->skipRender();
        }
    }

    public function applyLeavePeriod(): void
    {
        if (! $this->canEditStep1HrFields()) {
            $this->addError('authorization', 'You do not have permission to edit the inclusive leave dates.');

            return;
        }

        $this->validate([
            'leavePeriodStart' => ['required', 'date'],
            'leavePeriodEnd' => ['required', 'date', 'after_or_equal:leavePeriodStart'],
        ]);

        // Rebuild date-derived defaults for the newly applied range.
        $this->leaveDateOverrides = [];
        $this->leaveDeductionOverrides = [];
        $this->leavePeriodAppliedMessage = 'Inclusive leave dates applied. Save Step 1 to retain this range.';
    }

    public function saveStepChangesAndGoToStep(int $step, array $browserDeductionPrograms = []): bool
    {
        $this->applyBrowserDeductionProgramState($browserDeductionPrograms);
        $this->saveDraft();

        if ($this->getErrorBag()->any()) {
            return false;
        }

        $this->goToStep($step);

        return true;
    }

    private function applyBrowserDeductionProgramState(array $programs): void
    {
        if (! in_array($this->currentStep, [3, 5], true) || $programs === []) {
            return;
        }

        foreach ($programs as $programId => $selection) {
            if (! is_array($selection)) {
                continue;
            }

            $id = (string) $programId;
            $this->deductionProgramSelections[$id] = array_merge(
                $this->deductionProgramSelections[$id] ?? [],
                [
                    'enabled' => filter_var($selection['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                    'mode' => in_array($selection['mode'] ?? null, ['all', 'include', 'exclude'], true) ? $selection['mode'] : 'all',
                    'amount_mode' => in_array($selection['amount_mode'] ?? null, ['program', 'employee'], true) ? $selection['amount_mode'] : 'program',
                    'employee_ids' => array_values(array_map('strval', (array) ($selection['employee_ids'] ?? []))),
                ],
            );

            if (array_key_exists('employee_overrides', $selection)) {
                $this->deductionProgramSelections[$id]['employee_overrides'] = (array) $selection['employee_overrides'];
            }
        }
    }

    private function persistRecurringProgramSelections(): void
    {
        if (! Schema::connection('payroll')->hasTable('payroll_deduction_program_members')) {
            return;
        }

        $programs = PayrollDeduction::query()->where('is_recurring', true)
            ->where('section', $this->currentStep === 3 ? 'mandatory' : 'other')->get();
        foreach ($programs as $program) {
            $selection = $this->deductionProgramSelections[(string) $program->id] ?? [];
            if (($selection['mode'] ?? 'all') !== 'include') {
                continue;
            }

            PayrollDeductionProgramMember::query()->where('deduction_program_id', $program->id)->delete();
            if (! filter_var($selection['enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }
            foreach (collect($selection['employee_ids'] ?? [])->filter()->unique() as $empId) {
                PayrollDeductionProgramMember::query()->create([
                    'deduction_program_id' => $program->id,
                    'emp_id' => (string) $empId,
                    'amount' => data_get($selection, 'employee_amounts.'.(string) $empId),
                    'is_active' => true,
                ]);
            }
        }
    }

    public function discardStepChangesAndGoToStep(int $step): bool
    {
        $targetStep = max(1, min(count($this->steps), $step));

        $this->resetDraftBackedState();
        $this->restoreDraft();
        $this->goToStep($targetStep);

        return true;
    }

    private function draftStateForCurrentStep(): array
    {
        $state = [
            ...$this->existingDraftState(),
            'wizard_step_count' => count($this->steps),
            'wizard_layout' => PayrollGenerationDraft::WIZARD_LAYOUT,
            'selected_division_ids' => $this->selectedDivisionIds,
            'selected_department_ids' => $this->selectedDepartmentIds,
            'employee_filter_ids' => $this->employeeFilterIds,
            'applied_employee_filter_ids' => $this->appliedEmployeeFilterIds,
            'comparison_employee_scope_ids' => $this->comparisonEmployeeScopeIds,
            'leave_period_start' => $this->leavePeriodStart,
            'leave_period_end' => $this->leavePeriodEnd,
            'comparison_loan_overrides' => $this->comparisonLoanOverrides,
        ];

        return match ($this->currentStep) {
            1 => $this->mergeStepOneDraftState($state),
            2 => [
                ...$state,
                'compensation_adjustments' => $this->compensationAdjustments,
                'selected_adjustment_type_ids' => $this->selectedAdjustmentTypeIds,
            ],
            3 => [
                ...$state,
                'mandatory_deduction_adjustments' => $this->mandatoryDeductionAdjustments,
                'deduction_program_selections' => $this->deductionProgramSelections,
            ],
            4 => [
                ...$state,
                'loan_refunds' => $this->loanRefunds,
            ],
            5 => [
                ...$state,
                'deduction_program_selections' => $this->deductionProgramSelections,
                'other_deduction_remarks' => $this->otherDeductionRemarks,
            ],
            6 => [
                ...$state,
                'tax_annualization_overrides' => $this->taxAnnualizationOverrides,
            ],
            default => $state,
        };
    }

    private function mergeStepOneDraftState(array $state): array
    {
        $existingLeaveDeductionOverrides = (array) ($state['leave_deduction_overrides'] ?? []);
        $canEditHrFields = $this->canEditStep1HrFields();
        $canEditTev = $this->canEditStep1TevField();

        if ($canEditHrFields && $canEditTev) {
            return [
                ...$state,
                'deduction_day_overrides' => $this->deductionDayOverrides,
                'logbook_lwop_day_overrides' => $this->logbookLwopDayOverrides,
                'leave_deduction_overrides' => $this->leaveDeductionOverrides,
                'leave_date_overrides' => $this->leaveDateOverrides,
                'pay_basis_overrides' => $this->payBasisOverrides,
            ];
        }

        if ($canEditHrFields) {
            return [
                ...$state,
                'deduction_day_overrides' => $this->deductionDayOverrides,
                'logbook_lwop_day_overrides' => $this->logbookLwopDayOverrides,
                'leave_deduction_overrides' => $this->mergeLeaveDeductionOverrides(
                    $existingLeaveDeductionOverrides,
                    $this->leaveDeductionOverrides,
                    ['subsistence_days', 'pera_days', 'laundry_days'],
                ),
                'leave_date_overrides' => $this->leaveDateOverrides,
                'pay_basis_overrides' => $this->payBasisOverrides,
            ];
        }

        if ($canEditTev) {
            return [
                ...$state,
                'leave_deduction_overrides' => $this->mergeLeaveDeductionOverrides(
                    $existingLeaveDeductionOverrides,
                    $this->leaveDeductionOverrides,
                    ['tev_days'],
                ),
            ];
        }

        return $state;
    }

    private function mergeLeaveDeductionOverrides(array $existing, array $incoming, array $fields): array
    {
        foreach ($incoming as $empId => $values) {
            $values = (array) $values;
            foreach ($fields as $field) {
                if (array_key_exists($field, $values)) {
                    $existing[$empId][$field] = $values[$field];
                }
            }
        }

        return $existing;
    }

    private function existingDraftState(): array
    {
        $draft = $this->activeDraftId ? PayrollGenerationDraft::query()->find($this->activeDraftId) : null;
        $draft ??= PayrollGenerationDraft::query()->where('configuration_key', $this->draftConfigurationKey())->first();

        return (array) ($draft?->state_json ?? []);
    }

    private function resetDraftBackedState(): void
    {
        $this->deductionDayOverrides = [];
        $this->logbookLwopDayOverrides = [];
        $this->leaveDeductionOverrides = [];
        $this->leaveDateOverrides = [];
        $this->payBasisOverrides = [];
        $this->compensationAdjustments = [];
        $this->mandatoryDeductionAdjustments = [];
        $this->taxAnnualizationOverrides = [];
        $this->loanRefunds = [];
        $this->comparisonLoanOverrides = [];
        $this->selectedAdjustmentTypeIds = [];
        $this->deductionProgramSelections = [];
        $this->otherDeductionRemarks = [];
        $this->activeDraftId = null;
        $this->draftSavedAt = null;
        $this->draftNotice = null;
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
        if (! $this->ensureStepCanBeEdited(2)) {
            return;
        }

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
        if (! $this->ensureStepCanBeEdited(2)) {
            return;
        }

        unset($this->compensationAdjustments[$empId]['extra_items'][(string) $typeId]);
        $this->selectedAdjustmentTypeIds = $this->selectedAdjustmentTypeIdsFromAdjustments($this->compensationAdjustments);
    }

    public function exportRegularPayrollTemplate(RegularPayrollTemplateExportService $exporter)
    {
        if (! $this->ensureStepCanBeEdited(8, 'You can review this payroll but cannot export it.')) {
            return null;
        }

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
        $allAdjustmentTypes = $this->needsAdjustmentTypeOptions()
            ? $this->adjustmentTypes()
            : collect();
        $this->syncSelectedAdjustmentTypeIds($allAdjustmentTypes);
        $adjustmentTypes = $this->selectedAdjustmentTypes($allAdjustmentTypes);
        $this->syncDeductionProgramSelections($deductionPrograms);
        $savedAppliedEmployeeFilterIds = $this->appliedEmployeeFilterIds;
        $this->appliedEmployeeFilterIds = $this->comparisonEmployeeScopeIds;
        try {
            $allRows = $this->payrollRows($compensations, $deductionPrograms);
        } finally {
            $this->appliedEmployeeFilterIds = $savedAppliedEmployeeFilterIds;
        }
        $rows = $this->visiblePayrollRows($allRows);
        $totals = $this->needsPayrollTotals()
            ? $this->payrollTotals($allRows, $compensations)
            : [];
        $previousMraPeriod = $this->previousMraPeriod();
        $previousMraReport = $this->previousMraReport($previousMraPeriod);

        return view('livewire.payroll.payroll-generation', [
            'departments' => $this->departmentOptions(),
            'divisions' => $this->divisionOptions(),
            'employeeFilterOptions' => $this->employeeFilterOptions(),
            'employeeTypeOptions' => Employee::employeeTypeOptions(),
            'employeeTypeLabel' => Employee::employeeTypeLabel($this->employeeTypeFilter),
            'employeeTypeQueryValue' => Employee::employeeTypeQueryValue($this->employeeTypeFilter),
            'compensations' => $compensations,
            'deductionPrograms' => $deductionPrograms,
            'programSetupRows' => $deductionPrograms
                ->where('section', $this->currentStep === 3 ? 'mandatory' : 'other')
                ->filter(fn (PayrollDeduction $program) => $this->programSearch === ''
                    || str($program->name)->lower()->contains(str($this->programSearch)->lower()->squish()->toString()))
                ->values(),
            'allAdjustmentTypes' => $allAdjustmentTypes,
            'adjustmentTypes' => $adjustmentTypes,
            'loanTypes' => $this->currentStep === 4 ? $this->loanTypes(false) : collect(),
            'additionalPremiumTypes' => collect(),
            'rows' => $rows,
            'previousMraPeriod' => $previousMraPeriod,
            'previousMraReport' => $previousMraReport,
            'operatingMode' => app(PayrollOperatingModeService::class)->current(),
            'totals' => $totals,
            'reviewConfiguration' => $this->currentStep === 7
                ? $this->reviewConfiguration($allRows->count())
                : [],
            'payrollGenerationAccess' => $this->payrollGenerationAccess(),
            'unfilteredRowCount' => $allRows->count(),
            'externalOverrides' => $this->activeExternalEmployeeOverrides(),
        ]);
    }

    private function visiblePayrollRows(Collection $rows): Collection
    {
        $search = str($this->tableSearch)->lower()->squish()->toString();
        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search) {
                return str(implode(' ', [
                    $row['emp_id'] ?? '',
                    $row['employee_name'] ?? '',
                    $row['position'] ?? '',
                    $row['employee_classification'] ?? '',
                ]))->lower()->contains($search);
            });
        }

        $descending = $this->tableSortDirection === 'desc';

        return $rows->sortBy(
            fn (array $row) => is_numeric(data_get($row, $this->tableSort))
                ? (float) data_get($row, $this->tableSort)
                : str(data_get($row, $this->tableSort, ''))->lower()->toString(),
            SORT_REGULAR,
            $descending
        )->values();
    }

    private function needsAdjustmentTypeOptions(): bool
    {
        return in_array($this->currentStep, [2, 7], true)
            || $this->selectedAdjustmentTypeIds !== []
            || $this->compensationAdjustments !== [];
    }

    private function needsPayrollTotals(): bool
    {
        return in_array($this->currentStep, [2, 6, 7], true);
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

        $canonical = Schema::connection('payroll')->hasTable('payroll_canonical_employees');
        $employeeClass = $canonical ? Employee::class : app(LegacyPayrollGenerationTestSource::class)->employeeClass();
        $employeeColumns = $canonical ? [
            'emp_id',
            'firstname',
            'middlename',
            'lastname',
            'extension',
            'suffix',
            'position_external_id',
            'department_external_id',
            'step',
            'empstat_id',
            'date_hired',
            'tin_no',
            'gsis_no',
            'phic_no',
            'pagibig_no',
            'is_active',
            'is_external',
            'vacation_leave_credits',
            'sick_leave_credits',
        ] : ['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix', 'position_id', 'department_id', 'step', 'empstat_id', 'date_hired', 'tin_no', 'gsis_no', 'phic_no', 'pagibig_no', 'is_active'];
        $employees = $employeeClass::query()
            ->select($employeeColumns)
            ->with($canonical ? [
                'position',
                'department.division',
            ] : ['position:position_id,position_title,salary_grade,remarks', 'department:department_id,department,division_id', 'department.division:division_id,division'])
            ->when(true, fn ($query) => $this->applyEmployeeScope($query))
            ->where('is_active', $canonical ? true : 'Y')
            ->when(true, fn ($query) => $this->applyPayrollEmployeeType($query))
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
        $leavePeriodStart = $this->leavePeriodStart !== ''
            ? CarbonImmutable::parse($this->leavePeriodStart)
            : $previousMraPeriod['start'];
        $leavePeriodEnd = $this->leavePeriodEnd !== ''
            ? CarbonImmutable::parse($this->leavePeriodEnd)
            : $previousMraPeriod['end'];
        $empIds = $employees->pluck('emp_id')->all();
        $processedLeaveDates = $this->processedLeaveDatesByEmployee($empIds);
        $loanItems = PayrollLoanImportItem::query()
            ->select([
                'id',
                'import_id',
                'entity',
                'due_month',
                'matched_emp_id',
                'loan_account_no',
                'loan_type',
                'monthly_amortization',
                'amount_due',
                'outstanding_balance',
                'principal_due',
                'interest_due',
                'penalty_due',
                'remarks',
            ])
            ->with('import:id,original_filename,imported_at')
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
        $leaveClass = $canonical ? EmployeeLeave::class : app(LegacyPayrollGenerationTestSource::class)->leaveClass();
        $leaveQuery = $leaveClass::query()
            ->with('leaveType')
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            // Keep the indexed columns bare; DATE(column) forces a scan of the
            // legacy leave table (currently hundreds of thousands of rows).
            ->where('start_date', '<=', $leavePeriodEnd->endOfDay()->toDateTimeString())
            ->where('end_date', '>=', $leavePeriodStart->startOfDay()->toDateTimeString())
            ->when(
                $canonical,
                fn ($query) => $query->where('is_cancelled', false),
                fn ($query) => $query->where('status', 0)->whereDoesntHave('logs', fn ($logs) => $logs->whereIn('action', self::EXCLUDED_LEAVE_LOG_ACTIONS))
            );

        if ($this->selectedLeaveTypeIds === []) {
            $leaveQuery->whereRaw('1 = 0');
        } else {
            $leaveQuery->whereIn($canonical ? 'leave_type_external_id' : 'leave_type', $this->selectedLeaveTypeIds);
        }

        $leaves = $leaveQuery->get()->groupBy('emp_id');
        $cancelledLeaves = $leaveClass::query()
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $leavePeriodEnd->endOfDay()->toDateTimeString())
            ->where('end_date', '>=', $leavePeriodStart->startOfDay()->toDateTimeString())
            ->when(
                $canonical,
                fn ($query) => $query->where('is_cancelled', true),
                fn ($query) => $query->whereHas('logs', fn ($logs) => $logs->whereIn('action', self::EXCLUDED_LEAVE_LOG_ACTIONS))
            )
            ->get()
            ->groupBy('emp_id');
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
        $canonicalTimekeeping = Schema::connection('payroll')->hasTable('payroll_canonical_timekeeping')
            ? DB::connection('payroll')->table('payroll_canonical_timekeeping')->where('period', $this->period)->whereIn('emp_id', $empIds)->get()->keyBy('emp_id')
            : collect();
        $labelOptions = PayrollDtrLabelOption::query()->get()->keyBy('code');

        return $employees->map(function (Model $employee) use ($compensations, $deductionPrograms, $adjustmentTypes, $salaryMatrix, $loanReferenceByEntity, $loanReferenceLookup, $leaves, $cancelledLeaves, $processedLeaveDates, $excludedLeaveDates, $labels, $adjustments, $mraAdjustments, $labelOptions, $loanItems, $previousTaxAnnualization, $periodStart, $leavePeriodStart, $leavePeriodEnd, $canonicalTimekeeping) {
            $payBasis = $this->editablePayBasisFor($employee);
            $salaryGrade = $payBasis['salary_grade'];
            $step = $payBasis['step'];
            $isPartTime = $this->isPartTimeEmployee($employee);
            $baseBasicSalary = (float) ($salaryMatrix[$salaryGrade][$step] ?? 0);
            if ($isPartTime) {
                $baseBasicSalary = round($baseBasicSalary / 2, 2);
            }
            $leaveDeduction = $this->leaveDeductionDetails(
                $leaves->get($employee->emp_id, collect()),
                $leavePeriodStart,
                $leavePeriodEnd,
                $processedLeaveDates->get($employee->emp_id, collect()),
            );
            $leaveDeduction = $this->editableLeaveDeductionFor($employee->emp_id, $leaveDeduction);
            $fallbackDeductionDays = $this->deductionDays(
                $labels->get($employee->emp_id, collect()),
                $adjustments->get($employee->emp_id, collect()),
                $labelOptions,
                $excludedLeaveDates->get($employee->emp_id, collect()),
            );
            if ($timekeeping = $canonicalTimekeeping->get($employee->emp_id)) {
                $fallbackDeductionDays = round(
                    (float) $timekeeping->absent_days
                    + (float) $timekeeping->leave_days_without_pay
                    + (((float) $timekeeping->undertime_hours + (float) $timekeeping->tardy_hours) / 8),
                    3
                );
            }
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
                'is_part_time' => $isPartTime,
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
            $computedHazardPay = $this->compensationAmountByName($computed, ['hazard'], 'computed_amount');
            $hazardForTaxDisplay = $computedHazardPay ?: $taxableHazardPay;
            $regularTaxableCompensation = collect($computed)->sum('taxable_amount');
            $supplementalTaxDue = collect($computed)->sum('supplemental_tax_due');
            $leaveWithoutPayMonths = $this->leaveWithoutPayMonths($effectiveBasicDeductDays);
            $netMonths = max(0, PayrollTaxService::ANNUALIZED_MONTHS - $leaveWithoutPayMonths);
            $taxSubsistence = $this->compensationAmountByName($computed, ['subsistence']);
            $programDeductionItems = $this->programDeductionsFor($employee, $deductionPrograms, $basicSalary);
            $mandatoryProgramItems = collect($programDeductionItems)
                ->where('section', 'mandatory')->where('impact_type', 'employee_deduction')->values();
            $otherProgramItems = collect($programDeductionItems)
                ->where('section', 'other')->where('impact_type', 'employee_deduction')->values();
            $mandatoryProgramTotal = round($mandatoryProgramItems->sum('amount'), 2);
            $programDeductionTotal = round($otherProgramItems->sum('amount'), 2);
            $monthlyWithholdingTaxableIncome = round(
                $basicSalary
                + $taxSubsistence
                - (
                    (float) ($statutoryDeductions['life_retirement'] ?? 0)
                    + (float) ($statutoryDeductions['phic'] ?? 0)
                    + (float) ($statutoryDeductions['mandatory_pagibig'] ?? 0)
                    + $mandatoryProgramTotal
                ),
                2
            );
            $currentTaxMandatoryDeductions = $this->taxMandatoryDeductionTotal(
                $statutoryDeductions,
                $mandatoryProgramTotal,
            );
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
            $annualizationLeaveWithoutPayMonths = $leaveWithoutPayMonths;
            $futureMonths = $this->futureMonthsForTax($employee->date_hired, $periodStart);
            $hazardSubsistenceDeductionMonths = round($hazardLeaveDays / max(1, $this->workingDays), 4);
            $grossWithholdingTaxAdjustment = PayrollTaxService::MONTHLY_WITHHOLDING_TAX_ADJUSTMENT;
            $withholdingTaxAdjustment = $this->taxAnnualizationOverrideValue($employee->emp_id, 'withholding_tax_adjustment', 0);
            $totalMandatoryDeductions = round(collect($statutoryDeductions)->sum() + $mandatoryProgramTotal, 2);
            $netBeforeOtherDeductions = round($netCompensation - $totalMandatoryDeductions, 2);
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
                    'future_months_are_net' => false,
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
            $employeeDeductionItems = $loanItems->get($employee->emp_id, collect());
            [$employeePremiumItems, $employeeLoanItems] = $employeeDeductionItems->partition(
                fn (PayrollLoanImportItem $item) => $this->isAdditionalPremiumItem($item)
            );
            // Retained in snapshots for historical compatibility; active premiums are migrated to programs.
            $additionalPremiumTotal = 0.0;
            $loanTotal = round($employeeLoanItems->sum('amount_due'), 2);
            $loanRefund = round(max(0, (float) ($this->loanRefunds[$employee->emp_id]['amount'] ?? 0)), 2);
            // Workbook "Total Other Deductions" is the combined deductions block:
            // loan deductions followed by employee-specific other programs.
            $totalOtherDeductions = round($loanTotal + $programDeductionTotal, 2);
            $loanColumns = $this->blankLoanColumns();
            foreach ($employeeLoanItems as $loanItem) {
                $key = $this->loanColumnKeyFromReference($loanItem, $loanReferenceByEntity);
                $loanColumns[$key] = round(($loanColumns[$key] ?? 0) + (float) $loanItem->amount_due, 2);
            }
            $comparisonLoan = (array) ($this->comparisonLoanOverrides[$employee->emp_id] ?? []);
            if ($comparisonLoan !== []) {
                foreach ((array) ($comparisonLoan['columns'] ?? []) as $key => $amount) {
                    if (array_key_exists($key, $loanColumns)) {
                        $loanColumns[$key] = round(max(0, (float) $amount), 2);
                    }
                }
                $loanTotal = round(array_sum($loanColumns), 2);
                $totalOtherDeductions = round($loanTotal + $programDeductionTotal, 2);
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
            $netAfterLoansBeforeOther = round($netBeforeOtherDeductions - $loanTotal + $loanRefund, 2);
            $netAfterProgramDeductions = round($netAfterLoansBeforeOther - $programDeductionTotal, 2);
            $netAfterAdditionalPremiums = $netAfterProgramDeductions;
            $netAfterTax = round($netAfterProgramDeductions - $withholdingTax, 2);
            // Keep the legacy key as final net pay for payslips, review, and saved snapshots.
            $netAfterLoanDeductions = $netAfterTax;
            $fifteenth = round($netAfterLoanDeductions / 2, 2);
            $thirtieth = round($netAfterLoanDeductions - $fifteenth, 2);

            $isExternal = $this->isExternalEmployee($employee);

            return [
                'emp_id' => $employee->emp_id,
                'first_name' => $employee->firstname,
                'middle_name' => $employee->middlename,
                'last_name' => $employee->lastname,
                'extension' => $employee->extension,
                'employee_name' => $this->formatPayrollEmployeeName($employee),
                'is_part_time' => $isPartTime,
                'is_external' => $isExternal,
                'employee_classification' => $isPartTime ? 'Part-time' : ($isExternal ? 'External' : ''),
                'vacation_leave_credits' => (float) $employee->vacation_leave_credits,
                'sick_leave_credits' => (float) $employee->sick_leave_credits,
                'cancelled_leave_count' => $cancelledLeaves->get($employee->emp_id, collect())->count(),
                'cto_availed_days' => collect($leaveDeduction['items'] ?? [])
                    ->filter(fn (array $item) => str_contains(strtolower((string) $item['leave_type']), 'cto')
                        || str_contains(strtolower((string) $item['leave_type']), 'compensatory'))
                    ->sum(fn (array $item) => CarbonImmutable::parse($item['start_date'])->diffInDays(CarbonImmutable::parse($item['end_date'])) + 1),
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
                'leave_review_items' => $this->leaveReviewItems($leaveDeduction),
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
                'loan_refunds' => [
                    'total' => $loanRefund,
                    'loan_type' => trim((string) ($this->loanRefunds[$employee->emp_id]['loan_type'] ?? '')),
                    'remarks' => trim((string) ($this->loanRefunds[$employee->emp_id]['remarks'] ?? '')),
                ],
                'program_deductions' => [
                    'total' => $programDeductionTotal,
                    'items' => $otherProgramItems->all(),
                ],
                'mandatory_program_deductions' => [
                    'total' => $mandatoryProgramTotal,
                    'items' => $mandatoryProgramItems->all(),
                ],
                'additional_premiums' => [
                    'total' => $additionalPremiumTotal,
                    'items' => $employeePremiumItems->map(fn (PayrollLoanImportItem $item) => $this->deductionImportItemPayload($item, $loanReferenceByEntity, $loanReferenceLookup))->values()->all(),
                ],
                'gross' => $gross,
                'net_before_other_deductions' => $netBeforeOtherDeductions,
                'total_other_deductions' => $totalOtherDeductions,
                'other_deduction_remarks' => trim((string) ($this->otherDeductionRemarks[$employee->emp_id] ?? '')),
                'net_after_tax' => $netAfterTax,
                'net_after_loans_before_other' => $netAfterLoansBeforeOther,
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
        $this->loanRefunds = [];
        $this->comparisonLoanOverrides = [];
        $this->employeeFilterIds = [];
        $this->appliedEmployeeFilterIds = [];
        $this->comparisonEmployeeScopeIds = [];
        $this->selectedAdjustmentTypeIds = [];
        $this->deductionProgramSelections = [];
        $this->otherDeductionRemarks = [];
        $this->finalizedRunId = null;
        $this->finalizedSummary = [];
        $this->activeDraftId = null;
        $this->draftSavedAt = null;
        $this->draftNotice = null;
    }

    private function restoreDraft(): void
    {
        $requestedDraftId = request()->integer('draft_id');
        $draft = $requestedDraftId ? PayrollGenerationDraft::query()->find($requestedDraftId) : null;
        $draft ??= PayrollGenerationDraft::query()->where('configuration_key', $this->draftConfigurationKey())->first();

        if (! $draft) {
            return;
        }

        $state = $draft->state_json ?? [];
        $this->currentStep = PayrollGenerationDraft::restoredWizardStep((int) $draft->current_step, $state);
        $this->selectedDivisionIds = $this->normalizedIds($state['selected_division_ids'] ?? $this->selectedDivisionIds);
        $this->selectedDepartmentIds = $this->normalizedIds($state['selected_department_ids'] ?? $this->selectedDepartmentIds);
        $this->employeeFilterIds = $this->parseEmployeeIdList($state['employee_filter_ids'] ?? $this->employeeFilterIds);
        $this->appliedEmployeeFilterIds = $this->parseEmployeeIdList($state['applied_employee_filter_ids'] ?? $this->employeeFilterIds);
        $this->comparisonEmployeeScopeIds = $this->parseEmployeeIdList($state['comparison_employee_scope_ids'] ?? []);
        $this->leavePeriodStart = (string) ($state['leave_period_start'] ?? $this->leavePeriodStart);
        $this->leavePeriodEnd = (string) ($state['leave_period_end'] ?? $this->leavePeriodEnd);
        $this->syncLegacyScopeIds();
        $this->deductionDayOverrides = (array) ($state['deduction_day_overrides'] ?? []);
        $this->logbookLwopDayOverrides = (array) ($state['logbook_lwop_day_overrides'] ?? []);
        $this->leaveDeductionOverrides = (array) ($state['leave_deduction_overrides'] ?? []);
        $this->leaveDateOverrides = (array) ($state['leave_date_overrides'] ?? []);
        $this->payBasisOverrides = (array) ($state['pay_basis_overrides'] ?? []);
        $this->compensationAdjustments = (array) ($state['compensation_adjustments'] ?? []);
        $this->mandatoryDeductionAdjustments = (array) ($state['mandatory_deduction_adjustments'] ?? []);
        $this->loanRefunds = (array) ($state['loan_refunds'] ?? []);
        $this->comparisonLoanOverrides = (array) ($state['comparison_loan_overrides'] ?? []);
        $this->taxAnnualizationOverrides = collect((array) ($state['tax_annualization_overrides'] ?? []))
            ->map(fn ($values) => app(TaxInputImportService::class)->retainedOverrides((array) $values))
            ->all();
        $this->selectedAdjustmentTypeIds = array_key_exists('selected_adjustment_type_ids', $state)
            ? array_values((array) $state['selected_adjustment_type_ids'])
            : $this->selectedAdjustmentTypeIdsFromAdjustments($this->compensationAdjustments);
        $this->deductionProgramSelections = (array) ($state['deduction_program_selections'] ?? []);
        $this->otherDeductionRemarks = (array) ($state['other_deduction_remarks'] ?? []);
        $this->activeDraftId = $draft->id;
        $this->draftSavedAt = $draft->saved_at?->format('M d, Y g:i A');
        $this->draftNotice = data_get($state, 'comparison_source.remarks')
            ?: 'A saved draft for this configuration was restored.';
    }

    private function draftConfigurationKey(): string
    {
        return PayrollGenerationDraft::configurationKeyForScope(
            $this->selectedDivisionIds,
            $this->selectedDepartmentIds,
            PayrollType::CODE_GENERAL,
            $this->period,
            $this->workingDays,
            Employee::employeeTypeQueryValue($this->employeeTypeFilter),
            $this->gsisDays,
            $this->selectedLeaveTypeIds,
            $this->leavePeriodStart,
            $this->leavePeriodEnd,
        );
    }

    private function deductionPrograms(): Collection
    {
        return PayrollDeduction::query()
            ->select(['id', 'name', 'is_percentage', 'value', 'is_active', 'sort_order', 'insert_after_column', 'section', 'impact_type', 'is_recurring'])
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
        $members = Schema::connection('payroll')->hasTable('payroll_deduction_program_members')
            ? PayrollDeductionProgramMember::query()
                ->whereIn('deduction_program_id', $programs->pluck('id'))
                ->where('is_active', true)
                ->get()
                ->groupBy('deduction_program_id')
            : collect();

        foreach ($programs as $program) {
            $id = (string) $program->id;
            $rosterIds = $members->get($program->id, collect())
                ->pluck('emp_id')
                ->map(fn ($empId) => (string) $empId)
                ->values()
                ->all();
            $employeeAmounts = $members->get($program->id, collect())
                ->filter(fn ($member) => $member->amount !== null)
                ->mapWithKeys(fn ($member) => [(string) $member->emp_id => (float) $member->amount])
                ->all();
            $this->deductionProgramSelections[$id] = array_merge([
                'enabled' => $program->is_recurring
                    ? ($rosterIds !== [] || strcasecmp($program->name, 'EA Deduction') === 0)
                    : false,
                'mode' => $rosterIds === [] ? 'all' : 'include',
                'employee_ids' => $rosterIds,
                'amount_mode' => 'program',
                'employee_amounts' => $employeeAmounts,
                'employee_overrides' => [],
            ], $this->deductionProgramSelections[$id] ?? []);
        }
    }

    private function programDeductionsFor(Model $employee, Collection $programs, float $basicSalary): array
    {
        return $programs
            ->filter(fn (PayrollDeduction $program) => $this->programAppliesToEmployee($program, $employee->emp_id))
            ->map(fn (PayrollDeduction $program) => [
                'id' => $program->id,
                'name' => $program->name,
                'amount' => $this->computeDeductionProgram($program, $employee->emp_id, $basicSalary),
                'section' => $program->section ?? 'other',
                'impact_type' => $program->impact_type ?? 'employee_deduction',
                'insert_after_column' => $program->insert_after_column,
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
        $employeeOverride = $selection['employee_overrides'][$empId] ?? null;
        if ($employeeOverride !== null && $employeeOverride !== '' && is_numeric($employeeOverride)) {
            return round(max(0, (float) $employeeOverride), 2);
        }

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
            ->select(['id', 'entity_id', 'name', 'is_active', 'sort_order'])
            ->with('entity:id,code,name')
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

    private function currentDeductionImportMode(): string
    {
        return 'loans';
    }

    private function currentDeductionLabel(): string
    {
        return 'loan deduction';
    }

    private function currentDeductionTypeLabel(): string
    {
        return 'loan';
    }

    private function loanTypeMatchesCurrentDeductionStep(PayrollLoanType $loanType): bool
    {
        $entityCode = strtoupper((string) $loanType->entity?->code);
        $entityName = strtoupper((string) $loanType->entity?->name);
        $isPremiumType = in_array($entityCode, self::ADDITIONAL_PREMIUM_ENTITY_CODES, true)
            || in_array($entityName, self::ADDITIONAL_PREMIUM_ENTITY_CODES, true);

        return ! $isPremiumType;
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
        return CarbonImmutable::createFromFormat('!Y-m', $this->period)->startOfMonth();
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

    private function validateStandaloneDtrMraInputs(string $errorKey): bool
    {
        if (app(PayrollOperatingModeService::class)->current()->value !== 'standalone') {
            return true;
        }
        if ($this->previousMraReport($this->previousMraPeriod())) {
            return true;
        }

        $employeeIds = $this->employeeFilterOptions()->pluck('emp_id')->map('strval');
        $enteredIds = collect(array_keys($this->deductionDayOverrides))->map('strval');
        $missing = $employeeIds->diff($enteredIds);
        if ($missing->isEmpty()) {
            return true;
        }

        $this->addError(
            $errorKey,
            "Step 1 requires DTR/MRA deduction days for every employee in Standalone mode. {$missing->count()} employee(s) still have no value; enter 0 when there is no deduction, or import the completed template.",
        );

        return false;
    }

    private function logbookLwopDaysFor(string $empId): float
    {
        $override = $this->logbookLwopDayOverrides[$empId] ?? null;

        if ($override === null || $override === '' || ! is_numeric($override)) {
            return 0.0;
        }

        return round(max(0, (float) $override), 3);
    }

    private function editablePayBasisFor(Model $employee): array
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

    private function leaveDeductionDetails(Collection $leaves, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, Collection $processedLeaves): array
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

            $item = $this->editableLeaveDateFor(
                $leave,
                $periodStart,
                $periodEnd,
                $processedLeaves->get((string) $leave->leave_id, collect()),
            );
            $items[] = $item;

            if ($item['excluded']) {
                continue;
            }

            $withoutPayDays += (float) ($item['effective_days_without_pay'] ?? 0);

            $start = CarbonImmutable::parse($item['start_date']);
            $end = CarbonImmutable::parse($item['end_date']);

            if ($start->greaterThan($end)) {
                continue;
            }

            $periods[] = $item['period'];

            if (($item['days_without_pay'] ?? 0) > 0 && ($item['days_with_pay'] ?? 0) <= 0) {
                continue;
            }

            foreach ($item['included_dates'] as $includedDate) {
                $date = CarbonImmutable::parse($includedDate);
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

    private function editableLeaveDateFor(Model $leave, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, Collection $processedDates): array
    {
        $key = (string) $leave->leave_id;
        $authoritativeDates = collect(LeaveDates::for($leave));
        $originalStart = $authoritativeDates->isNotEmpty()
            ? CarbonImmutable::parse($authoritativeDates->first())
            : CarbonImmutable::parse($leave->start_date);
        $originalEnd = $authoritativeDates->isNotEmpty()
            ? CarbonImmutable::parse($authoritativeDates->last())
            : CarbonImmutable::parse($leave->end_date);
        $defaultStart = $originalStart->max($periodStart);
        $defaultEnd = $originalEnd->min($periodEnd);
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

        // Prefer remarks CSV (itemized) clipped by override window; never invent weekend gaps.
        $rangeDates = $authoritativeDates
            ->filter(function (string $date) use ($start, $end) {
                $parsed = CarbonImmutable::parse($date);

                return $parsed->betweenIncluded($start, $end);
            })
            ->values();

        if ($authoritativeDates->isEmpty()) {
            $rangeDates = collect();
            for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                $rangeDates->push($date->toDateString());
            }
        }

        $processedInRange = $rangeDates
            ->filter(fn (string $date) => $processedDates->has($date))
            ->map(fn (string $date) => ['date' => $date, ...$processedDates->get($date)])
            ->values();
        $includedDates = $rangeDates->reject(fn (string $date) => $processedDates->has($date))->values();
        $availableRatio = $rangeDates->isEmpty() ? 0 : $includedDates->count() / $rangeDates->count();
        $daysWithoutPay = $this->numericLeaveDeductionValue($leave->days_wopay ?? 0);
        $daysWithPay = $this->numericLeaveDeductionValue($leave->days_wpay ?? 0);

        return [
            'id' => $leave->leave_id,
            'leave_type' => $leave->leave_type_name ?: $leave->leaveType?->leave_name ?: 'Leave',
            'original_period' => $this->formatLeavePeriod($originalStart, $originalEnd),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'period' => $this->formatLeavePeriod($start, $end),
            'excluded' => filter_var($this->leaveDateOverrides[$key]['excluded'] ?? false, FILTER_VALIDATE_BOOL),
            'days_without_pay' => $daysWithoutPay,
            'days_with_pay' => $daysWithPay,
            'effective_days_without_pay' => round($daysWithoutPay * $availableRatio, 3),
            'effective_days_with_pay' => round($daysWithPay * $availableRatio, 3),
            'included_dates' => $includedDates->all(),
            'processed_dates' => $processedInRange->all(),
            'already_processed' => $processedInRange->isNotEmpty(),
            'fully_processed' => $includedDates->isEmpty() && $processedInRange->isNotEmpty(),
        ];
    }

    private function leaveReviewItems(array $leaveDeduction): array
    {
        $items = collect($leaveDeduction['items'] ?? [])
            ->reject(fn (array $item) => filter_var($item['excluded'] ?? false, FILTER_VALIDATE_BOOL))
            ->map(function (array $item) {
                $dates = collect($item['included_dates'] ?? [])
                    ->filter()
                    ->map(fn (string $date) => CarbonImmutable::parse($date))
                    ->unique(fn (CarbonImmutable $date) => $date->toDateString())
                    ->sort()
                    ->values();

                return [
                    'type' => (string) ($item['leave_type'] ?? 'Leave'),
                    'dates' => $dates,
                ];
            })
            ->filter(fn (array $item) => $item['dates']->isNotEmpty())
            ->values();

        $monthCount = $items
            ->flatMap(fn (array $item) => $item['dates'])
            ->map(fn (CarbonImmutable $date) => $date->format('Y-m'))
            ->unique()
            ->count();

        return $items->map(function (array $item) use ($monthCount) {
            $dates = $monthCount > 1
                ? $item['dates']->groupBy(fn (CarbonImmutable $date) => $date->format('Y-m'))
                    ->map(fn (Collection $monthDates) => $monthDates->first()->format('F').' '.$monthDates->pluck('day')->implode(', '))
                    ->implode('; ')
                : $item['dates']->pluck('day')->implode(', ');

            return ['type' => $item['type'], 'dates' => $dates];
        })->all();
    }

    private function reviewConfiguration(int $employeeCount): array
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', $this->period);
        $leaveStart = CarbonImmutable::parse($this->leavePeriodStart);
        $leaveEnd = CarbonImmutable::parse($this->leavePeriodEnd);
        $leaveCoverage = $leaveStart->isSameMonth($leaveEnd)
            ? $leaveStart->format('F j').'–'.$leaveEnd->format('j, Y')
            : $leaveStart->format('F j, Y').'–'.$leaveEnd->format('F j, Y');
        $leaveTypes = $this->selectedLeaveTypeIds === []
            ? 'None selected'
            : LeaveType::query()->whereIn('external_id', $this->selectedLeaveTypeIds)
                ->orderBy('name')->pluck('name')->filter()->implode(', ');
        $divisions = Division::query()->whereIn('external_id', $this->selectedDivisionIds)
            ->orderBy('name')->pluck('name')->filter()->implode(', ');
        $departments = Department::query()->whereIn('external_id', $this->selectedDepartmentIds)
            ->orderBy('name')->pluck('name')->filter()->implode(', ');

        return [
            ['label' => 'Payroll period', 'value' => $period->format('F Y')],
            ['label' => 'Payroll type', 'value' => str(PayrollType::CODE_GENERAL)->headline()->toString()],
            ['label' => 'Employees', 'value' => number_format($employeeCount)],
            ['label' => 'Employee type', 'value' => Employee::employeeTypeLabel($this->employeeTypeFilter)],
            ['label' => 'Working days', 'value' => (string) $this->workingDays],
            ['label' => 'GSIS days', 'value' => (string) $this->gsisDays],
            ['label' => 'Leave coverage', 'value' => $leaveCoverage],
            ['label' => 'Included leave types', 'value' => $leaveTypes !== '' ? $leaveTypes : 'None selected', 'wide' => true],
            ['label' => 'Divisions', 'value' => $divisions !== '' ? $divisions : 'None selected', 'wide' => true],
            ['label' => 'Departments', 'value' => $departments !== '' ? $departments : 'All departments in the selected divisions', 'wide' => true],
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

        $this->currentStep = 2;
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

    private function processedLeaveDatesByEmployee(array $empIds): Collection
    {
        if ($empIds === []) {
            return collect();
        }

        $entries = [];
        if (Schema::connection('payroll')->hasTable('payroll_processed_leave_dates')) {
            foreach (PayrollProcessedLeaveDate::query()->whereIn('emp_id', $empIds)->get() as $processed) {
                $entries[(string) $processed->emp_id][(string) $processed->leave_id][$processed->leave_date->toDateString()] = [
                    'payroll_run_id' => $processed->payroll_run_id,
                    'payroll_batch_id' => $processed->payroll_batch_id,
                    'payroll_period' => null,
                ];
            }
        }

        if (Schema::connection('payroll')->hasTable('payroll_batch_records')) {
            $records = PayrollBatchRecord::query()
                ->with('batch:id,payroll_period')
                ->whereIn('emp_id', $empIds)
                ->get(['id', 'payroll_batch_id', 'emp_id', 'snapshot_json']);

            foreach ($records as $record) {
                $items = data_get($record->snapshot_json, 'pay_basis.leave_deduction.items', []);
                foreach (is_array($items) ? $items : [] as $item) {
                    if (filter_var($item['excluded'] ?? false, FILTER_VALIDATE_BOOL) || empty($item['id'])) {
                        continue;
                    }

                    $dates = collect($item['included_dates'] ?? []);
                    if ($dates->isEmpty() && ! empty($item['start_date']) && ! empty($item['end_date'])) {
                        $start = CarbonImmutable::parse($item['start_date']);
                        $end = CarbonImmutable::parse($item['end_date']);
                        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
                            $dates->push($date->toDateString());
                        }
                    }

                    foreach ($dates as $date) {
                        $entries[(string) $record->emp_id][(string) $item['id']][(string) $date] ??= [
                            'payroll_run_id' => data_get($record->snapshot_json, 'payroll_run_id'),
                            'payroll_batch_id' => $record->payroll_batch_id,
                            'payroll_period' => $record->batch?->payroll_period,
                        ];
                    }
                }
            }
        }

        return collect($entries)->map(
            fn (array $leaves) => collect($leaves)->map(fn (array $dates) => collect($dates))
        );
    }

    private function excludedLeaveDates(array $empIds, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Collection
    {
        $canonical = Schema::connection('payroll')->hasTable('payroll_canonical_leaves');
        $leaveClass = $canonical ? EmployeeLeave::class : app(LegacyPayrollGenerationTestSource::class)->leaveClass();

        return $leaveClass::query()
            ->whereIn('emp_id', $empIds)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $periodEnd->endOfDay()->toDateTimeString())
            ->where('end_date', '>=', $periodStart->startOfDay()->toDateTimeString())
            ->when(
                $canonical,
                fn ($query) => $query->where('is_cancelled', true),
                fn ($query) => $query->whereHas('logs', fn ($logs) => $logs->whereIn('action', self::EXCLUDED_LEAVE_LOG_ACTIONS))
            )
            ->get()
            ->flatMap(function (Model $leave) use ($periodStart, $periodEnd) {
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
        $canonical = $query->getModel() instanceof Employee;
        if ($this->selectedDepartmentIds !== []) {
            return $query->whereIn($canonical ? 'department_external_id' : 'department_id', $this->selectedDepartmentIds);
        }

        return $query->whereHas(
            'department',
            fn ($departmentQuery) => $departmentQuery->whereIn($canonical ? 'division_external_id' : 'division_id', $this->selectedDivisionIds)
        );
    }

    private function applyPayrollEmployeeType($query)
    {
        $types = Employee::normalizeEmployeeTypes($this->employeeTypeFilter);
        if (in_array(Employee::EMPLOYEE_TYPE_ALL, $types, true)) {
            return $query;
        }

        $includesExternal = in_array(Employee::EMPLOYEE_TYPE_EXTERNAL, $types, true);
        if (! $includesExternal) {
            $overrideIds = $this->activeExternalEmployeeOverrideIds();

            return $query
                ->employeeType($types)
                ->when($overrideIds !== [], fn ($q) => $q->whereNotIn('emp_id', $overrideIds));
        }

        $nonExternalTypes = array_values(array_diff($types, [Employee::EMPLOYEE_TYPE_EXTERNAL]));
        $overrideIds = $this->activeExternalEmployeeOverrideIds();

        return $query->where(function ($typeQuery) use ($nonExternalTypes, $overrideIds) {
            if ($nonExternalTypes !== []) {
                $typeQuery->where(fn ($q) => $q->employeeType($nonExternalTypes));
            } else {
                $typeQuery->whereRaw('1 = 0');
            }

            $typeQuery
                ->orWhere(fn ($q) => $q->employeeType(Employee::EMPLOYEE_TYPE_EXTERNAL))
                ->when($overrideIds !== [], fn ($q) => $q->orWhereIn('emp_id', $overrideIds));
        });
    }

    private function isExternalEmployee(Model $employee): bool
    {
        if ((int) $employee->empstat_id === Employee::EMPSTAT_EXTERNAL || (bool) $employee->is_external) {
            return true;
        }

        if (strtolower(trim((string) $employee->department?->division?->division)) === Employee::EXTERNAL_DIVISION_NAME) {
            return true;
        }

        return in_array((string) $employee->emp_id, $this->activeExternalEmployeeOverrideIds(), true);
    }

    /** @return Collection<int, PayrollExternalEmployeeOverride> */
    private function activeExternalEmployeeOverrides(): Collection
    {
        if ($this->activeExternalEmployeeOverrides !== null) {
            return $this->activeExternalEmployeeOverrides;
        }

        return $this->activeExternalEmployeeOverrides = Schema::connection('payroll')
            ->hasTable('payroll_external_employee_overrides')
                ? PayrollExternalEmployeeOverride::query()
                    ->where('is_active', true)
                    ->orderBy('employee_name')
                    ->get()
                : collect();
    }

    /** @return list<string> */
    private function activeExternalEmployeeOverrideIds(): array
    {
        return $this->activeExternalEmployeeOverrides()
            ->pluck('emp_id')
            ->map(fn ($empId) => (string) $empId)
            ->values()
            ->all();
    }

    private function departmentOptions(): Collection
    {
        return DB::connection('payroll')
            ->table('payroll_canonical_departments')
            ->selectRaw('external_id as department_id, name as department, division_external_id as division_id')
            ->orderBy('department')
            ->get();
    }

    private function divisionOptions(): Collection
    {
        return DB::connection('payroll')
            ->table('payroll_canonical_divisions')
            ->selectRaw('external_id as division_id, name as division')
            ->orderBy('division')
            ->get();
    }

    private function employeeFilterOptions(): Collection
    {
        if ($this->selectedDivisionIds === [] && $this->selectedDepartmentIds === []) {
            return collect();
        }

        $query = DB::connection('payroll')
            ->table('payroll_canonical_employees as employees')
            ->leftJoin('payroll_canonical_positions as positions', 'positions.external_id', '=', 'employees.position_external_id')
            ->leftJoin('payroll_canonical_departments as departments', 'departments.external_id', '=', 'employees.department_external_id')
            ->leftJoin('payroll_canonical_divisions as divisions', 'divisions.external_id', '=', 'departments.division_external_id')
            ->select([
                'employees.emp_id',
                'employees.firstname',
                'employees.middlename',
                'employees.lastname',
                'employees.extension',
                'employees.suffix',
                'positions.title as position_title',
            ])
            ->where('employees.is_active', true);

        if ($this->selectedDepartmentIds !== []) {
            $query->whereIn('employees.department_external_id', $this->selectedDepartmentIds);
        } else {
            $query->whereIn('departments.division_external_id', $this->selectedDivisionIds);
        }

        $this->applyRawEmployeeTypeScope($query);

        if ($this->comparisonEmployeeScopeIds !== []) {
            $query->whereIn('employees.emp_id', $this->comparisonEmployeeScopeIds);
        }

        return $query
            ->orderBy('employees.lastname')
            ->orderBy('employees.firstname')
            ->get()
            ->map(fn ($employee) => [
                'emp_id' => (string) $employee->emp_id,
                'label' => trim($employee->emp_id.' - '.$this->formatRawPayrollEmployeeName($employee).' - '.($employee->position_title ?? 'No position'), ' -'),
            ]);
    }

    private function applyRawEmployeeTypeScope($query): void
    {
        $types = Employee::normalizeEmployeeTypes($this->employeeTypeFilter);

        if (in_array(Employee::EMPLOYEE_TYPE_ALL, $types, true)) {
            return;
        }

        $query->where(function ($typeQuery) use ($types) {
            foreach ($types as $type) {
                $typeQuery->orWhere(function ($employeeQuery) use ($type) {
                    if ($type === Employee::EMPLOYEE_TYPE_EXTERNAL) {
                        $employeeQuery->whereRaw('LOWER(TRIM(divisions.name)) = ?', [Employee::EXTERNAL_DIVISION_NAME]);

                        return;
                    }

                    $employeeQuery
                        ->where('employees.empstat_id', $this->employeeStatusIdForType($type))
                        ->where(function ($divisionQuery) {
                            $divisionQuery
                                ->whereNull('divisions.name')
                                ->orWhereRaw('LOWER(TRIM(divisions.name)) != ?', [Employee::EXTERNAL_DIVISION_NAME]);
                        });
                });
            }
        });
    }

    private function employeeStatusIdForType(string $type): int
    {
        return match ($type) {
            Employee::EMPLOYEE_TYPE_CASUAL => Employee::EMPSTAT_CASUAL,
            Employee::EMPLOYEE_TYPE_PART_TIME => Employee::EMPSTAT_PART_TIME,
            Employee::EMPLOYEE_TYPE_CONTRACTUAL => Employee::EMPSTAT_CONTRACTUAL,
            Employee::EMPLOYEE_TYPE_TEMPORARY => Employee::EMPSTAT_TEMPORARY,
            Employee::EMPLOYEE_TYPE_VISITING_CONSULTANT => Employee::EMPSTAT_VISITING_CONSULTANT,
            Employee::EMPLOYEE_TYPE_COS => Employee::EMPSTAT_CONTRACT_OF_SERVICE,
            Employee::EMPLOYEE_TYPE_PROBATIONARY => Employee::EMPSTAT_PROBATIONARY,
            Employee::EMPLOYEE_TYPE_INTERN => Employee::EMPSTAT_INTERN,
            default => Employee::EMPSTAT_PERMANENT,
        };
    }

    private function formatRawPayrollEmployeeName(object $employee): string
    {
        $lastName = trim((string) ($employee->lastname ?? ''));
        $firstName = trim((string) ($employee->firstname ?? ''));
        $middleName = trim((string) ($employee->middlename ?? ''));
        $middleInitial = $middleName !== ''
            ? mb_strtoupper(mb_substr($middleName, 0, 1)).'.'
            : null;

        $givenName = trim(implode(' ', array_filter([$firstName, $middleInitial])));

        return trim($lastName.', '.$givenName, ' ,');
    }

    private function scopeName(): string
    {
        if (count($this->selectedDepartmentIds) === 1) {
            return Department::query()
                ->where('external_id', $this->selectedDepartmentIds[0])
                ->value('name') ?: 'Selected Department';
        }

        if (count($this->selectedDepartmentIds) > 1) {
            return count($this->selectedDepartmentIds).' Departments';
        }

        if (count($this->selectedDivisionIds) === 1) {
            $division = Division::query()
                ->where('external_id', $this->selectedDivisionIds[0])
                ->value('name');

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
            ->pluck('external_id')
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
            'future_months_are_net' => $context['future_months_are_net'] ?? false,
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

    private function taxMandatoryDeductionTotal(array $statutoryDeductions, float $mandatoryProgramTotal): float
    {
        return round(
            (float) ($statutoryDeductions['life_retirement'] ?? 0)
            + (float) ($statutoryDeductions['phic'] ?? 0)
            + (float) ($statutoryDeductions['mandatory_pagibig'] ?? 0)
            + $mandatoryProgramTotal,
            2
        );
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

        $number = (float) $value;

        return round($key === 'withholding_tax_adjustment' ? $number : max(0, $number), 2);
    }

    private function futureMonthsForTax(mixed $appointmentDate, CarbonImmutable $periodStart): int
    {
        if ($periodStart->month >= 12) {
            return 0;
        }

        $futureStart = $periodStart->addMonthNoOverflow()->startOfMonth();
        $futureEnd = $periodStart->endOfYear();

        if ($appointmentDate) {
            $appointment = CarbonImmutable::parse($appointmentDate)->startOfDay();
            if ($appointment->greaterThan($futureEnd)) {
                return 0;
            }

            if ($appointment->greaterThan($futureStart)) {
                $futureStart = $appointment;
            }
        }

        $calendarMonths = ((int) $futureEnd->format('Y') - (int) $futureStart->format('Y')) * 12
            + ((int) $futureEnd->format('n') - (int) $futureStart->format('n'))
            + 1;

        return min(12 - $periodStart->month, max(0, $calendarMonths));
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
            ->select([
                'id',
                'name',
                'is_percentage',
                'value',
                'computation_type',
                'formula',
                'variable_name',
                'include_in_net_pay',
                'tax_treatment',
                'annual_exempt_limit',
                'supplemental_tax_rate',
                'sort_order',
                'is_active',
            ])
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
            ->select(['id', 'name', 'is_active', 'sort_order'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function selectedAdjustmentTypes(?Collection $adjustmentTypes = null): Collection
    {
        $selectedIds = collect($this->selectedAdjustmentTypeIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        $adjustmentTypes ??= $this->adjustmentTypes();

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

    private function formatPayrollEmployeeName(Model $employee): string
    {
        $lastName = trim((string) $employee->lastname);
        $firstName = trim((string) $employee->firstname);
        $middleInitial = $employee->middlename
            ? mb_strtoupper(mb_substr(trim((string) $employee->middlename), 0, 1)).'.'
            : null;

        $givenName = trim(implode(' ', array_filter([$firstName, $middleInitial])));

        return trim($lastName.', '.$givenName, ' ,');
    }

    private function salaryMatrix(): array
    {
        if (! Schema::connection('payroll')->hasTable('payroll_canonical_salary_rates')) {
            $grades = app(LegacyPayrollGenerationTestSource::class)
                ->salaryGroups($this->selectedPeriodStart()->endOfMonth()->toDateString());

            return $this->salaryMatrixFromGroups($grades);
        }

        $grades = SalaryRate::query()
            ->selectRaw('salary_grade, step as step_increment, salary, effective_from as effectivity_date')
            ->whereDate('effective_from', '<=', $this->selectedPeriodStart()->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->selectedPeriodStart()->toDateString()))
            ->orderByDesc('effective_from')
            ->get()
            ->groupBy(fn ($grade) => $grade->salary_grade.'|'.$grade->step_increment);

        return $this->salaryMatrixFromGroups($grades);
    }

    private function salaryMatrixFromGroups(Collection $grades): array
    {
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

    private function isPartTimeEmployee(Model $employee): bool
    {
        if ((int) $employee->empstat_id === Employee::EMPSTAT_PART_TIME) {
            return true;
        }

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
        $standalone = app(PayrollOperatingModeService::class)->current()->value === 'standalone';
        $readiness = app(PayrollReadinessService::class)->check($this->period, requireTimekeeping: ! $standalone);
        if (! $readiness['ready']) {
            $this->addError('finalize', implode(' ', $readiness['errors']));

            return;
        }
        if (! $this->validateStandaloneDtrMraInputs('finalize')) {
            return;
        }

        if (! $this->ensureStepCanBeEdited(8, 'You can review this payroll but cannot finalize it.')) {
            return;
        }

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

        $configurationKey = $this->draftConfigurationKey();
        if (PayrollBatch::query()->where('configuration_key', $configurationKey)->exists()) {
            $this->addError(
                'finalize',
                'This payroll configuration has already been finalized. Open Payroll History to view the existing snapshot.'
            );

            return;
        }

        $comparisonSource = data_get($this->existingDraftState(), 'comparison_source');
        $run = DB::connection('payroll')->transaction(function () use (
            $rows,
            $compensations,
            $deductionPrograms,
            $totals,
            $comparisonSource,
            $configurationKey
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
                'operating_mode' => app(PayrollOperatingModeService::class)->current()->value,
                'source_batch_ids' => DB::connection('payroll')->table('payroll_source_batches')->where('status', 'active')->pluck('id')->all(),
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
                'configuration_key' => $configurationKey,
                'payroll_period' => $this->period,
                'payroll_type' => $payrollType->name,
                'payroll_type_code' => $payrollType->code,
                'working_days' => $this->workingDays,
                'gsis_days' => $this->gsisDays,
                'included_leave_type_ids' => $this->selectedLeaveTypeIds,
                'employee_type' => Employee::employeeTypeQueryValue($this->employeeTypeFilter),
                'generated_by' => $generatedBy,
                'snapshot_created_at' => now(),
                'remarks' => $comparisonSource
                    ? "Generated comparison payroll finalized from historical workbook import #{$comparisonSource['historical_payroll_import_id']}. Eligible for use as historical payroll. Source: {$comparisonSource['filename']}."
                    : "Payroll run #{$run->id} finalized from Payroll Generation module.",
            ]);

            foreach ($rows as $row) {
                if (Schema::connection('payroll')->hasTable('payroll_processed_leave_dates')) {
                    foreach (($row['leave_deduction']['items'] ?? []) as $leaveItem) {
                        if (filter_var($leaveItem['excluded'] ?? false, FILTER_VALIDATE_BOOL)) {
                            continue;
                        }

                        foreach (($leaveItem['included_dates'] ?? []) as $leaveDate) {
                            PayrollProcessedLeaveDate::create([
                                'payroll_run_id' => $run->id,
                                'payroll_batch_id' => $batch->id,
                                'emp_id' => $row['emp_id'],
                                'leave_id' => $leaveItem['id'],
                                'leave_date' => $leaveDate,
                                'processed_by' => $generatedBy,
                            ]);
                        }
                    }
                }

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

            PayrollAuditLog::create([
                'payroll_generate_id' => $run->id,
                'action' => 'payroll.finalized',
                'performed_by' => $generatedBy,
                'remarks' => $comparisonSource
                    ? "Finalized generated comparison payroll for {$this->period} and {$scopeName}; historical import #{$comparisonSource['historical_payroll_import_id']}."
                    : "Finalized {$this->period} payroll for {$scopeName}.",
                'created_at' => now(),
            ]);

            return $run->fresh();
        });

        $this->finalizedRunId = $run->id;
        PayrollGenerationDraft::query()
            ->where(function ($query) {
                $query->where('configuration_key', $this->draftConfigurationKey())
                    ->when(
                        $this->activeDraftId,
                        fn ($query) => $query->orWhere(
                            $query->getModel()->getQualifiedKeyName(),
                            $this->activeDraftId
                        )
                    );
            })
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

        if ((float) ($row['loan_refunds']['total'] ?? 0) > 0) {
            $lines[] = [
                'emp_id' => $row['emp_id'],
                'line_group' => 'EARNING',
                'code' => 'loan_refund',
                'name' => $row['loan_refunds']['loan_type'] ?: 'Loan Refund',
                'amount' => $row['loan_refunds']['total'],
                'remarks' => $row['loan_refunds']['remarks'] ?: 'Loan refund',
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
                'leave_period_start' => $this->leavePeriodStart,
                'leave_period_end' => $this->leavePeriodEnd,
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
            'mandatory_program_deductions' => $row['mandatory_program_deductions'],
            'additional_premiums' => $row['additional_premiums'],
            'loan_deductions' => $row['loan_deductions'],
            'loan_refunds' => $row['loan_refunds'] ?? ['total' => 0],
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
            ['label' => 'Mandatory Deductions', 'columns' => $this->positionedMandatoryDeductionColumns($deductionPrograms)],
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
            ['label' => 'Deduction Programs', 'columns' => array_merge($deductionPrograms->whereNull('insert_after_column')->map(fn ($program) => 'program_'.$program->id)->all(), ['program_total'])],
            ['label' => 'Additional Premiums', 'columns' => array_merge($this->additionalPremiumTypes()->whereNull('insert_after_column')->map(fn ($type) => 'premium_type_'.$type->id)->all(), ['additional_premium_total'])],
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
            'government_pagibig' => ['label' => 'HDMF (GS)', 'enabled' => true],
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
            'additional_premium_total' => ['label' => 'Additional Premium', 'enabled' => true],
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

        foreach ($this->additionalPremiumTypes() as $type) {
            $columns['premium_type_'.$type->id] = ['label' => $type->review_column_label ?: $type->name, 'enabled' => true];
        }

        foreach ($this->loanColumnGroups as $group) {
            foreach ($group as $key => $label) {
                $columns[$key] = ['label' => $label, 'enabled' => true];
            }
        }

        return $columns;
    }

    private function positionedMandatoryDeductionColumns(Collection $deductionPrograms): array
    {
        $columns = ['life_retirement', 'government_life_retirement', 'ec', 'phic', 'government_phic', 'mandatory_pagibig', 'government_pagibig'];
        $positioned = $deductionPrograms
            ->filter(fn ($program) => filled($program->insert_after_column))
            ->map(fn ($program) => ['key' => 'program_'.$program->id, 'after' => $program->insert_after_column])
            ->concat($this->additionalPremiumTypes()
                ->filter(fn ($type) => filled($type->insert_after_column))
                ->map(fn ($type) => ['key' => 'premium_type_'.$type->id, 'after' => $type->insert_after_column]));

        foreach ($positioned->groupBy('after') as $after => $items) {
            $index = array_search($after, $columns, true);
            if ($index !== false) {
                array_splice($columns, $index + 1, 0, $items->pluck('key')->all());
            }
        }

        $columns[] = 'total_mandatory_deductions';

        return $columns;
    }

    private function additionalPremiumTypes(): Collection
    {
        return PayrollLoanType::query()
            ->where('is_active', true)
            ->whereHas('entity', fn ($query) => $query->whereIn('code', self::ADDITIONAL_PREMIUM_ENTITY_CODES))
            ->orderBy('sort_order')->orderBy('name')->get();
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
