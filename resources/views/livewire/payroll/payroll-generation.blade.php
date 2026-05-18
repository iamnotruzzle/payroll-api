<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Payroll Generation</h2>
            <p class="text-sm text-slate-600">Generate the selected month from the previous month MRA and computed payroll items.</p>
        </div>
        <a href="{{ route('payroll.compensations') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Manage Compensations
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr]">
            <div>
                <label class="text-sm font-medium">Department</label>
                <select wire:model.live="departmentId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Choose department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->department }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Payroll Month</label>
                <input wire:model.live="period" type="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Working Days</label>
                <input wire:model.live="workingDays" type="number" min="1" max="31" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Employee Type</label>
                <select wire:model.live="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($employeeTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Search</label>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Emp ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-2 md:grid-cols-6">
            @foreach ($steps as $number => $label)
                <button
                    type="button"
                    wire:click="goToStep({{ $number }})"
                    class="rounded-md border px-3 py-2 text-left text-sm transition {{ $currentStep === $number ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <span class="block text-xs font-semibold uppercase tracking-wide">Step {{ $number }}</span>
                    <span class="mt-1 block font-medium">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employees</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($rows->count()) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Basic Salary</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totals['basic_salary'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">15th Preview</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totals['fifteenth'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">30th Preview</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totals['thirtieth'], 2) }}</p>
        </div>
    </div>

    @if ($currentStep === 1)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">MRA Validation</h3>
                <p class="text-sm text-slate-600">
                    Source:
                    {{ $previousMraPeriod['start']->format('M d, Y') }} to {{ $previousMraPeriod['end']->format('M d, Y') }}
                    @if ($previousMraReport)
                        · {{ $previousMraReport->status }} by {{ $previousMraReport->generated_by }}
                    @else
                        · no previous month MRA found; current DTR deductions are shown as fallback.
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th colspan="2" class="border-b border-slate-200 px-4 py-3 text-center">Employee Information</th>
                            <th colspan="2" class="border-b border-slate-200 px-4 py-3 text-center">Prior Month MRA Basis</th>
                            <th class="border-b border-slate-200 px-4 py-3 text-center">Payroll Input</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3">Employee No.</th>
                            <th class="px-4 py-3">Employee Name</th>
                            <th class="px-4 py-3 text-right">Deduct Days</th>
                            <th class="px-4 py-3 text-right">Undertime/Tardy Minutes</th>
                            <th class="px-4 py-3 text-right">Deduct Days for Payroll</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $row['emp_id'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['department'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['mra_deduction_days'], 3) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['mra_minutes']) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model.live.debounce.300ms="deductionDayOverrides.{{ $row['emp_id'] }}"
                                        type="number"
                                        min="0"
                                        max="31"
                                        step="0.001"
                                        placeholder="{{ number_format($row['mra_deduction_days'], 3) }}"
                                        class="w-28 rounded-md border border-slate-300 px-3 py-2 text-right text-sm"
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($currentStep === 2)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Allowances Computation</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 align-middle">Employee Name</th>
                            <th colspan="{{ max(1, $compensations->count()) }}" class="border-b border-slate-200 px-4 py-3 text-center">Additional Earnings</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Gross Pay</th>
                        </tr>
                        <tr>
                            @forelse ($compensations as $item)
                                <th class="px-4 py-3 text-right">{{ $item->name }}</th>
                            @empty
                                <th class="px-4 py-3 text-right">None</th>
                            @endforelse
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $row['employee_name'] }}</td>
                                @forelse ($compensations as $item)
                                    <td class="px-4 py-3 text-right">{{ number_format($row['compensations'][$item->id]['amount'] ?? 0, 2) }}</td>
                                @empty
                                    <td class="px-4 py-3 text-right">-</td>
                                @endforelse
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['gross'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + max(1, $compensations->count()) }}" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($currentStep === 3)
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Deductions and Adjustments</h3>
            <p class="mt-1 text-sm text-slate-600">Editable leave/deduct days from Step 1 are applied before statutory and payroll split calculations.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 align-middle">Employee Name</th>
                            <th colspan="2" class="border-b border-slate-200 px-4 py-3 text-center">Pay Adjustments</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Gross Pay</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3 text-right">Deduct Days</th>
                            <th class="px-4 py-3 text-right">Other Adjustments</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $row['employee_name'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['deduction_days'], 3) }}</td>
                                <td class="px-4 py-3 text-right">0.00</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['gross'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($currentStep === 4)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Statutory</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 align-middle">Employee Name</th>
                            <th colspan="3" class="border-b border-slate-200 px-4 py-3 text-center">Statutory Deductions</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Net Pay</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3 text-right">Life &amp; Retirement</th>
                            <th class="px-4 py-3 text-right">PhilHealth</th>
                            <th class="px-4 py-3 text-right">Pag-IBIG</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $row['employee_name'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['life_retirement'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['phic'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_before_other_deductions'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($currentStep === 5)
        <div class="rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm">
            <h3 class="text-lg font-semibold">Loan Deductions</h3>
            <p class="mt-2 text-sm text-slate-600">Loan deduction setup will be added here in the next pass.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Review</h3>
            </div>
            @include('livewire.payroll.partials.payroll-review-table', ['rows' => $rows, 'compensations' => $compensations, 'totals' => $totals])
        </div>
    @endif

    @if ($currentStep !== 6)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Payroll Preview</h3>
            </div>
            @include('livewire.payroll.partials.payroll-review-table', ['rows' => $rows, 'compensations' => $compensations, 'totals' => $totals])
        </div>
    @endif

    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <button
            type="button"
            wire:click="previousStep"
            @disabled($currentStep === 1)
            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Previous
        </button>
        <div class="text-sm text-slate-600">Step {{ $currentStep }} of {{ count($steps) }}</div>
        <button
            type="button"
            wire:click="nextStep"
            @disabled($currentStep === 6)
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
        >
            Next
        </button>
    </div>
</section>
