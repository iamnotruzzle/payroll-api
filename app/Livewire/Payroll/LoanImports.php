<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollLoanEntity;
use App\Models\Payroll\PayrollLoanImport;
use App\Models\Payroll\PayrollLoanImportItem;
use App\Models\Payroll\PayrollLoanImportItemAudit;
use App\Models\Payroll\PayrollLoanType;
use App\Services\Payroll\PayrollLoanImportService;
use App\Services\Payroll\PayrollLoanReferenceService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class LoanImports extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $loanFile;

    public string $mode = 'loans';

    public ?int $selectedImportId = null;

    public int $itemsPerPage = 100;

    public string $itemSearch = '';

    public string $itemStatusFilter = '';

    public string $itemEntityFilter = '';

    public array $itemEdits = [];

    public ?string $pendingLoanImportPath = null;

    public ?string $pendingLoanImportOriginalFilename = null;

    public array $loanImportPreview = [];

    public string $period;

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

    public function mount(string $mode = 'loans'): void
    {
        $this->mode = $mode === 'additional_premiums' ? 'additional_premiums' : 'loans';
        $this->period = request()->query('period', CarbonImmutable::today()->format('Y-m'));
    }

    public function updatedPeriod(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->period)) {
            $this->period = CarbonImmutable::today()->format('Y-m');
        }
    }

    public function updatedLoanFile(): void
    {
        $this->pendingLoanImportPath = null;
        $this->pendingLoanImportOriginalFilename = null;
        $this->loanImportPreview = [];
        $this->resetValidation('loanFile');
    }

    public function updatedItemSearch(): void
    {
        $this->resetPage('importItemsPage');
    }

    public function updatedItemStatusFilter(): void
    {
        $this->resetPage('importItemsPage');
    }

    public function updatedItemEntityFilter(): void
    {
        $this->resetPage('importItemsPage');
    }

    public function render()
    {
        $imports = $this->scopedImportsQuery()
            ->withCount('items')
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $selected = $this->selectedImportId
            ? PayrollLoanImport::query()->find($this->selectedImportId)
            : $imports->first();
        $selectedItemsQuery = $selected ? $this->filteredImportItemsQuery($selected) : null;
        $selectedItems = $selectedItemsQuery
            ? $selectedItemsQuery->paginate($this->itemsPerPage, pageName: 'importItemsPage')
            : null;
        $selectedItems?->getCollection()->each(fn (PayrollLoanImportItem $item) => $this->hydrateItemEdit($item));
        $auditStates = $selectedItems
            ? collect($selectedItems->items())->mapWithKeys(fn (PayrollLoanImportItem $item) => [$item->id => $this->auditStateFor($item)])->all()
            : [];
        $itemEntityOptions = $selected
            ? PayrollLoanImportItem::query()
                ->where('import_id', $selected->id)
                ->select('entity')
                ->distinct()
                ->orderBy('entity')
                ->pluck('entity')
                ->filter()
                ->values()
            : collect();

        return view('livewire.payroll.loan-imports', [
            'imports' => $imports,
            'selected' => $selected,
            'selectedItems' => $selectedItems,
            'auditStates' => $auditStates,
            'itemEntityOptions' => $itemEntityOptions,
            'employees' => Employee::query()
                ->where('is_active', 'Y')
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix']),
            'loanTypes' => PayrollLoanType::query()
                ->with('entity')
                ->where('is_active', true)
                ->whereHas('entity', fn ($query) => $this->scopeEntityQuery($query))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'isAdditionalPremiumMode' => $this->isAdditionalPremiumMode(),
            'labels' => $this->labels(),
        ]);
    }

    public function previewLoanImport(): void
    {
        $data = $this->validate([
            'loanFile' => ['required', 'file', 'mimes:xlsx,xls,xlsm,csv', 'max:65536'],
        ]);

        $this->loanImportPreview = [];
        $this->pendingLoanImportPath = null;
        $this->pendingLoanImportOriginalFilename = null;

        $file = $data['loanFile'];
        $storedPath = $file->store('payroll/loan-imports');
        $this->pendingLoanImportPath = $storedPath;
        $this->pendingLoanImportOriginalFilename = $file->getClientOriginalName();
        $this->loanImportPreview = app(PayrollLoanImportService::class)->preview(
            Storage::path($storedPath),
            $file->getClientOriginalName(),
            $this->mode,
        );
    }

    public function saveLoanImport(): void
    {
        if (! $this->pendingLoanImportPath || empty($this->loanImportPreview)) {
            $this->addError('loanFile', 'Preview the loan file before saving the import.');

            return;
        }

        $this->loanImportPreview = app(PayrollLoanImportService::class)->preview(
            Storage::path($this->pendingLoanImportPath),
            $this->pendingLoanImportOriginalFilename ?? 'loan_import.xlsx',
            $this->mode,
        );

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

        $this->selectedImportId = $import->id;
        $this->resetPage('importItemsPage');
        $this->resetItemFilters();
        $this->resetLoanImportState();

        session()->flash(
            'status',
            "Imported {$import->total_rows} row(s): {$import->valid_rows} ready."
        );
    }

    public function import(): void
    {
        $this->previewLoanImport();
        $this->saveLoanImport();
    }

    public function exportTemplate()
    {
        $path = app(PayrollLoanImportService::class)->buildTemplate($this->mode);
        $filename = $this->isAdditionalPremiumMode()
            ? 'payroll_additional_premium_import_template.xlsx'
            : 'payroll_loan_due_import_template.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function selectImport(int $id): void
    {
        $this->selectedImportId = $id;
        $this->resetPage('importItemsPage');
        $this->resetItemFilters();
    }

    public function clearItemFilters(): void
    {
        $this->resetItemFilters();
        $this->resetPage('importItemsPage');
    }

    public function saveImportItem(int $itemId): void
    {
        $item = PayrollLoanImportItem::query()->findOrFail($itemId);
        $before = $this->auditedValues($item);
        $data = $this->validatedItemEdit($itemId);
        $after = $this->normalizeAuditValues([
            ...$before,
            ...$data,
            ...$this->validateImportItemValues($data),
        ]);

        if ($before === $after) {
            return;
        }

        DB::connection('payroll')->transaction(function () use ($item, $before, $after) {
            $item->fill($after);
            $item->save();
            $this->recordImportItemAudit($item, 'update', $before, $after);
            $this->refreshLoanImportCounts($item->import_id);
        });

        $this->itemEdits[$itemId] = $this->editValuesFromArray($after);
        session()->flash('status', 'Import row updated.');
    }

    public function undoImportItem(int $itemId): void
    {
        $item = PayrollLoanImportItem::query()->findOrFail($itemId);
        $audit = $this->latestUndoableAudit($item);

        if (! $audit) {
            return;
        }

        $before = $this->auditedValues($item);
        $after = $this->normalizeAuditValues((array) $audit->old_values);

        DB::connection('payroll')->transaction(function () use ($item, $audit, $before, $after) {
            $item->fill($after);
            $item->save();
            $audit->fill([
                'reverted_at' => now(),
                'reverted_by' => auth()->user()?->emp_id ?? 'web',
            ])->save();
            $this->recordImportItemAudit($item, 'undo', $before, $after);
            $this->refreshLoanImportCounts($item->import_id);
        });

        $this->itemEdits[$itemId] = $this->editValuesFromArray($after);
    }

    public function redoImportItem(int $itemId): void
    {
        $item = PayrollLoanImportItem::query()->findOrFail($itemId);
        $audit = $this->latestRedoableAudit($item);

        if (! $audit) {
            return;
        }

        $before = $this->auditedValues($item);
        $after = $this->normalizeAuditValues((array) $audit->old_values);

        DB::connection('payroll')->transaction(function () use ($item, $audit, $before, $after) {
            $item->fill($after);
            $item->save();
            $audit->fill([
                'reverted_at' => now(),
                'reverted_by' => auth()->user()?->emp_id ?? 'web',
            ])->save();
            $this->recordImportItemAudit($item, 'redo', $before, $after);
            $this->refreshLoanImportCounts($item->import_id);
        });

        $this->itemEdits[$itemId] = $this->editValuesFromArray($after);
    }

    public function closeLoanImportPreview(): void
    {
        $this->resetLoanImportState();
    }

    private function resetLoanImportState(): void
    {
        $this->loanFile = null;
        $this->pendingLoanImportPath = null;
        $this->pendingLoanImportOriginalFilename = null;
        $this->loanImportPreview = [];
        $this->resetValidation('loanFile');
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
                'loan_type_id.required' => 'Choose a '.$this->labels()['type_name_lc'].' for row '.($index + 1).'.',
                'amount_due.required' => 'Enter the amount due for row '.($index + 1).'.',
            ]);

            if ($validator->fails()) {
                $this->addError('loanDeductionForm', $validator->errors()->first());

                return;
            }

            $data = $validator->validated();
            $employee = Employee::query()->where('emp_id', $data['emp_id'])->first();
            $loanType = PayrollLoanType::query()->with('entity')->find((int) $data['loan_type_id']);

            if (! $employee || ! $loanType || ! $this->loanTypeMatchesMode($loanType)) {
                $this->addError('loanDeductionForm', 'Choose a valid employee and '.$this->labels()['type_name_lc'].' for row '.($index + 1).'.');

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
        $this->selectedImportId = $import->id;
        $this->resetPage('importItemsPage');
        $this->resetItemFilters();
        session()->flash('status', "Saved {$saved} ".$this->labels()['deduction_lc']." deduction(s).");
        $this->dispatch('loan-deduction-batch-saved');
    }

    public function recentLoanSuggestionsForModal(Collection $employees, Collection $loanTypes): array
    {
        $empIds = $employees->pluck('emp_id')->filter()->values()->all();

        if (empty($empIds) || $loanTypes->isEmpty()) {
            return [];
        }

        $loanTypesByName = $loanTypes->keyBy(fn (PayrollLoanType $type) => strtolower($type->name));
        $suggestions = [];

        PayrollLoanImportItem::query()
            ->where('validation_status', 'valid')
            ->whereIn('matched_emp_id', $empIds)
            ->whereDate('due_month', '<', $this->selectedPeriodStart()->toDateString())
            ->whereIn(DB::raw('UPPER(entity)'), $this->entityNamesForMode())
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

    private function filteredImportItemsQuery(PayrollLoanImport $selected)
    {
        return PayrollLoanImportItem::query()
            ->where('import_id', $selected->id)
            ->when($this->itemStatusFilter !== '', fn ($query) => $query->where('validation_status', $this->itemStatusFilter))
            ->when($this->itemEntityFilter !== '', fn ($query) => $query->where('entity', $this->itemEntityFilter))
            ->when(trim($this->itemSearch) !== '', function ($query) {
                $search = '%'.strtolower(trim($this->itemSearch)).'%';

                $query->where(function ($query) use ($search) {
                    $query
                        ->whereRaw('LOWER(employee_id) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(matched_emp_id) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(employee_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(loan_account_no) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(loan_type) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(remarks) LIKE ?', [$search]);
                });
            })
            ->orderBy('row_number')
            ->orderBy('id');
    }

    private function resetItemFilters(): void
    {
        $this->itemSearch = '';
        $this->itemStatusFilter = '';
        $this->itemEntityFilter = '';
    }

    private function hydrateItemEdit(PayrollLoanImportItem $item): void
    {
        $this->itemEdits[$item->id] ??= $this->editValuesFromArray($this->auditedValues($item));
    }

    private function editableImportItemFields(): array
    {
        return [
            'due_month',
            'employee_id',
            'employee_name',
            'loan_account_no',
            'loan_type',
            'monthly_amortization',
            'amount_due',
            'outstanding_balance',
            'remarks',
            'validation_status',
            'validation_errors',
        ];
    }

    private function auditedValues(PayrollLoanImportItem $item): array
    {
        return $this->normalizeAuditValues($item->only($this->editableImportItemFields()));
    }

    private function normalizeAuditValues(array $values): array
    {
        foreach (['monthly_amortization', 'amount_due', 'outstanding_balance'] as $field) {
            $values[$field] = ($values[$field] ?? null) === null || $values[$field] === ''
                ? null
                : $this->moneyValue($values[$field]);
        }

        if (($values['due_month'] ?? null) instanceof CarbonInterface) {
            $values['due_month'] = $values['due_month']->toDateString();
        }

        foreach (['due_month', 'employee_id', 'employee_name', 'loan_account_no', 'loan_type', 'remarks', 'validation_status'] as $field) {
            $values[$field] = trim((string) ($values[$field] ?? ''));
        }

        $values['validation_errors'] = array_values((array) ($values['validation_errors'] ?? [])) ?: null;

        return $values;
    }

    private function editValuesFromArray(array $values): array
    {
        return [
            'due_month' => $values['due_month'] ?? '',
            'employee_id' => $values['employee_id'] ?? '',
            'employee_name' => $values['employee_name'] ?? '',
            'loan_account_no' => $values['loan_account_no'] ?? '',
            'loan_type' => $values['loan_type'] ?? '',
            'monthly_amortization' => $values['monthly_amortization'] ?? '',
            'amount_due' => $values['amount_due'] ?? '',
            'outstanding_balance' => $values['outstanding_balance'] ?? '',
            'remarks' => $values['remarks'] ?? '',
        ];
    }

    private function validatedItemEdit(int $itemId): array
    {
        $data = validator($this->itemEdits[$itemId] ?? [], [
            'due_month' => ['required', 'date'],
            'employee_id' => ['nullable', 'string', 'max:80'],
            'employee_name' => ['required', 'string', 'max:255'],
            'loan_account_no' => ['nullable', 'string', 'max:120'],
            'loan_type' => ['nullable', 'string', 'max:120'],
            'monthly_amortization' => ['nullable', 'numeric', 'min:0'],
            'amount_due' => ['required', 'numeric', 'min:0'],
            'outstanding_balance' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        foreach (['monthly_amortization', 'outstanding_balance'] as $field) {
            $data[$field] = ($data[$field] ?? null) === null || $data[$field] === ''
                ? null
                : $this->moneyValue($data[$field]);
        }
        $data['amount_due'] = $this->moneyValue($data['amount_due']);
        $data['loan_account_no'] = trim((string) ($data['loan_account_no'] ?? '')) ?: '(blank)';
        $data['loan_type'] = trim((string) ($data['loan_type'] ?? '')) ?: null;
        $data['employee_id'] = trim((string) ($data['employee_id'] ?? '')) ?: null;
        $data['remarks'] = trim((string) ($data['remarks'] ?? '')) ?: null;

        return $data;
    }

    private function validateImportItemValues(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['employee_name'] ?? '')) === '') {
            $errors[] = 'Employee name is required.';
        }
        if (trim((string) ($data['loan_account_no'] ?? '')) === '') {
            $errors[] = 'Reference/account number is required.';
        }
        if ((float) ($data['amount_due'] ?? 0) < 0) {
            $errors[] = 'Amount due must be zero or greater.';
        }

        return [
            'validation_status' => $errors === [] ? 'valid' : 'invalid',
            'validation_errors' => $errors ?: null,
        ];
    }

    private function recordImportItemAudit(PayrollLoanImportItem $item, string $action, array $oldValues, array $newValues): void
    {
        PayrollLoanImportItemAudit::query()->create([
            'import_id' => $item->import_id,
            'import_item_id' => $item->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'performed_by' => auth()->user()?->emp_id ?? 'web',
            'created_at' => now(),
        ]);
    }

    private function latestAudit(PayrollLoanImportItem $item): ?PayrollLoanImportItemAudit
    {
        return PayrollLoanImportItemAudit::query()
            ->where('import_item_id', $item->id)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function latestUndoableAudit(PayrollLoanImportItem $item): ?PayrollLoanImportItemAudit
    {
        $audit = $this->latestAudit($item);

        return $audit && in_array($audit->action, ['update', 'redo'], true) && ! $audit->reverted_at ? $audit : null;
    }

    private function latestRedoableAudit(PayrollLoanImportItem $item): ?PayrollLoanImportItemAudit
    {
        $audit = $this->latestAudit($item);

        return $audit && $audit->action === 'undo' && ! $audit->reverted_at ? $audit : null;
    }

    private function auditStateFor(PayrollLoanImportItem $item): array
    {
        return [
            'can_undo' => (bool) $this->latestUndoableAudit($item),
            'can_redo' => (bool) $this->latestRedoableAudit($item),
        ];
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

    private function selectedPeriodStart(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m', $this->period)->startOfMonth();
        } catch (\Throwable) {
            $this->period = CarbonImmutable::today()->format('Y-m');

            return CarbonImmutable::today()->startOfMonth();
        }
    }

    private function manualLoanImportFor(CarbonImmutable $periodStart): PayrollLoanImport
    {
        return PayrollLoanImport::query()->firstOrCreate(
            [
                'source_entity' => $this->isAdditionalPremiumMode() ? 'Manual Additional Premium' : 'Manual Entry',
                'billing_period' => $periodStart->toDateString(),
                'original_filename' => $this->isAdditionalPremiumMode() ? 'manual-additional-premiums' : 'manual-loan-deductions',
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

        PayrollLoanImport::query()->whereKey($importId)->update([
            'total_rows' => $items->count(),
            'valid_rows' => $items->where('validation_status', 'valid')->count(),
            'invalid_rows' => $items->where('validation_status', '!=', 'valid')->count(),
        ]);
    }

    private function moneyValue(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }

    private function nullableMoneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->moneyValue($value);
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

    private function scopedImportsQuery()
    {
        return PayrollLoanImport::query()
            ->when($this->isAdditionalPremiumMode(), function ($query) {
                $query->where(function ($query) {
                    $query->whereIn(DB::raw('UPPER(source_entity)'), $this->entityNamesForMode())
                        ->orWhere('original_filename', 'manual-additional-premiums')
                        ->orWhereHas('items', fn ($items) => $items->whereIn(DB::raw('UPPER(entity)'), $this->entityNamesForMode()));
                });
            }, function ($query) {
                $query->where('original_filename', '!=', 'manual-additional-premiums')
                    ->whereNotIn(DB::raw('UPPER(source_entity)'), $this->additionalPremiumEntityNames())
                    ->whereDoesntHave('items', fn ($items) => $items->whereIn(DB::raw('UPPER(entity)'), $this->additionalPremiumEntityNames()));
            });
    }

    private function scopeEntityQuery($query): void
    {
        $entityNames = $this->additionalPremiumEntityNames();

        if ($this->isAdditionalPremiumMode()) {
            $query->where(function ($query) use ($entityNames) {
                foreach ($entityNames as $entityName) {
                    $query->orWhereRaw('UPPER(code) = ?', [$entityName])
                        ->orWhereRaw('UPPER(name) = ?', [$entityName]);
                }
            });

            return;
        }

        foreach ($entityNames as $entityName) {
            $query->whereRaw('UPPER(code) != ?', [$entityName])
                ->whereRaw('UPPER(name) != ?', [$entityName]);
        }
    }

    private function loanTypeMatchesMode(PayrollLoanType $loanType): bool
    {
        $entityNames = $this->additionalPremiumEntityNames();
        $entityCode = strtoupper((string) $loanType->entity?->code);
        $entityName = strtoupper((string) $loanType->entity?->name);
        $isPremiumType = in_array($entityCode, $entityNames, true) || in_array($entityName, $entityNames, true);

        return $this->isAdditionalPremiumMode() ? $isPremiumType : ! $isPremiumType;
    }

    private function entityNamesForMode(): array
    {
        if ($this->isAdditionalPremiumMode()) {
            return $this->additionalPremiumEntityNames();
        }

        $premiumNames = $this->additionalPremiumEntityNames();

        return PayrollLoanEntity::query()
            ->where('is_active', true)
            ->get(['code', 'name'])
            ->flatMap(fn (PayrollLoanEntity $entity) => [$entity->code, $entity->name])
            ->map(fn (?string $name) => strtoupper((string) $name))
            ->filter(fn (string $name) => $name !== '' && ! in_array($name, $premiumNames, true))
            ->unique()
            ->values()
            ->all();
    }

    private function additionalPremiumEntityNames(): array
    {
        return collect([
            ...PayrollLoanReferenceService::ADDITIONAL_PREMIUM_ENTITY_CODES,
            ...app(PayrollLoanReferenceService::class)->additionalPremiumEntityCodes(),
        ])
            ->map(fn (string $code) => strtoupper($code))
            ->unique()
            ->values()
            ->all();
    }

    private function isAdditionalPremiumMode(): bool
    {
        return $this->mode === 'additional_premiums';
    }

    private function labels(): array
    {
        return $this->isAdditionalPremiumMode()
            ? [
                'page_title' => 'Additional Premium Imports',
                'description' => 'Upload or encode additional premium deductions separately from bank loans.',
                'add_button' => 'Add Employee Premium',
                'template_button' => 'Export Premium Template',
                'modal_title' => 'Batch Add Employee Premiums',
                'modal_subtitle' => 'Included in Additional Premiums for',
                'import_title' => 'Import Additional Premium File',
                'empty_imports' => 'No additional premium imports yet.',
                'type_name' => 'Premium Type',
                'type_name_lc' => 'premium type',
                'deduction_lc' => 'additional premium',
                'batch_title' => 'Batch Premiums',
                'empty_batch' => 'No additional premiums staged yet.',
                'ready_text' => 'Ready for payroll Step 6.',
            ]
            : [
                'page_title' => 'Loan Due Imports',
                'description' => 'Upload the uniform loan or deduction template and review validation before payroll generation.',
                'add_button' => 'Add Loan Deduction',
                'template_button' => 'Export Template',
                'modal_title' => 'Batch Add Employee Loans',
                'modal_subtitle' => 'Included in Loan Due Imports for',
                'import_title' => 'Import Loan Due File',
                'empty_imports' => 'No loan imports yet.',
                'type_name' => 'Loan Type',
                'type_name_lc' => 'loan type',
                'deduction_lc' => 'loan',
                'batch_title' => 'Batch Loans',
                'empty_batch' => 'No loan deductions staged yet.',
                'ready_text' => 'Ready for payroll Step 7.',
            ];
    }
}
