<?php

namespace App\Livewire\Payroll;

use App\Models\Hris\Employee;
use App\Models\Payroll\PayrollLoanImport;
use App\Models\Payroll\PayrollLoanImportItem;
use App\Models\Payroll\PayrollLoanType;
use App\Services\Payroll\PayrollLoanImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class LoanImports extends Component
{
    use WithFileUploads;

    public $loanFile;

    public ?int $selectedImportId = null;

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

    public function mount(): void
    {
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

    public function render()
    {
        $imports = PayrollLoanImport::query()
            ->withCount('items')
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $selected = $this->selectedImportId
            ? PayrollLoanImport::with('items')->find($this->selectedImportId)
            : $imports->first()?->load('items');

        return view('livewire.payroll.loan-imports', [
            'imports' => $imports,
            'selected' => $selected,
            'employees' => Employee::query()
                ->where('is_active', 'Y')
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'suffix']),
            'loanTypes' => PayrollLoanType::query()
                ->with('entity')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
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
        $path = app(PayrollLoanImportService::class)->buildTemplate();

        return response()->download($path, 'payroll_loan_due_import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function selectImport(int $id): void
    {
        $this->selectedImportId = $id;
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
        $this->selectedImportId = $import->id;
        session()->flash('status', "Saved {$saved} loan deduction(s).");
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
}
