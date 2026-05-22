@php
    $selectedDepartment = $departments->firstWhere('department_id', $departmentId);
    $selectedDivision = $divisions->firstWhere('division_id', $divisionId);
    $scopeLabel = $selectedDepartment?->department ?? ($selectedDivision?->division ? $selectedDivision->division . ' Division' : 'Selected division');
@endphp

<section class="space-y-4 pb-24">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Hazard Pay Payroll</h2>
            <p class="text-sm text-slate-600">
                {{ $scopeLabel }} &middot; {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }} &middot; {{ $employeeTypeOptions[$employeeTypeFilter] ?? 'Selected employees' }}
            </p>
        </div>
        <a href="{{ route('payroll.generation.configuration', ['division_id' => $divisionId, 'department_id' => $departmentId, 'payroll_type' => \App\Models\Payroll\PayrollType::CODE_HAZARD, 'period' => $period, 'working_days' => $workingDays, 'employee_type' => $employeeTypeFilter]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Change Configuration
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-sm font-medium">Employee Search</label>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Filter hazard payroll rows by employee ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-2 md:grid-cols-3">
            @foreach ($steps as $number => $label)
                <button
                    type="button"
                    wire:click="goToStep({{ $number }})"
                    class="rounded-md border px-3 py-2 text-left text-sm transition {{ $currentStep === $number ? 'border-[#5f61e6] bg-[#5f61e6] font-semibold text-white shadow-sm shadow-[#696cff]/25' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <span class="block text-xs font-semibold uppercase tracking-wide">Step {{ $number }}</span>
                    <span class="mt-1 block font-medium">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($currentStep === 1)
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Position</th>
                        <th class="px-4 py-3 text-right">Basic Salary</th>
                        <th class="px-4 py-3 text-right">Hazard %</th>
                        <th class="px-4 py-3 text-right">Gross Hazard</th>
                        <th class="px-4 py-3 text-right">Adjustment</th>
                        <th class="px-4 py-3 text-right">Overpayment</th>
                        <th class="px-4 py-3 text-right">Adjusted Gross</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['emp_id'] }} &middot; {{ $row['department'] ?: 'No department' }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div>{{ $row['position'] ?: '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $row['sg_step'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right align-top">{{ number_format($row['basic_salary'], 2) }}</td>
                            <td class="px-4 py-3 text-right align-top">{{ number_format($row['hazard_rate'] * 100, 0) }}%</td>
                            <td class="px-4 py-3 text-right align-top">{{ number_format($row['gross_hazard_pay'], 2) }}</td>
                            <td class="px-4 py-3 text-right align-top">
                                <input wire:model.blur="adjustments.{{ $row['emp_id'] }}" type="number" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm">
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <input wire:model.blur="overpayments.{{ $row['emp_id'] }}" type="number" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm">
                            </td>
                            <td class="px-4 py-3 text-right align-top font-semibold">{{ number_format($row['adjusted_gross_hazard_pay'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No employees found for this payroll configuration.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="2" class="px-4 py-3">Totals</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                            <td></td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['gross_hazard_pay'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['adjustments'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['overpayments'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($totals['adjusted_gross_hazard_pay'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    @elseif ($currentStep === 2)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Tax Calculation</h3>
                <p class="text-sm text-slate-600">Annualized taxable hazard pay and monthly withholding tax before review.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1980px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Entry Date</th>
                            <th class="px-4 py-3 text-right">SG</th>
                            <th class="px-4 py-3 text-right">Salary</th>
                            <th class="px-4 py-3 text-right">Subsistence</th>
                            <th class="px-4 py-3 text-right">Hazard</th>
                            <th class="px-4 py-3 text-right">Deductions</th>
                            <th class="px-4 py-3 text-right">Net Monthly Income</th>
                            <th class="px-4 py-3 text-right">Tax Adjustment</th>
                            <th class="px-4 py-3 text-right">Total Months</th>
                            <th class="px-4 py-3 text-right">Leave W/O Pay (Months)</th>
                            <th class="px-4 py-3 text-right">Net, Months</th>
                            <th class="px-4 py-3 text-right">Total Gross Income</th>
                            <th class="px-4 py-3 text-right">Total Deductions</th>
                            <th class="px-4 py-3 text-right">Taxable Income (Year)</th>
                            <th class="px-4 py-3 text-right">Tax Due (Year)</th>
                            <th class="px-4 py-3 text-right">Withholding Tax</th>
                            <th class="px-4 py-3 text-right">Net After Tax</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['emp_id'] }} &middot; {{ $row['department'] ?: 'No department' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $row['tax']['entry_date'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $row['tax']['salary_grade'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['salary'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['subsistence'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['hazard'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['monthly_mandatory_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['monthly_net_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['tax_adjustment'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_months'] ?? 12, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['leave_without_pay_months'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['months'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_gross_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_mandatory_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_taxable_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tax']['monthly_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_tax'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="px-4 py-8 text-center text-sm text-slate-500">No employees found for this payroll configuration.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td class="px-4 py-3">Totals</td>
                                <td colspan="2"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['adjusted_gross_hazard_pay'], 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_gross_income'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_mandatory_deductions'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_taxable_income'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['withholding_tax'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_after_tax'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Review</h3>
                <p class="text-sm text-slate-600">Final hazard pay summary with withholding tax applied.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[1220px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Position</th>
                            <th class="px-4 py-3 text-right">Basic Salary</th>
                            <th class="px-4 py-3 text-right">Gross Hazard</th>
                            <th class="px-4 py-3 text-right">Adjusted Gross</th>
                            <th class="px-4 py-3 text-right">Withholding Tax</th>
                            <th class="px-4 py-3 text-right">Net After Tax</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['emp_id'] }} &middot; {{ $row['department'] ?: 'No department' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ $row['position'] ?: '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['sg_step'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['basic_salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['gross_hazard_pay'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['adjusted_gross_hazard_pay'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['monthly_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_tax'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No employees found for this payroll configuration.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="2" class="px-4 py-3">Totals</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['gross_hazard_pay'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['adjusted_gross_hazard_pay'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['withholding_tax'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_after_tax'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif

    <div class="pointer-events-none fixed inset-x-0 bottom-5 z-30 flex justify-center px-4">
        <div class="pointer-events-auto flex items-center gap-3 rounded-lg border border-white/50 bg-white/70 px-3 py-2 shadow-lg shadow-slate-900/10 backdrop-blur-md">
            <button type="button" wire:click="previousStep" @disabled($currentStep === 1) class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50">
                Previous
            </button>
            <div class="min-w-20 text-center text-sm text-slate-700">Step {{ $currentStep }} of {{ count($steps) }}</div>
            <button type="button" wire:click="nextStep" @disabled($currentStep === count($steps)) class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50">
                Next
            </button>
        </div>
    </div>
</section>
