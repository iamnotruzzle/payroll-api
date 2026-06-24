<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Payroll History</h2>
        <p class="text-sm text-slate-600">Payroll snapshot records.</p>
    </div>

    <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
        <div class="md:col-span-2">
            <label class="text-sm font-medium">Search</label>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Period, type, or user" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium">Payroll Period</label>
            <input type="month" wire:model.live="period" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium">Payroll Type</label>
            <select wire:model.live="payrollTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All types</option>
                @foreach ($payrollTypeOptions as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium">Employee Type</label>
            <select wire:model.live="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All employees</option>
                @foreach ($employeeTypeOptions as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end md:col-start-5">
            <button type="button" wire:click="clearFilters" class="h-9 whitespace-nowrap rounded-md border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Clear Filters
            </button>
        </div>
    </div>

    <div class="flex border-b border-slate-200">
        <button
            type="button"
            wire:click="showTab('finalized')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $activeTab === 'finalized' ? 'border-[#696cff] text-[#5f61e6]' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
        >
            Finalized Snapshots
        </button>
        <button
            type="button"
            wire:click="showTab('drafts')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $activeTab === 'drafts' ? 'border-[#696cff] text-[#5f61e6]' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
        >
            Saved Drafts
        </button>
    </div>

    @if ($activeTab === 'finalized')
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Payroll Period</th>
                        <th class="px-3 py-2">Payroll Type</th>
                        <th class="px-3 py-2">Generation Configuration</th>
                        <th class="px-3 py-2">Generated</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($batches as $batch)
                        @php
                            $configuration = $batchConfigurations[$batch->id] ?? null;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $batch->payroll_period }}</td>
                            <td class="px-3 py-2">{{ $batch->payroll_type }}</td>
                            <td class="px-3 py-2">
                                @if ($configuration)
                                    <div class="space-y-1 text-xs text-slate-600">
                                        <div>
                                            <span class="font-medium text-slate-700">Working:</span>
                                            {{ $configuration['working_days'] === null ? 'Not recorded' : $configuration['working_days'].' days' }}
                                            <span class="mx-1 text-slate-300">|</span>
                                            <span class="font-medium text-slate-700">GSIS:</span>
                                            {{ $configuration['gsis_days'] === null ? 'Not recorded' : $configuration['gsis_days'].' days' }}
                                        </div>
                                        <div>
                                            <span class="font-medium text-slate-700">Employee:</span>
                                            {{ $configuration['employee_type'] }}
                                        </div>
                                        <div class="max-w-[760px] truncate" title="{{ implode(', ', $configuration['leave_types']) }}">
                                            <span class="font-medium text-slate-700">Leaves:</span>
                                            {{ implode(', ', $configuration['leave_types']) }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Not recorded</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $batch->snapshot_created_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-3 py-2 text-right align-top">
                                <button wire:click="selectBatch({{ $batch->id }})" class="h-8 whitespace-nowrap rounded-md border border-slate-300 px-3 text-xs font-medium hover:bg-slate-50">
                                    View Snapshot
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-slate-500">No payroll snapshots found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $batches->links() }}
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Payroll Period</th>
                        <th class="px-3 py-2">Payroll Type</th>
                        <th class="px-3 py-2">Scope</th>
                        <th class="px-3 py-2">Saved Configuration</th>
                        <th class="px-3 py-2">Saved</th>
                        <th class="w-48 px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($drafts as $draft)
                        @php
                            $configuration = $draftConfigurations[$draft->id] ?? null;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $draft->payroll_period }}</td>
                            <td class="px-3 py-2">{{ strtoupper($draft->payroll_type_code) }}</td>
                            <td class="max-w-[260px] px-3 py-2">
                                <div class="truncate" title="{{ $configuration['scope'] ?? 'Not recorded' }}">
                                    {{ $configuration['scope'] ?? 'Not recorded' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                @if ($configuration)
                                    <div class="space-y-1 text-xs text-slate-600">
                                        <div>
                                            <span class="font-medium text-slate-700">Step:</span>
                                            {{ $configuration['current_step'] }}
                                            <span class="mx-1 text-slate-300">|</span>
                                            <span class="font-medium text-slate-700">Working:</span>
                                            {{ $configuration['working_days'] }} days
                                            <span class="mx-1 text-slate-300">|</span>
                                            <span class="font-medium text-slate-700">GSIS:</span>
                                            {{ $configuration['gsis_days'] }} days
                                        </div>
                                        <div>
                                            <span class="font-medium text-slate-700">Employee:</span>
                                            {{ $configuration['employee_type'] }}
                                        </div>
                                        <div class="max-w-[760px] truncate" title="{{ implode(', ', $configuration['leave_types']) }}">
                                            <span class="font-medium text-slate-700">Leaves:</span>
                                            {{ implode(', ', $configuration['leave_types']) }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">Not recorded</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ $draft->saved_at?->format('M d, Y h:i A') }}</div>
                                <div class="text-xs text-slate-500">{{ $draft->saved_by ?: 'Unknown user' }}</div>
                            </td>
                            <td class="px-3 py-2 text-right align-top">
                                <div class="flex items-start justify-end gap-2 whitespace-nowrap">
                                    <button wire:click="continueDraft({{ $draft->id }})" class="h-8 whitespace-nowrap rounded-md bg-blue-600 px-3 text-xs font-semibold text-white hover:bg-blue-700">
                                        Continue Draft
                                    </button>
                                    <button
                                        wire:click="deleteDraft({{ $draft->id }})"
                                        wire:confirm="Delete this saved payroll draft?"
                                        class="h-8 whitespace-nowrap rounded-md border border-red-200 px-3 text-xs font-semibold text-red-700 hover:bg-red-50"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">No saved payroll drafts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $drafts->links() }}
        </div>
    @endif

    @if ($activeTab === 'finalized' && $records->isNotEmpty())
        @php
            $firstSnapshot = $records->first()->snapshot_json ?? [];
            $columnGroups = $firstSnapshot['column_groups'] ?? [];
            $columns = $firstSnapshot['columns'] ?? [];
            $reviewTableWidth = 2200;

            foreach ($columnGroups as $group) {
                $reviewTableWidth += count($group['columns'] ?? []) * 120;
            }
        @endphp

        @if ($selectedBatch && $selectedBatchConfiguration)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Selected Payroll Snapshot</h3>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $selectedBatch->payroll_period }} | {{ $selectedBatch->payroll_type }}
                            @if ($selectedBatchConfiguration['payroll_type_code'])
                                ({{ $selectedBatchConfiguration['payroll_type_code'] }})
                            @endif
                        </p>
                    </div>
                    <div class="text-sm text-slate-600">
                        Generated {{ $selectedBatch->snapshot_created_at?->format('M d, Y h:i A') }}
                    </div>
                </div>

                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Working Days</dt>
                        <dd class="mt-1 text-slate-900">{{ $selectedBatchConfiguration['working_days'] ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">GSIS Days</dt>
                        <dd class="mt-1 text-slate-900">{{ $selectedBatchConfiguration['gsis_days'] ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Employee Type</dt>
                        <dd class="mt-1 text-slate-900">{{ $selectedBatchConfiguration['employee_type'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-slate-500">Included Leave Types</dt>
                        <dd class="mt-1 text-slate-900">{{ implode(', ', $selectedBatchConfiguration['leave_types']) }}</dd>
                    </div>
                </dl>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="divide-y divide-slate-200 text-sm" style="min-width: {{ $reviewTableWidth }}px;">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    {{-- GROUP HEADERS --}}
                    <tr>
                        @foreach ($columnGroups as $group)
                            @php
                                $isGovernmentGroup = ($group['label'] ?? '') === 'Government Shares';
                            @endphp
                            <th colspan="{{ count($group['columns']) }}" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center {{ $isGovernmentGroup ? 'border-l-4 border-l-indigo-500 bg-indigo-50 text-indigo-700' : '' }}">
                                {{ $group['label'] }}
                            </th>
                        @endforeach
                    </tr>
                    {{-- COLUMN HEADERS --}}
                    <tr>
                        @foreach ($columnGroups as $group)
                            @php
                                $isGovernmentGroup = ($group['label'] ?? '') === 'Government Shares';
                            @endphp
                            @foreach ($group['columns'] as $columnKey)
                                <th class="px-4 py-3 text-right whitespace-nowrap {{ $isGovernmentGroup ? 'bg-indigo-50 text-indigo-700' : '' }} {{ $isGovernmentGroup && $loop->first ? 'border-l-4 border-l-indigo-500' : '' }}">
                                    {{ $columns[$columnKey]['label'] ?? $columnKey }}
                                </th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($records as $record)
                        @php
                            $snapshot = $record->snapshot_json ?? [];
                            $employee = $snapshot['employee'] ?? [];
                            $payBasis = $snapshot['pay_basis'] ?? [];
                            $earnings = $snapshot['earnings'] ?? [];
                            $statutory = $snapshot['statutory_deductions'] ?? [];
                            $governmentShares = $snapshot['statutory_government_shares'] ?? [];
                            $tax = $snapshot['tax'] ?? [];
                            $programs = $snapshot['program_deductions'] ?? [];
                            $loans = $snapshot['loan_deductions'] ?? [];
                            $totals = $snapshot['totals'] ?? [];
                        @endphp
                        <tr class="hover:bg-slate-50">
                            @foreach ($columnGroups as $group)
                                @php
                                    $isGovernmentGroup = ($group['label'] ?? '') === 'Government Shares';
                                @endphp
                                @foreach ($group['columns'] as $columnKey)
                                    <td class="px-4 py-3 text-right whitespace-nowrap {{ $isGovernmentGroup ? 'bg-indigo-50 text-indigo-900' : '' }} {{ $isGovernmentGroup && $loop->first ? 'border-l-4 border-l-indigo-500' : '' }}">
                                        {{-- EMPLOYEE INFORMATION --}}
                                        @if ($columnKey === 'emp_id')
                                            {{ $employee['emp_id'] ?? '-' }}
                                        @elseif ($columnKey === 'employee_name')
                                            <div class="text-left font-medium">
                                                {{ $employee['employee_name'] ?? '-' }}
                                            </div>
                                        @elseif ($columnKey === 'position')
                                            <div class="text-left">
                                                {{ $employee['position'] ?? '-' }}
                                            </div>
                                        {{-- PAY BASIS --}}
                                        @elseif ($columnKey === 'salary_grade')
                                            {{ $payBasis['salary_grade'] ?? '-' }}
                                        @elseif ($columnKey === 'step')
                                            {{ $payBasis['step'] ?? '-' }}
                                        @elseif ($columnKey === 'subsistence_deduct_days')
                                            {{ number_format($payBasis['leave_deduction']['subsistence_days'] ?? 0) }}
                                        @elseif ($columnKey === 'pera_deduct_days')
                                            {{ ($payBasis['leave_deduction']['pera_days'] ?? 0) > 0 ? number_format($payBasis['leave_deduction']['pera_days']) : '-' }}
                                        @elseif ($columnKey === 'laundry_deduct_days')
                                            {{ number_format($payBasis['leave_deduction']['laundry_days'] ?? 0) }}
                                        @elseif ($columnKey === 'tev_deduct_days')
                                            {{ number_format($payBasis['leave_deduction']['tev_days'] ?? 0) }}
                                        @elseif ($columnKey === 'deduction_days')
                                            {{ number_format($payBasis['deduction_days'] ?? 0, 3) }}
                                        {{-- EARNINGS --}}
                                        @elseif ($columnKey === 'basic_salary')
                                            {{ number_format($earnings['basic_salary'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'gross')
                                            <span class="font-semibold">
                                                {{ number_format($totals['gross'] ?? 0, 2) }}
                                            </span>
                                        {{-- MANDATORY DEDUCTIONS --}}
                                        @elseif ($columnKey === 'life_retirement')
                                            {{ number_format($statutory['life_retirement'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'government_life_retirement')
                                            {{ number_format($governmentShares['government_life_retirement'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'ec')
                                            {{ number_format($governmentShares['ec'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'phic')
                                            {{ number_format($statutory['phic'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'government_phic')
                                            {{ number_format($governmentShares['government_phic'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'mandatory_pagibig')
                                            {{ number_format($statutory['mandatory_pagibig'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'hdmf_ps_2_ms')
                                            {{ number_format($statutory['hdmf_ps_2_ms'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'government_pagibig')
                                            {{ number_format($governmentShares['government_pagibig'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'ea_deduction')
                                            {{ number_format($statutory['ea_deduction'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'mandatory_deduction_adjustment')
                                            {{ number_format($totals['mandatory_deduction_adjustment'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'total_mandatory_deductions')
                                            {{ number_format($totals['total_mandatory_deductions'] ?? 0, 2) }}
                                        {{-- TAX CALCULATION --}}
                                        @elseif ($columnKey === 'entry_date')
                                            {{ $tax['entry_date'] ?? '-' }}
                                        @elseif ($columnKey === 'tax_salary_grade')
                                            {{ $tax['salary_grade'] ?? '-' }}
                                        @elseif ($columnKey === 'tax_salary')
                                            {{ number_format($tax['salary'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_subsistence')
                                            {{ number_format($tax['subsistence'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_hazard')
                                            {{ number_format($tax['hazard'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_gross_compensation')
                                            {{ number_format($totals['net_compensation'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_deductions')
                                            {{ number_format($tax['monthly_mandatory_deductions'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_other_deductions')
                                            {{ number_format(($programs['total'] ?? 0) + ($loans['total'] ?? 0), 2) }}
                                        @elseif ($columnKey === 'tax_refunds')
                                            {{ number_format(0, 2) }}
                                        @elseif ($columnKey === 'tax_monthly_net_income')
                                            {{ number_format($tax['monthly_net_income'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_adjustment')
                                            {{ number_format($tax['tax_adjustment'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_total_months')
                                            {{ number_format($tax['total_months'] ?? 12, 2) }}
                                        @elseif ($columnKey === 'tax_leave_without_pay_months')
                                            {{ number_format($tax['annualization_leave_without_pay_months'] ?? $tax['leave_without_pay_months'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_net_months')
                                            {{ number_format($tax['months'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_total_gross_income')
                                            {{ number_format($tax['annual_gross_income'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_total_deductions')
                                            {{ number_format($tax['annual_mandatory_deductions'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'annual_taxable_income')
                                            {{ number_format($tax['annual_taxable_income'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'annual_tax_due')
                                            {{ number_format($tax['annual_tax_due'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'regular_monthly_tax_due')
                                            {{ number_format($tax['regular_monthly_tax_due'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'supplemental_tax_due')
                                            {{ number_format(($tax['gross_withholding_tax_adjustment'] ?? 0) + ($tax['supplemental_tax_due'] ?? 0), 2) }}
                                        @elseif ($columnKey === 'withholding_tax_gross')
                                            {{ number_format($tax['withholding_tax_gross'] ?? $tax['monthly_tax_due'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'withholding_tax_adjustment')
                                            {{ number_format($tax['withholding_tax_adjustment'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'withholding_tax')
                                            {{ number_format($tax['monthly_tax_due'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'net_after_tax')
                                            {{ number_format($totals['net_after_tax'] ?? 0, 2) }}
                                        {{-- NET PAY DISTRIBUTION --}}
                                        @elseif ($columnKey === 'net_before_other_deductions')
                                            {{ number_format($totals['net_before_other_deductions'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'loan_total')
                                            {{ number_format($totals['total_other_deductions'] ?? (($programs['total'] ?? 0) + ($loans['total'] ?? 0)), 2) }}
                                        @elseif ($columnKey === 'net_after_loan_deductions')
                                            <span class="font-semibold">
                                                {{ number_format($totals['net_after_loan_deductions'] ?? 0, 2) }}
                                            </span>
                                        @elseif ($columnKey === 'fifteenth')
                                            {{ number_format($totals['fifteenth'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'thirtieth')
                                            {{ number_format($totals['thirtieth'] ?? 0, 2) }}
                                        {{-- PROGRAM TOTAL --}}
                                        @elseif ($columnKey === 'program_total')
                                            {{ number_format($programs['total'] ?? 0, 2) }}
                                        {{-- COMPENSATION COLUMNS --}}
                                        @elseif (str_starts_with($columnKey, 'compensation_'))
                                            @php
                                                $id = str_replace('compensation_', '', $columnKey);
                                            @endphp
                                            {{ number_format($earnings['compensations'][$id]['amount'] ?? 0, 2) }}
                                        {{-- ADJUSTMENT COLUMNS --}}
                                        @elseif ($columnKey === 'adjustment_basic_salary')
                                            {{ number_format($earnings['adjustments']['basic_salary'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'adjustment_subsistence')
                                            {{ number_format($earnings['adjustments']['subsistence'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'adjustment_laundry')
                                            {{ number_format($earnings['adjustments']['laundry'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'adjustment_pera')
                                            {{ number_format($earnings['adjustments']['pera'] ?? 0, 2) }}
                                        @elseif (str_starts_with($columnKey, 'adjustment_type_'))
                                            @php
                                                $adjustmentTypeId = str_replace('adjustment_type_', '', $columnKey);
                                            @endphp
                                            {{ number_format($earnings['adjustments']['extra_items'][$adjustmentTypeId]['signed_amount'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'adjustment_remarks')
                                            <div class="text-left">
                                                {{ $earnings['adjustments']['remarks'] ?? '-' }}
                                            </div>
                                        {{-- PROGRAM COLUMNS --}}
                                        @elseif (str_starts_with($columnKey, 'program_'))
                                            @php
                                                $programId = str_replace('program_', '', $columnKey);
                                                $programItem = collect($programs['items'] ?? [])
                                                    ->firstWhere('id', (int) $programId);
                                            @endphp
                                            {{ number_format($programItem['amount'] ?? 0, 2) }}
                                        {{-- LOAN COLUMNS --}}
                                        @else
                                            {{ number_format($loans['columns'][$columnKey] ?? 0, 2) }}
                                        @endif
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
