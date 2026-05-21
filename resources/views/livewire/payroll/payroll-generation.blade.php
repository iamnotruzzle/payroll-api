@php
    $payrollLoadingTargets = 'search,goToStep,nextStep,previousStep';
    $selectedDepartment = $departments->firstWhere('department_id', $departmentId);
    $selectedDivision = $divisions->firstWhere('division_id', $divisionId);
    $scopeLabel = $selectedDepartment?->department ?? ($selectedDivision?->division ? $selectedDivision->division . ' Division' : 'Selected division');
@endphp

<section class="space-y-4 pb-24">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Payroll Generation</h2>
            <p class="text-sm text-slate-600">
                {{ $scopeLabel }} · {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }} · {{ $employeeTypeOptions[$employeeTypeFilter] ?? 'Selected employees' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('payroll.generation.configuration', ['division_id' => $divisionId, 'department_id' => $departmentId, 'payroll_type' => \App\Models\Payroll\PayrollType::CODE_GENERAL, 'period' => $period, 'working_days' => $workingDays, 'employee_type' => $employeeTypeFilter]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Change Configuration
            </a>
            <a href="{{ route('payroll.deduction-programs') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Manage Deduction Programs
            </a>
            <a href="{{ route('payroll.compensations') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Manage Compensations
            </a>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-sm font-medium">Employee Search</label>
        <input wire:model.live.debounce.300ms="search" wire:loading.attr="disabled" wire:target="{{ $payrollLoadingTargets }}" type="search" placeholder="Filter generated payroll rows by employee ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-2 md:grid-cols-7">
            @foreach ($steps as $number => $label)
                <button
                    type="button"
                    wire:click="goToStep({{ $number }})"
                    wire:loading.attr="disabled"
                    wire:target="{{ $payrollLoadingTargets }}"
                    class="rounded-md border px-3 py-2 text-left text-sm transition {{ $currentStep === $number ? 'border-[#5f61e6] bg-[#5f61e6] font-semibold text-white shadow-sm shadow-[#696cff]/25' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <span class="block text-xs font-semibold uppercase tracking-wide">Step {{ $number }}</span>
                    <span class="mt-1 block font-medium">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div
        wire:loading.class.remove="hidden"
        wire:target="{{ $payrollLoadingTargets }}"
        class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    >
        <div class="flex items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
            Loading payroll rows...
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Pay Basis</th>
                        <th class="px-4 py-3">Earnings</th>
                        <th class="px-4 py-3">Deductions</th>
                        <th class="px-4 py-3">Net Pay</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @for ($i = 0; $i < 8; $i++)
                        <tr class="animate-pulse">
                            <td class="px-4 py-4">
                                <div class="h-4 w-44 rounded bg-slate-200"></div>
                                <div class="mt-2 h-3 w-24 rounded bg-slate-100"></div>
                            </td>
                            <td class="px-4 py-4"><div class="h-4 w-28 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-36 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-32 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-28 rounded bg-slate-200"></div></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <div wire:loading.class="hidden" wire:target="{{ $payrollLoadingTargets }}">
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
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <h3 class="font-semibold">Deduction Programs</h3>
                    <p class="text-sm text-slate-600">Turn recurring deductions on for this payroll run and choose who they apply to.</p>
                </div>
                <a href="{{ route('payroll.deduction-programs') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Manage Programs
                </a>
            </div>

            @php
                $activeDeductionPrograms = $deductionPrograms->filter(fn ($program) => filter_var($deductionProgramSelections[(string) $program->id]['enabled'] ?? false, FILTER_VALIDATE_BOOL));
                $programPreviewWidth = max(920, 720 + ($activeDeductionPrograms->count() * 170));
            @endphp

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="font-semibold">Program Setup</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1120px] border-separate border-spacing-0 text-sm">
                        <thead class="bg-slate-100 text-left text-xs uppercase text-slate-600">
                            <tr>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Program</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Default</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Applies To</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Employees</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Amount Source</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Status</th>
                                <th class="border-b border-slate-300 px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deductionPrograms as $program)
                                @php
                                    $selection = $deductionProgramSelections[(string) $program->id] ?? ['enabled' => false, 'mode' => 'all', 'employee_ids' => [], 'amount_mode' => 'program'];
                                    $isEnabled = filter_var($selection['enabled'] ?? false, FILTER_VALIDATE_BOOL);
                                    $selectedEmployeeIds = collect($selection['employee_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
                                @endphp
                                <tr wire:key="deduction-program-row-{{ $program->id }}" class="hover:bg-slate-50">
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        <div class="font-semibold text-slate-900">{{ $program->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $program->is_percentage ? 'Percentage of basic salary' : 'Fixed amount' }}</div>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-medium">
                                        {{ number_format((float) $program->value, 4) }}
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        <select wire:model.live="deductionProgramSelections.{{ $program->id }}.mode" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs">
                                            <option value="all">All employees</option>
                                            <option value="include">Specific employees only</option>
                                            <option value="exclude">All except specific employees</option>
                                        </select>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        @if (($selection['mode'] ?? 'all') !== 'all')
                                            <div wire:ignore wire:key="program-employee-picker-{{ $program->id }}-{{ $selection['mode'] ?? 'all' }}">
                                                <select
                                                    data-select2-employee-picker
                                                    data-model="deductionProgramSelections.{{ $program->id }}.employee_ids"
                                                    data-placeholder="{{ ($selection['mode'] ?? 'all') === 'include' ? 'Choose included employees' : 'Choose excluded employees' }}"
                                                    multiple
                                                    class="w-full"
                                                >
                                                    @foreach ($rows as $row)
                                                        <option value="{{ $row['emp_id'] }}" @selected(in_array((string) $row['emp_id'], $selectedEmployeeIds, true))>
                                                            {{ $row['emp_id'] }} - {{ $row['employee_name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-500">No employee picker needed</span>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        <select wire:model.live="deductionProgramSelections.{{ $program->id }}.amount_mode" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs">
                                            <option value="program">Use program value</option>
                                            <option value="employee">Employee-specific</option>
                                        </select>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $isEnabled ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">
                                            {{ $isEnabled ? 'Applied' : 'Not applied' }}
                                        </span>
                                    </td>
                                    <td class="border-b border-slate-200 px-3 py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            @if ($isEnabled)
                                                <button wire:click="removeDeductionProgram({{ $program->id }})" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                    Remove
                                                </button>
                                                <button wire:click="applyDeductionProgram({{ $program->id }})" type="button" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                                    Update Table
                                                </button>
                                            @else
                                                <button wire:click="applyDeductionProgram({{ $program->id }})" type="button" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                                    Apply
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                        No active deduction programs. Create one from Deduction Programs management.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            </div>

            <div class="grid gap-3">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h3 class="font-semibold">Program Deduction Preview</h3>
                    </div>
                    <div class="max-h-[640px] overflow-auto">
                        <table class="border-separate border-spacing-0 text-sm" style="min-width: {{ $programPreviewWidth }}px;">
                            <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                                <tr>
                                    <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Employee</th>
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Net Before Programs</th>
                                    @foreach ($activeDeductionPrograms as $program)
                                        <th class="border-b border-r border-slate-300 px-3 py-2 text-right">{{ $program->name }}</th>
                                    @endforeach
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Program Total</th>
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Net After Programs</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $programItems = collect($row['program_deductions']['items']);
                                    @endphp
                                    <tr class="hover:bg-slate-50">
                                        <td class="sticky left-0 border-b border-r border-slate-200 bg-inherit px-3 py-2">
                                            <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $row['emp_id'] }} &middot; {{ $row['department'] }}</div>
                                        </td>
                                        <td class="border-b border-r border-slate-200 px-3 py-2 text-right">{{ number_format($row['net_before_other_deductions'], 2) }}</td>
                                        @foreach ($activeDeductionPrograms as $program)
                                            @php
                                                $programItem = $programItems->firstWhere('id', $program->id);
                                                $programSelection = $deductionProgramSelections[(string) $program->id] ?? [];
                                            @endphp
                                            <td class="border-b border-r border-slate-200 px-3 py-2 text-right">
                                                @if ($programItem && (($programSelection['amount_mode'] ?? 'program') === 'employee'))
                                                    <input
                                                        wire:model.live.debounce.300ms="deductionProgramSelections.{{ $program->id }}.employee_amounts.{{ $row['emp_id'] }}"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="{{ number_format((float) $program->value, 2, '.', '') }}"
                                                        class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-xs"
                                                    >
                                                @elseif ($programItem)
                                                    <span class="font-semibold text-slate-800">{{ number_format($programItem['amount'], 2) }}</span>
                                                @else
                                                    <span class="text-xs text-slate-400">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold {{ ($row['program_deductions']['total'] ?? 0) > 0 ? 'text-blue-700' : 'text-slate-500' }}">
                                            {{ number_format($row['program_deductions']['total'] ?? 0, 2) }}
                                        </td>
                                        <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">{{ number_format($row['net_after_program_deductions'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 4 + $activeDeductionPrograms->count() }}" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($currentStep === 6)
        <div class="space-y-4">
            @if (session('loan_import_status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('loan_import_status') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <h3 class="font-semibold">Imported Deductions</h3>
                    <p class="text-sm text-slate-600">Validated loan and deduction imports for {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }} are matched to active employees.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('payroll.loan-imports.template') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Export Template
                    </a>
                    <button type="button" wire:click="openLoanImportModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Import Loan Excel
                    </button>
                    <a href="{{ route('payroll.loan-imports') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Recent Imports
                    </a>
                </div>
            </div>

            @if ($showLoanImportModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm">
                    <div class="flex max-h-[90vh] w-full max-w-6xl flex-col rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-slate-900">Import Loan Excel</h3>
                                <p class="mt-1 text-sm text-slate-600">Preview and validate the completed deduction template before saving it to payroll.</p>
                            </div>
                            <button type="button" wire:click="closeLoanImportModal" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close import modal">
                                &times;
                            </button>
                        </div>

                        <div class="space-y-4 overflow-y-auto px-5 py-5">
                            <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                                <div>
                                    <label class="text-sm font-medium">Loan Excel file</label>
                                    <input wire:model="loanFile" type="file" accept=".xlsx,.xls,.csv" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    @error('loanFile')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="flex items-end">
                                    <button type="button" wire:click="previewLoanImport" wire:loading.attr="disabled" wire:target="previewLoanImport,loanFile" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60 lg:w-auto">
                                        Preview Rows
                                    </button>
                                </div>
                            </div>

                            <div wire:loading.flex wire:target="previewLoanImport,saveLoanImport,loanFile" class="items-center gap-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
                                <span>Reading and validating loan rows...</span>
                            </div>

                            @if (! empty($loanImportPreview))
                                @if (($loanImportPreview['invalid_rows'] ?? 0) > 0)
                                    <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                        Fix the invalid rows in the workbook and preview again before saving.
                                    </div>
                                @endif

                                <div class="overflow-hidden rounded-lg border border-slate-200">
                                    <div class="max-h-[420px] overflow-auto">
                                        <table class="min-w-[1280px] border-separate border-spacing-0 text-sm">
                                            <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                                                <tr>
                                                    <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Row</th>
                                                    <th class="border-b border-r border-slate-300 px-3 py-2">Status</th>
                                                    <th class="border-b border-r border-slate-300 px-3 py-2">Due Month</th>
                                                    <th class="border-b border-r border-slate-300 px-3 py-2">Employee ID</th>
                                                    <th class="border-b border-r border-slate-300 px-3 py-2">Employee Name</th>
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
                                                        <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['employee_id'] ?: ($item['matched_emp_id'] ?? '') }}</td>
                                                        <td class="border-b border-r border-slate-200 px-3 py-2 font-medium">{{ $item['employee_name'] ?? '-' }}</td>
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
                                </div>
                            @else
                                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                                    Upload a loan Excel file, then preview rows before saving.
                                </div>
                            @endif
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                            <button type="button" wire:click="closeLoanImportModal" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="button" wire:click="saveLoanImport" wire:loading.attr="disabled" wire:target="saveLoanImport" @disabled(empty($loanImportPreview) || (($loanImportPreview['invalid_rows'] ?? 0) > 0)) class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                                Save Import
                            </button>
                        </div>
                            </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="max-h-[640px] overflow-auto">
                    <table class="min-w-[1120px] border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                            <tr>
                                <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Employee</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Net After Programs</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Imported Deductions</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Final Net Pay</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Deduction Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="sticky left-0 border-b border-r border-slate-200 bg-inherit px-3 py-2">
                                        <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $row['emp_id'] }} · {{ $row['department'] }}</div>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right">{{ number_format($row['net_after_program_deductions'], 2) }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold {{ ($row['loan_deductions']['total'] ?? 0) > 0 ? 'text-blue-700' : 'text-slate-500' }}">
                                        {{ number_format($row['loan_deductions']['total'] ?? 0, 2) }}
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">{{ number_format($row['net_after_loan_deductions'], 2) }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        @forelse ($row['loan_deductions']['items'] as $loan)
                                            <div class="mb-1 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs">
                                                <span class="font-semibold">{{ $loan['entity'] }}</span>
                                                <span class="text-slate-500">· {{ $loan['loan_account_no'] }}</span>
                                                <span class="float-right font-semibold">{{ number_format($loan['amount_due'], 2) }}</span>
                                            </div>
                                        @empty
                                            <span class="text-xs text-slate-500">No validated deduction import matched for this month.</span>
                                        @endforelse
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
        </div>
    @else
        @php
            $activeReviewDeductionPrograms = $deductionPrograms->filter(fn ($program) => filter_var($deductionProgramSelections[(string) $program->id]['enabled'] ?? false, FILTER_VALIDATE_BOOL));
        @endphp

        {{-- FINALIZE HEADER --}}
        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div>
                <h3 class="font-semibold">Review</h3>
                <p class="text-sm text-slate-600">
                    Final payroll summary before saving the payroll run.
                </p>
            </div>

            <button
                type="button"
                wire:click="finalizePayroll"
                wire:loading.attr="disabled"
                wire:target="finalizePayroll"
                @disabled($rows->isEmpty())
                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="finalizePayroll">Finalize Payroll Run</span>
                <span wire:loading wire:target="finalizePayroll">Saving Payroll Run...</span>
            </button>
        </div>

        @error('finalize')
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror

        @if (session('success'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                <div class="font-semibold">{{ session('success') }}</div>
                @if ($finalizedRunId)
                    <p class="mt-1">
                        Run #{{ $finalizedRunId }} saved for {{ $finalizedSummary['department'] ?? 'the selected department' }} covering {{ $finalizedSummary['period'] ?? $period }}.
                    </p>
                @endif
            </div>
        @endif

        {{-- REVIEW TABLE --}}
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Review</h3>
            </div>

            @include('livewire.payroll.partials.payroll-review-table', [
                'rows' => $rows,
                'compensations' => $compensations,
                'totals' => $totals,
                'loanColumnGroups' => $loanColumnGroups,
                'deductionPrograms' => $activeReviewDeductionPrograms,
            ])

        </div>
    @endif
    </div>

    <div class="pointer-events-none fixed inset-x-0 bottom-5 z-30 flex justify-center px-4">
        <div class="pointer-events-auto flex items-center gap-3 rounded-lg border border-white/50 bg-white/70 px-3 py-2 shadow-lg shadow-slate-900/10 backdrop-blur-md">
            <button
                type="button"
                wire:click="previousStep"
                wire:loading.attr="disabled"
                wire:target="{{ $payrollLoadingTargets }}"
                @disabled($currentStep === 1)
                class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Previous
            </button>
            <div class="min-w-20 text-center text-sm text-slate-700">Step {{ $currentStep }} of {{ count($steps) }}</div>
            <button
                type="button"
                wire:click="nextStep"
                wire:loading.attr="disabled"
                wire:target="{{ $payrollLoadingTargets }}"
                @disabled($currentStep === count($steps))
                class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Next
            </button>
        </div>
    </div>
</section>
