@php
    $loanColumnGroups = $loanColumnGroups ?? [];
    $loanColumnCount = collect($loanColumnGroups)->sum(fn ($columns) => count($columns));
    $deductionPrograms = collect($deductionPrograms ?? []);
    $deductionProgramCount = $deductionPrograms->count();
    $adjustmentTypes = collect($adjustmentTypes ?? []);
    $adjustmentTypeCount = $adjustmentTypes->count();
    $reviewTableWidth = max(3170, 3060 + ($compensations->count() * 120) + ($adjustmentTypeCount * 140) + ($deductionProgramCount * 150) + ($loanColumnCount * 120));
@endphp

<div class="overflow-x-auto">
    <table class="divide-y divide-slate-200 text-sm" style="min-width: {{ $reviewTableWidth }}px;">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th colspan="3" class="payroll-sticky-employee-info-group border-b border-r-2 border-slate-300 px-4 py-3 text-center">Employee Information</th>
                <th colspan="3" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">Pay Basis</th>
                <th colspan="{{ 2 + $compensations->count() }}" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">Earnings</th>
                <th colspan="{{ 2 + $adjustmentTypeCount }}" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">Compensation Adjustment</th>
                <th colspan="10" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">Mandatory Deductions</th>
                <th colspan="2" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">Tax Calculation</th>
                <th colspan="{{ max(1, $deductionProgramCount) + 1 }}" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">Deduction Programs</th>
                @foreach ($loanColumnGroups as $groupLabel => $columns)
                    <th colspan="{{ count($columns) }}" class="border-b border-r-2 border-slate-300 px-4 py-3 text-center">{{ $groupLabel }}</th>
                @endforeach
                <th colspan="5" class="border-b border-slate-200 px-4 py-3 text-center">Net Pay Distribution</th>
            </tr>
            <tr>
                <th class="payroll-sticky-employee-no-header px-4 py-3">Employee No.</th>
                <th class="payroll-sticky-employee-name-header px-4 py-3">Employee Name</th>
                <th class="payroll-sticky-employee-position-header border-r-2 border-slate-300 px-4 py-3">Position</th>
                <th class="px-4 py-3 text-right">Salary Grade</th>
                <th class="px-4 py-3 text-right">Step</th>
                <th class="border-r-2 border-slate-300 px-4 py-3 text-right">Deduct Days</th>
                <th class="px-4 py-3 text-right">Basic Pay</th>
                @foreach ($compensations as $item)
                    <th class="px-4 py-3 text-right">{{ $item->name }}</th>
                @endforeach
                <th class="border-r-2 border-slate-300 px-4 py-3 text-right">Gross Pay</th>
                <th class="px-4 py-3 text-right">Adjustment Total</th>
                @foreach ($adjustmentTypes as $type)
                    <th class="px-4 py-3 text-right">{{ $type->name }}</th>
                @endforeach
                <th class="border-r-2 border-slate-300 px-4 py-3 text-right">Net Compensation</th>
                <th class="px-4 py-3 text-right">GSIS (PS)</th>
                <th class="px-4 py-3 text-right">GSIS (GS)</th>
                <th class="px-4 py-3 text-right">EC</th>
                <th class="px-4 py-3 text-right">PHIC (PS)</th>
                <th class="px-4 py-3 text-right">PHIC (GS)</th>
                <th class="px-4 py-3 text-right">HDMF (PS) 1</th>
                <th class="px-4 py-3 text-right">HDMF (PS) 2 MS</th>
                <th class="px-4 py-3 text-right">HDMF (GS)</th>
                <th class="px-4 py-3 text-right">EA Deduction</th>
                <th class="border-r-2 border-slate-300 px-4 py-3 text-right">Total Mandatory Deductions</th>
                <th class="px-4 py-3 text-right">Withholding Tax</th>
                <th class="border-r-2 border-slate-300 px-4 py-3 text-right">Net After Tax</th>
                @forelse ($deductionPrograms as $program)
                    <th class="px-4 py-3 text-right">{{ $program->name }}</th>
                @empty
                    <th class="px-4 py-3 text-right">No Active Programs</th>
                @endforelse
                <th class="border-r-2 border-slate-300 px-4 py-3 text-right">Program Total</th>
                @foreach ($loanColumnGroups as $columns)
                    @foreach ($columns as $key => $label)
                        <th class="px-4 py-3 text-right {{ $loop->last ? 'border-r-2 border-slate-300' : '' }}">{{ $label }}</th>
                    @endforeach
                @endforeach
                <th class="px-4 py-3 text-right">Net Before Other Deductions</th>
                <th class="px-4 py-3 text-right">TOTAL OTHER DEDUCTIONS</th>
                <th class="px-4 py-3 text-right">Final Net Pay</th>
                <th class="px-4 py-3 text-right">15th Payroll</th>
                <th class="px-4 py-3 text-right">30th Payroll</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr class="hover:bg-slate-50">
                    <td class="payroll-sticky-employee-no-cell px-4 py-3 font-medium">{{ $row['emp_id'] }}</td>
                    <td class="payroll-sticky-employee-name-cell px-4 py-3">
                        <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                    </td>
                    <td class="payroll-sticky-employee-position-cell border-r-2 border-slate-200 px-4 py-3">{{ $row['position'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ $row['salary_grade'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ $row['step'] }}</td>
                    <td class="border-r-2 border-slate-200 px-4 py-3 text-right">{{ number_format($row['deduction_days'], 3) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['basic_salary'], 2) }}</td>
                    @foreach ($compensations as $item)
                        <td class="px-4 py-3 text-right">{{ number_format($row['compensations'][$item->id]['amount'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($row['gross'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['compensation_adjustments']['total'] ?? 0, 2) }}</td>
                    @foreach ($adjustmentTypes as $type)
                        <td class="px-4 py-3 text-right">{{ number_format($row['compensation_adjustments']['extra_items'][(string) $type->id]['signed_amount'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($row['net_compensation'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['life_retirement'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_government_shares']['government_life_retirement'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_government_shares']['ec'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['phic'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_government_shares']['government_phic'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['hdmf_ps_2_ms'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_government_shares']['government_pagibig'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['ea_deduction'] ?? 0, 2) }}</td>
                    <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($row['total_mandatory_deductions'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['tax']['monthly_tax_due'] ?? 0, 2) }}</td>
                    <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_tax'] ?? 0, 2) }}</td>
                    @php
                        $programItems = collect($row['program_deductions']['items'] ?? []);
                    @endphp
                    @forelse ($deductionPrograms as $program)
                        @php
                            $programItem = $programItems->firstWhere('id', $program->id);
                        @endphp
                        <td class="px-4 py-3 text-right">{{ number_format($programItem['amount'] ?? 0, 2) }}</td>
                    @empty
                        <td class="px-4 py-3 text-right text-slate-400">-</td>
                    @endforelse
                    <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($row['program_deductions']['total'] ?? 0, 2) }}</td>
                    @foreach ($loanColumnGroups as $columns)
                        @foreach ($columns as $key => $label)
                            <td class="px-4 py-3 text-right {{ $loop->last ? 'border-r-2 border-slate-200' : '' }}">{{ number_format($row['loan_deductions']['columns'][$key] ?? 0, 2) }}</td>
                        @endforeach
                    @endforeach
                    <td class="px-4 py-3 text-right">{{ number_format($row['net_before_other_deductions'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($row['total_other_deductions'] ?? (($row['program_deductions']['total'] ?? 0) + ($row['loan_deductions']['total'] ?? 0)), 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_loan_deductions'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['fifteenth'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['thirtieth'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 28 + $compensations->count() + $adjustmentTypeCount + max(1, $deductionProgramCount) + $loanColumnCount }}" class="px-4 py-8 text-center text-slate-500">
                        No active HRIS employees found for the selected department.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot class="bg-slate-50 font-semibold">
                <tr>
                    <td colspan="6" class="border-r-2 border-slate-300 px-4 py-3">Totals</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                    @foreach ($compensations as $item)
                        <td class="px-4 py-3 text-right">{{ number_format($totals['compensations'][$item->id] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="border-r-2 border-slate-300 px-4 py-3 text-right">{{ number_format($totals['gross'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['compensation_adjustments']['total'], 2) }}</td>
                    @foreach ($adjustmentTypes as $type)
                        <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['compensation_adjustments']['extra_items'][(string) $type->id]['signed_amount'] ?? 0), 2) }}</td>
                    @endforeach
                    <td class="border-r-2 border-slate-300 px-4 py-3 text-right">{{ number_format($totals['net_compensation'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['life_retirement'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_government_shares']['government_life_retirement'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_government_shares']['ec'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['phic'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_government_shares']['government_phic'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['hdmf_ps_2_ms'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_government_shares']['government_pagibig'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['statutory_deductions']['ea_deduction'] ?? 0, 2) }}</td>
                    <td class="border-r-2 border-slate-300 px-4 py-3 text-right">{{ number_format($totals['total_mandatory_deductions'] ?? 0, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['withholding_tax'], 2) }}</td>
                    <td class="border-r-2 border-slate-300 px-4 py-3 text-right">{{ number_format($totals['net_after_tax'], 2) }}</td>
                    @forelse ($deductionPrograms as $program)
                        <td class="px-4 py-3 text-right">
                            {{ number_format($rows->sum(fn ($row) => collect($row['program_deductions']['items'] ?? [])->firstWhere('id', $program->id)['amount'] ?? 0), 2) }}
                        </td>
                    @empty
                        <td class="px-4 py-3 text-right text-slate-400">-</td>
                    @endforelse
                    <td class="border-r-2 border-slate-300 px-4 py-3 text-right">{{ number_format($totals['program_deductions'], 2) }}</td>
                    @foreach ($loanColumnGroups as $columns)
                        @foreach ($columns as $key => $label)
                            <td class="px-4 py-3 text-right {{ $loop->last ? 'border-r-2 border-slate-300' : '' }}">{{ number_format($totals['loan_columns'][$key] ?? 0, 2) }}</td>
                        @endforeach
                    @endforeach
                    <td class="px-4 py-3 text-right">{{ number_format($totals['net_before_other_deductions'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['total_other_deductions'] ?? (($totals['program_deductions'] ?? 0) + ($totals['loan_deductions'] ?? 0)), 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['net_after_loan_deductions'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['fifteenth'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($totals['thirtieth'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
