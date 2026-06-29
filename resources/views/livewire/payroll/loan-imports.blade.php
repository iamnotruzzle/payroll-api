@php
    $loanEmployees = $employees->map(fn ($employee) => [
        'emp_id' => $employee->emp_id,
        'name' => $employee->full_name,
    ])->values();
    $loanTypeOptions = $loanTypes->map(fn ($type) => [
        'id' => (string) $type->id,
        'label' => ($type->entity?->name ?? $type->entity?->code).' - '.$type->name,
    ])->values();
    $recentLoanSuggestions = $this->recentLoanSuggestionsForModal($employees, $loanTypes);
    try {
        $selectedPeriodLabel = \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y');
    } catch (\Throwable) {
        $selectedPeriodLabel = \Carbon\CarbonImmutable::today()->format('F Y');
    }
    $isCompactPremiumLayout = $isAdditionalPremiumMode ?? false;
@endphp

<section
    class="space-y-4 pb-12"
    x-on:loan-deduction-batch-saved.window="closeLoanModal()"
    x-data="{
        loanModalOpen: false,
        savingLoan: false,
        loanBatch: [],
        batchError: '',
        loanEmployees: @js($loanEmployees),
        loanTypeOptions: @js($loanTypeOptions),
        recentLoanSuggestions: @js($recentLoanSuggestions),
        labels: @js($labels),
        loanForm: {
            emp_id: '',
            loan_type_id: '',
            loan_account_no: '',
            monthly_amortization: '',
            amount_due: '',
            outstanding_balance: '',
            principal_due: '',
            interest_due: '',
            penalty_due: '',
            remarks: '',
        },
        blankLoanForm(empId = '') {
            return {
                emp_id: String(empId || ''),
                loan_type_id: '',
                loan_account_no: '',
                monthly_amortization: '',
                amount_due: '',
                outstanding_balance: '',
                principal_due: '',
                interest_due: '',
                penalty_due: '',
                remarks: '',
            };
        },
        openLoanModal() {
            this.batchError = '';
            this.loanBatch = [];
            this.loanForm = this.blankLoanForm();
            this.loanModalOpen = true;
            this.syncLoanSelects();
        },
        closeLoanModal() {
            this.loanModalOpen = false;
            this.savingLoan = false;
            this.batchError = '';
            this.loanBatch = [];
        },
        clearLoanReferenceAndAmount() {
            this.loanForm.loan_account_no = '';
            this.loanForm.amount_due = '';
        },
        resetLoanForm(keepEmployee = true) {
            const empId = keepEmployee ? this.loanForm.emp_id : '';
            this.loanForm = this.blankLoanForm(empId);
            this.syncLoanSelects();
        },
        get selectedRecentLoanSuggestion() {
            return this.recentLoanSuggestions[`${this.loanForm.emp_id}|${this.loanForm.loan_type_id}`] || null;
        },
        applyRecentLoanSuggestion() {
            const suggestion = this.selectedRecentLoanSuggestion;
            if (!suggestion) {
                return;
            }

            ['loan_account_no', 'monthly_amortization', 'amount_due', 'outstanding_balance', 'principal_due', 'interest_due', 'penalty_due'].forEach((field) => {
                if (this.loanForm[field] === '' && suggestion[field] !== null && suggestion[field] !== undefined) {
                    this.loanForm[field] = String(suggestion[field]);
                }
            });
        },
        amountChangedFromRecent() {
            const suggestion = this.selectedRecentLoanSuggestion;

            return suggestion
                && this.loanForm.loan_account_no === String(suggestion.loan_account_no || '')
                && this.loanForm.amount_due !== ''
                && Number(this.loanForm.amount_due) !== Number(suggestion.amount_due || 0);
        },
        loanEmployeeName(empId) {
            return this.loanEmployees.find((employee) => employee.emp_id === empId)?.name || empId || '-';
        },
        loanTypeLabel(loanTypeId) {
            return this.loanTypeOptions.find((loanType) => loanType.id === String(loanTypeId))?.label || '-';
        },
        addLoanToBatch() {
            this.batchError = '';
            if (!this.loanForm.emp_id || !this.loanForm.loan_type_id || this.loanForm.amount_due === '') {
                this.batchError = `Choose an employee, choose a ${this.labels.type_name_lc}, and enter the amount due.`;
                return;
            }

            this.loanBatch.push({
                ...this.loanForm,
                client_id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
            });
            this.resetLoanForm(true);
        },
        editBatchLoan(index) {
            const item = this.loanBatch[index];
            if (!item) {
                return;
            }

            this.loanForm = { ...item };
            this.loanBatch.splice(index, 1);
            this.syncLoanSelects();
        },
        removeBatchLoan(index) {
            this.loanBatch.splice(index, 1);
        },
        syncLoanSelects() {
            this.$nextTick(() => {
                if (window.jQuery && this.$refs.loanEmployee) {
                    window.jQuery(this.$refs.loanEmployee).val(this.loanForm.emp_id).trigger('change.select2');
                }
                if (window.jQuery && this.$refs.loanType) {
                    window.jQuery(this.$refs.loanType).val(this.loanForm.loan_type_id).trigger('change.select2');
                }
            });
        },
        saveLoanBatch() {
            this.batchError = '';
            if (this.loanBatch.length === 0) {
                this.batchError = `Add at least one ${this.labels.deduction_lc} deduction to the batch.`;
                return;
            }

            this.savingLoan = true;
            $wire.saveLoanDeductionsBatch(this.loanBatch)
                .then(() => { this.savingLoan = false; })
                .catch(() => { this.savingLoan = false; });
        },
    }"
