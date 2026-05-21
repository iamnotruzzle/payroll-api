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
                                <input wire:model.live.debounce.500ms="adjustments.{{ $row['emp_id'] }}" type="number" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm">
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <input wire:model.live.debounce.500ms="overpayments.{{ $row['emp_id'] }}" type="number" step="0.01" class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-sm">
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
</section>
