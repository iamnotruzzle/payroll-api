<section class="space-y-6">
    <div>
        <h2 class="text-2xl font-semibold">Payroll History</h2>
        <p class="text-sm text-slate-600">Payroll snapshot records.</p>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div>
            <label class="text-sm font-medium">Date From</label>
            <input type="date" wire:model.live="dateFrom" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium">Date To</label>
            <input type="date" wire:model.live="dateTo" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Payroll Period</th>
                    <th class="px-3 py-2">Payroll Type</th>
                    <th class="px-3 py-2">Generated</th>
                    <th class="px-3 py-2 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($batches as $batch)
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $batch->payroll_period }}</td>
                        <td class="px-3 py-2">{{ $batch->payroll_type }}</td>
                        <td class="px-3 py-2">{{ $batch->snapshot_created_at?->format('M d, Y h:i A') }}</td>
                        <td class="px-3 py-2 text-right">
                            <button wire:click="selectBatch({{ $batch->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">
                                View Snapshot
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-8 text-center text-slate-500">No payroll snapshots found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $batches->links() }}
    </div>

    @if ($records->isNotEmpty())
        @php
            $firstSnapshot = $records->first()->snapshot_json ?? [];
            $columnGroups = $firstSnapshot['column_groups'] ?? [];
            $columns = $firstSnapshot['columns'] ?? [];
            $reviewTableWidth = 2200;

            foreach ($columnGroups as $group) {
                $reviewTableWidth += count($group) * 120;
            }
        @endphp

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="divide-y divide-slate-200 text-sm" style="min-width: {{ $reviewTableWidth }}px;">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    {{-- GROUP HEADERS --}}
                    <tr>
                        @foreach ($columnGroups as $group)
                            <th colspan="{{ count($group['columns']) }}" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">
                                {{ $group['label'] }}
                            </th>
                        @endforeach
                    </tr>
                    {{-- COLUMN HEADERS --}}
                    <tr>
                        @foreach ($columnGroups as $group)
                            @foreach ($group['columns'] as $columnKey)
                                <th class="px-4 py-3 text-right whitespace-nowrap">
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
                            $tax = $snapshot['tax'] ?? [];
                            $programs = $snapshot['program_deductions'] ?? [];
                            $loans = $snapshot['loan_deductions'] ?? [];
                            $totals = $snapshot['totals'] ?? [];
                        @endphp
                        <tr class="hover:bg-slate-50">
                            @foreach ($columnGroups as $group)
                                @foreach ($group['columns'] as $columnKey)
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
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
                                        {{-- STATUTORY --}}
                                        @elseif ($columnKey === 'life_retirement')
                                            {{ number_format($statutory['life_retirement'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'phic')
                                            {{ number_format($statutory['phic'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'mandatory_pagibig')
                                            {{ number_format($statutory['mandatory_pagibig'] ?? 0, 2) }}
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
                                        @elseif ($columnKey === 'tax_deductions')
                                            {{ number_format($tax['monthly_mandatory_deductions'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_monthly_net_income')
                                            {{ number_format($tax['monthly_net_income'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_adjustment')
                                            {{ number_format($tax['tax_adjustment'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'tax_total_months')
                                            {{ number_format($tax['total_months'] ?? 12, 2) }}
                                        @elseif ($columnKey === 'tax_leave_without_pay_months')
                                            {{ number_format($tax['leave_without_pay_months'] ?? 0, 2) }}
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
                                        @elseif ($columnKey === 'withholding_tax')
                                            {{ number_format($tax['monthly_tax_due'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'net_after_tax')
                                            {{ number_format($totals['net_after_tax'] ?? 0, 2) }}
                                        {{-- NET PAY DISTRIBUTION --}}
                                        @elseif ($columnKey === 'net_before_other_deductions')
                                            {{ number_format($totals['net_before_other_deductions'] ?? 0, 2) }}
                                        @elseif ($columnKey === 'loan_total')
                                            {{ number_format($loans['total'] ?? 0, 2) }}
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