>
    <div @class([
        'flex flex-wrap items-end gap-3',
        'justify-between' => ! $isCompactPremiumLayout,
        'justify-end' => $isCompactPremiumLayout,
    ])>
        @unless ($isCompactPremiumLayout)
            <div>
                <h2 class="text-xl font-semibold">{{ $labels['page_title'] }}</h2>
                <p class="text-sm text-slate-600">{{ $labels['description'] }}</p>
            </div>
        @endunless
        <div class="flex flex-wrap items-end gap-2">
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Billing Month</span>
                <input wire:model.live="period" type="month" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <button type="button" x-on:click="openLoanModal()" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800">
                {{ $labels['add_button'] }}
            </button>
            <button type="button" wire:click="exportTemplate" wire:loading.attr="disabled" wire:target="exportTemplate" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:cursor-wait disabled:opacity-70">
                {{ $labels['template_button'] }}
            </button>
        </div>
    </div>

    <div wire:loading.flex wire:target="exportTemplate" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900">Preparing Template</h3>
                    <p class="mt-1 text-sm text-slate-600">Building the hidden employee records and validation lists.</p>
                </div>
                <span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full w-2/3 animate-pulse rounded-full bg-blue-600"></div>
            </div>
            <p class="mt-3 text-xs text-slate-500">The download will start automatically when the workbook is ready.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @error('loanDeductionForm')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if (! empty($loanImportPreview))
        <div class="fixed inset-0 z-50 overflow-hidden bg-slate-950/40 p-3 backdrop-blur-sm" style="height: 100vh;">
            <div class="mx-auto flex w-fit flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl" style="max-width: calc(100vw - 1.5rem); height: calc(100vh - 1.5rem);">
                <div class="shrink-0 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-slate-900">Import Preview</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ number_format($loanImportPreview['total_rows'] ?? 0) }} row(s) &middot;
                            {{ number_format($loanImportPreview['valid_rows'] ?? 0) }} valid &middot;
                            {{ number_format($loanImportPreview['invalid_rows'] ?? 0) }} invalid
                            @if (! empty($loanImportPreview['detected_loan_columns']))
                                &middot; {{ count($loanImportPreview['detected_loan_columns']) }} detected loan column(s)
                            @endif
                        </p>
                        @if (! empty($loanImportPreview['loan_type_counts']))
                            <p class="mt-1 max-w-5xl text-xs text-slate-500">
                                {{ count($loanImportPreview['loan_type_counts']) }} {{ $labels['type_name_lc'] }}(s):
                                @foreach (array_slice($loanImportPreview['loan_type_counts'], 0, 8, true) as $loanType => $count)
                                    <span class="font-medium text-slate-700">{{ $loanType }}</span> {{ number_format($count) }}@if (! $loop->last), @endif
                                @endforeach
                                @if (count($loanImportPreview['loan_type_counts']) > 8)
                                    , +{{ count($loanImportPreview['loan_type_counts']) - 8 }} more
                                @endif
                            </p>
                        @endif
                    </div>
                    <button type="button" wire:click="closeLoanImportPreview" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close import preview">
                        &times;
                    </button>
                </div>

                @if (($loanImportPreview['invalid_rows'] ?? 0) > 0)
                    <div class="shrink-0 border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800">
                        Fix invalid rows in the workbook and preview again before saving.
                    </div>
                @endif

                <div wire:loading.flex wire:target="saveLoanImport" class="shrink-0 items-center gap-3 border-b border-blue-100 bg-blue-50 px-5 py-3 text-sm text-blue-800">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
                    <span>Saving import rows...</span>
                </div>

                <div class="min-h-0 flex-1 overflow-x-auto overflow-y-auto overscroll-contain">
                    <table class="min-w-[1280px] border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                            <tr>
                                <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Row</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Status</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Due Month</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Entity</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Employee</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">{{ $labels['type_name'] }}</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Reference/Account No.</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Amount Due</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Validation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($loanImportPreview['items'] ?? []) as $item)
                                <tr class="{{ ($item['validation_status'] ?? '') === 'valid' ? 'bg-white hover:bg-emerald-50/50' : 'bg-amber-50 hover:bg-amber-100/60' }}">
                                    <td class="sticky left-0 border-b border-r border-slate-200 bg-inherit px-3 py-2 font-mono text-xs">{{ $item['row_number'] }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ ($item['validation_status'] ?? '') === 'valid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ ucfirst($item['validation_status'] ?? 'invalid') }}
                                        </span>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['due_month'] ?? '-' }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['entity'] ?? '-' }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        <div class="font-medium text-slate-900">{{ $item['employee_name'] ?? '-' }}</div>
                                        <div class="text-xs text-slate-500">{{ $item['employee_id'] ?: ($item['matched_emp_id'] ?? '') }}</div>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['loan_type'] ?? '-' }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['loan_account_no'] ?? '-' }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">{{ number_format((float) ($item['amount_due'] ?? 0), 2) }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-xs text-slate-600">
                                        {{ ! empty($item['validation_errors']) ? implode(' ', $item['validation_errors']) : 'Ready to save.' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="shrink-0 flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                    <button type="button" wire:click="closeLoanImportPreview" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Close
                    </button>
                    <button type="button" wire:click="saveLoanImport" wire:loading.attr="disabled" wire:target="saveLoanImport" @disabled(($loanImportPreview['invalid_rows'] ?? 0) > 0) class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveLoanImport">Save Import</span>
                        <span wire:loading wire:target="saveLoanImport">Saving...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div x-cloak x-show="loanModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="display: none; height: 100dvh;">
        <div x-on:click.outside="closeLoanModal()" class="flex max-h-[92vh] w-full max-w-7xl flex-col rounded-lg border border-slate-200 bg-white shadow-xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ $labels['modal_title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $labels['modal_subtitle'] }} {{ $selectedPeriodLabel }}.</p>
                </div>
                <button type="button" x-on:click="closeLoanModal()" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close loan deduction modal">
                    &times;
                </button>
            </div>

            <div class="grid min-h-0 gap-5 overflow-y-auto px-5 py-5 xl:grid-cols-[minmax(420px,0.85fr)_minmax(520px,1.15fr)]">
                <div class="grid content-start gap-4 md:grid-cols-2">
                    <div class="md:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        Fill the form, add it to the batch, then save all staged deductions once.
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Employee</label>
                        <select x-ref="loanEmployee" x-model="loanForm.emp_id" x-on:change="$nextTick(() => applyRecentLoanSuggestion())" data-select2-searchable data-placeholder="Search employee" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select employee</option>
                            <template x-for="employee in loanEmployees" :key="employee.emp_id">
                                <option :value="employee.emp_id" x-text="`${employee.name} - ${employee.emp_id}`"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">{{ $labels['type_name'] }}</label>
                        <select x-ref="loanType" x-model="loanForm.loan_type_id" x-on:change="$nextTick(() => applyRecentLoanSuggestion())" data-select2-searchable data-placeholder="Search {{ $labels['type_name_lc'] }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select {{ $labels['type_name_lc'] }}</option>
                            <template x-for="loanType in loanTypeOptions" :key="loanType.id">
                                <option :value="loanType.id" x-text="loanType.label"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="selectedRecentLoanSuggestion" class="md:col-span-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                        <span>Auto-filled from </span><span x-text="selectedRecentLoanSuggestion?.due_month"></span><span> for the same employee and {{ $labels['type_name_lc'] }}.</span>
                        <div x-show="amountChangedFromRecent()" class="mt-1 font-semibold text-amber-800">
                            Same reference, but the amount differs from the previous <span x-text="Number(selectedRecentLoanSuggestion?.amount_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>.
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Reference/Account No. <span class="font-normal normal-case text-slate-400">Optional</span></label>
                        <div class="mt-1 flex gap-2">
                            <input x-model="loanForm.loan_account_no" type="text" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <button type="button" x-on:click="clearLoanReferenceAndAmount()" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Monthly Amortization <span class="font-normal normal-case text-slate-400">Optional</span></label>
                        <input x-model="loanForm.monthly_amortization" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Amount Due</label>
                        <div class="mt-1 flex gap-2">
                            <input x-model="loanForm.amount_due" type="number" min="0" step="0.01" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                            <button type="button" x-on:click="clearLoanReferenceAndAmount()" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Outstanding Balance</label>
                        <input x-model="loanForm.outstanding_balance" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Principal Due <span class="font-normal normal-case text-slate-400">Optional</span></label>
                        <input x-model="loanForm.principal_due" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Interest Due</label>
                        <input x-model="loanForm.interest_due" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Penalty Due</label>
                        <input x-model="loanForm.penalty_due" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold uppercase text-slate-500">Remarks</label>
                        <textarea x-model="loanForm.remarks" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2">
                        <button type="button" x-on:click="resetLoanForm(true)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Clear Form
                        </button>
                        <button type="button" x-on:click="addLoanToBatch()" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Add to Batch
                        </button>
                    </div>
                    <div x-show="batchError" class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="batchError"></div>
                </div>

                <div class="min-h-[360px] overflow-hidden rounded-lg border border-slate-200">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h4 class="font-semibold text-slate-900">{{ $labels['batch_title'] }}</h4>
                        <span class="text-sm text-slate-600"><span x-text="loanBatch.length"></span> staged</span>
                    </div>
                    <div class="max-h-[520px] overflow-auto">
                        <table class="min-w-[820px] divide-y divide-slate-200 text-sm">
                            <thead class="sticky top-0 bg-white text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Employee</th>
                                    <th class="px-3 py-2">{{ $labels['type_name'] }}</th>
                                    <th class="px-3 py-2 text-right">Amount Due</th>
                                    <th class="px-3 py-2 text-right">Principal</th>
                                    <th class="px-3 py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(loan, index) in loanBatch" :key="loan.client_id">
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-slate-900" x-text="loanEmployeeName(loan.emp_id)"></div>
                                            <div class="text-xs text-slate-500" x-text="loan.emp_id"></div>
                                        </td>
                                        <td class="px-3 py-2" x-text="loanTypeLabel(loan.loan_type_id)"></td>
                                        <td class="px-3 py-2 text-right font-semibold" x-text="Number(loan.amount_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        <td class="px-3 py-2 text-right" x-text="loan.principal_due === '' ? '-' : Number(loan.principal_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" x-on:click="editBatchLoan(index)" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold hover:bg-slate-50">Edit</button>
                                            <button type="button" x-on:click="removeBatchLoan(index)" class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Remove</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="loanBatch.length === 0">
                                    <td colspan="5" class="px-3 py-10 text-center text-slate-500">{{ $labels['empty_batch'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                <button type="button" x-on:click="closeLoanModal()" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Cancel
                </button>
                <button type="button" x-on:click="saveLoanBatch()" x-bind:disabled="savingLoan || loanBatch.length === 0" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="!savingLoan">Save Batch</span>
                    <span x-show="savingLoan">Saving...</span>
                </button>
            </div>
        </div>
    </div>

    <div @class([
        'grid gap-4',
        'xl:grid-cols-[360px_1fr]' => ! $isCompactPremiumLayout,
    ])>
        <div @class([
            'space-y-4' => ! $isCompactPremiumLayout,
            'grid gap-4 lg:grid-cols-[minmax(280px,360px)_minmax(280px,1fr)]' => $isCompactPremiumLayout,
        ])>
            <form
                wire:submit="previewLoanImport"
                class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                x-data="{ uploadingLoanFile: false, loanUploadProgress: 0, loanUploadError: '' }"
                x-on:livewire-upload-start="uploadingLoanFile = true; loanUploadProgress = 0; loanUploadError = ''"
                x-on:livewire-upload-finish="uploadingLoanFile = false; loanUploadProgress = 100"
                x-on:livewire-upload-error="uploadingLoanFile = false; loanUploadError = 'Upload failed. The workbook may exceed the server upload limit or the connection was interrupted.'"
                x-on:livewire-upload-cancel="uploadingLoanFile = false; loanUploadError = 'Upload cancelled.'"
                x-on:livewire-upload-progress="loanUploadProgress = $event.detail.progress"
            >
                <h3 class="font-semibold">{{ $labels['import_title'] }}</h3>
                <div class="mt-4">
                    <label class="text-sm font-medium">Excel file</label>
                    <input wire:model="loanFile" type="file" accept=".xlsx,.xls,.xlsm,.csv" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('loanFile')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-cloak x-show="uploadingLoanFile" x-transition class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2">
                    <div class="flex items-center justify-between gap-3 text-xs font-medium text-blue-800">
                        <span x-text="loanUploadProgress >= 100 ? 'Finalizing upload' : 'Uploading workbook'"></span>
                        <span x-text="`${loanUploadProgress}%`"></span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-blue-100">
                        <div class="h-full rounded-full bg-blue-600 transition-all duration-150" x-bind:style="`width: ${loanUploadProgress}%`"></div>
                    </div>
                </div>

                <div x-cloak x-show="loanUploadError" x-transition class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="loanUploadError"></div>

                <div wire:loading.flex wire:target="previewLoanImport,saveLoanImport" class="mt-3 items-center gap-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
                    <span>Reading and validating deduction rows...</span>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="previewLoanImport,loanFile" class="mt-4 w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="previewLoanImport">Preview Rows</span>
                    <span wire:loading wire:target="previewLoanImport">Reading...</span>
                </button>
            </form>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="font-semibold">Recent Imports</h3>
                </div>
                <div @class([
                    'divide-y divide-slate-100',
                    'max-h-[220px] overflow-y-auto' => $isCompactPremiumLayout,
                ])>
                    @forelse ($imports as $import)
                        <button type="button" wire:click="selectImport({{ $import->id }})" class="block w-full px-4 py-3 text-left text-sm hover:bg-slate-50 {{ $selected?->id === $import->id ? 'bg-blue-50' : '' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900">{{ $import->original_filename }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $import->source_entity }} · {{ optional($import->billing_period)->format('M Y') ?? 'No period' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $import->invalid_rows ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $import->invalid_rows ? 'Review' : 'Ready' }}
                                </span>
                            </div>
                            <div class="mt-2 flex gap-3 text-xs {{ $selected?->id === $import->id ? 'text-white' : 'text-slate-500' }}">
                                <span>{{ number_format($import->valid_rows) }} valid</span>
                                <span>{{ number_format($import->invalid_rows) }} invalid</span>
                                <span>{{ $import->imported_at?->format('M d, Y g:i A') }}</span>
                            </div>
                        </button>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-slate-500">{{ $labels['empty_imports'] }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="font-semibold">Validation Grid</h3>
                    <p class="text-sm text-slate-600">
                        @if ($selected)
                            {{ $selected->original_filename }} - Showing {{ number_format($selectedItems?->firstItem() ?? 0) }}-{{ number_format($selectedItems?->lastItem() ?? 0) }} of {{ number_format($selectedItems?->total() ?? $selected->total_rows) }} row(s)
                        @else
                            Select an import to preview rows.
                        @endif
                    </p>
                </div>
                @if ($selected)
                    <div class="grid grid-cols-3 overflow-hidden rounded-md border border-slate-200 text-center text-xs">
                        <div class="px-3 py-2">
                            <p class="font-semibold">{{ number_format($selected->total_rows) }}</p>
                            <p class="text-slate-500">Rows</p>
                        </div>
                        <div class="border-l border-slate-200 px-3 py-2">
                            <p class="font-semibold text-emerald-700">{{ number_format($selected->valid_rows) }}</p>
                            <p class="text-slate-500">Valid</p>
                        </div>
                        <div class="border-l border-slate-200 px-3 py-2">
                            <p class="font-semibold text-amber-700">{{ number_format($selected->invalid_rows) }}</p>
                            <p class="text-slate-500">Invalid</p>
                        </div>
                    </div>
                @endif
            </div>

            @if ($selected)
                <div class="grid gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 md:grid-cols-[minmax(220px,1fr)_180px_180px_auto]">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Search Rows</span>
                        <input wire:model.live.debounce.750ms="itemSearch" type="search" placeholder="Employee, reference, type, remarks" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Status</span>
                        <select wire:model.live="itemStatusFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All statuses</option>
                            <option value="valid">Valid</option>
                            <option value="invalid">Invalid</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Entity</span>
                        <select wire:model.live="itemEntityFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All entities</option>
                            @foreach ($itemEntityOptions as $entityOption)
                                <option value="{{ $entityOption }}">{{ $entityOption }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-end">
                        <button type="button" wire:click="clearItemFilters" class="w-full rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Clear
                        </button>
                    </div>
                </div>
            @endif

            <div @class([
                'overflow-auto',
                'max-h-[680px]' => ! $isCompactPremiumLayout,
                'max-h-[520px]' => $isCompactPremiumLayout,
            ])>
                <table class="min-w-[1460px] border-separate border-spacing-0 text-sm">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                        <tr>
                            <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Row</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Status</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Entity</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Due Month</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Employee ID</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Employee Name</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">{{ $labels['type_name'] }}</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Reference/Account No.</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Amortization</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Amount Due</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Balance</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2">Validation</th>
                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($selectedItems?->items() ?? []) as $item)
                            <tr class="{{ $item->validation_status === 'valid' ? 'bg-white hover:bg-emerald-50/50' : 'bg-amber-50 hover:bg-amber-100/60' }}">
                                <td class="sticky left-0 border-b border-r border-slate-200 bg-inherit px-3 py-2 font-mono text-xs">{{ $item->row_number }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $item->validation_status === 'valid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ ucfirst($item->validation_status) }}
                                    </span>
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item->entity }}</td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                    <input wire:model="itemEdits.{{ $item->id }}.due_month" type="date" class="w-36 rounded-md border border-slate-300 px-2 py-1 text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                    <input wire:model="itemEdits.{{ $item->id }}.employee_id" type="text" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 font-medium">
                                    <input wire:model="itemEdits.{{ $item->id }}.employee_name" type="text" class="w-72 rounded-md border border-slate-300 px-2 py-1 text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                    <input wire:model="itemEdits.{{ $item->id }}.loan_type" type="text" class="w-44 rounded-md border border-slate-300 px-2 py-1 text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                    <input wire:model="itemEdits.{{ $item->id }}.loan_account_no" type="text" class="w-44 rounded-md border border-slate-300 px-2 py-1 text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right">
                                    <input wire:model="itemEdits.{{ $item->id }}.monthly_amortization" type="number" min="0" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">
                                    <input wire:model="itemEdits.{{ $item->id }}.amount_due" type="number" min="0" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm font-semibold">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right">
                                    <input wire:model="itemEdits.{{ $item->id }}.outstanding_balance" type="number" min="0" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm">
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-xs text-slate-600">
                                    {{ $item->validation_errors ? implode(' ', $item->validation_errors) : $labels['ready_text'] }}
                                </td>
                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" wire:click="saveImportItem({{ $item->id }})" wire:loading.attr="disabled" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50">
                                            Save
                                        </button>
                                        <button type="button" wire:click="undoImportItem({{ $item->id }})" @disabled(! ($auditStates[$item->id]['can_undo'] ?? false)) class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            Undo
                                        </button>
                                        <button type="button" wire:click="redoImportItem({{ $item->id }})" @disabled(! ($auditStates[$item->id]['can_redo'] ?? false)) class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-semibold hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            Redo
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-12 text-center text-slate-500">No rows to display.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($selectedItems && $selectedItems->hasPages())
                <div class="border-t border-slate-200 bg-white px-4 py-3">
                    {{ $selectedItems->links() }}
                </div>
            @endif
        </div>
    </div>
</section>


