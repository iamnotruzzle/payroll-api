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
            <button
                type="button"
                wire:click="saveDraft"
                wire:loading.attr="disabled"
                wire:target="saveDraft"
                class="rounded-md border border-[#696cff] bg-white px-4 py-2 text-sm font-medium text-[#5f61e6] hover:bg-[#f1f2ff] disabled:cursor-wait disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="saveDraft">Save as Draft</span>
                <span wire:loading wire:target="saveDraft">Saving Draft...</span>
            </button>
            <a href="{{ route('payroll.generation.configuration', ['division_id' => $divisionId, 'department_id' => $departmentId, 'payroll_type' => \App\Models\Payroll\PayrollType::CODE_GENERAL, 'period' => $period, 'working_days' => $workingDays, 'employee_type' => $employeeTypeFilter]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Change Configuration
            </a>
            <a href="{{ route('payroll.deduction-programs') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Manage Deduction Programs
            </a>
            <a href="{{ route('payroll.compensations') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Manage Compensations
            </a>
            <a href="{{ route('payroll.adjustment-types') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Manage Adjustment Types
            </a>
        </div>
    </div>

    @error('draft')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if (session('draft_success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <div class="font-semibold">{{ session('draft_success') }}</div>
            @if ($draftSavedAt)
                <div class="mt-1 text-emerald-800">Saved {{ $draftSavedAt }}.</div>
            @endif
        </div>
    @elseif ($draftNotice)
        <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <div class="font-semibold">{{ $draftNotice }}</div>
            @if ($draftSavedAt)
                <div class="mt-1">Last saved {{ $draftSavedAt }}.</div>
            @endif
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <label class="text-sm font-medium">Employee Search</label>
        <input wire:model.live.debounce.300ms="search" wire:loading.attr="disabled" wire:target="{{ $payrollLoadingTargets }}" type="search" placeholder="Filter generated payroll rows by employee ID or name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-2 md:grid-cols-4 lg:grid-cols-8">
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
                            <th colspan="6" class="border-b border-slate-200 px-4 py-3 text-center">Leave Basis</th>
                            <th class="border-b border-slate-200 px-4 py-3 text-center">Payroll Input</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3">Employee No.</th>
                            <th class="px-4 py-3">Employee Name</th>
                            <th class="px-4 py-3">Leave Period</th>
                            <th class="px-4 py-3 text-right">Subsistence</th>
                            <th class="px-4 py-3 text-right">PERA</th>
                            <th class="px-4 py-3 text-right">Laundry</th>
                            <th class="px-4 py-3 text-right">TEV</th>
                            <th class="px-4 py-3 text-right">Prior MRA Days</th>
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
                                <td class="px-4 py-3">{{ implode(', ', $row['leave_deduction']['periods'] ?? []) ?: '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.blur="leaveDeductionOverrides.{{ $row['emp_id'] }}.subsistence_days" type="number" min="0" max="31" step="0.001" class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.blur="leaveDeductionOverrides.{{ $row['emp_id'] }}.pera_days" type="number" min="0" max="31" step="0.001" class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.blur="leaveDeductionOverrides.{{ $row['emp_id'] }}.laundry_days" type="number" min="0" max="31" step="0.001" class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.blur="leaveDeductionOverrides.{{ $row['emp_id'] }}.tev_days" type="number" min="0" max="31" step="0.001" class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm">
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['mra_adjustment_days'] ?? 0, 3) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model.blur="deductionDayOverrides.{{ $row['emp_id'] }}"
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
                                <td colspan="9" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
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
        <div
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
            x-data="{
                open: false,
                adjustmentTypes: @js($allAdjustmentTypes->map(fn ($type) => ['id' => (int) $type->id, 'name' => $type->name])->values()),
                modal: { empId: '', employeeName: '', typeId: '', operator: 'ADD', amount: 0, existingIds: [] },
                start(empId, employeeName, existingIds, item = null) {
                    this.modal.empId = empId;
                    this.modal.employeeName = employeeName;
                    this.modal.existingIds = existingIds.map((id) => Number(id));
                    this.modal.typeId = item ? String(item.typeId) : '';
                    this.modal.operator = item ? item.operator : 'ADD';
                    this.modal.amount = item ? item.amount : 0;
                    this.open = true;
                },
                get availableTypes() {
                    return this.adjustmentTypes.filter((type) => !this.modal.existingIds.includes(type.id) || type.id === Number(this.modal.typeId));
                },
                save() {
                    if (!this.modal.typeId) {
                        return;
                    }

                    this.open = false;
                    $wire.saveEmployeeAdjustment(this.modal.empId, Number(this.modal.typeId), this.modal.operator, this.modal.amount);
                },
            }"
        >
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Deductions and Adjustments</h3>
                <p class="mt-1 text-sm text-slate-600">Compensation adjustments for the Regular payroll output.</p>
            </div>

            @error('adjustments')
                <div class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div class="overflow-x-auto">
                <table class="divide-y divide-slate-200 text-sm" style="min-width: {{ 1320 + ($adjustmentTypes->count() * 140) }}px;">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 align-middle">Employee Name</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Deduct Days</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Gross Compensation</th>
                            <th colspan="{{ 5 + $adjustmentTypes->count() }}" class="border-b border-slate-200 px-4 py-3 text-center">Compensation Adjustment</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Net Compensation</th>
                        </tr>
                        <tr>
                            <th class="px-3 py-3 text-right">Basic Salary</th>
                            <th class="px-3 py-3 text-right">Subsistence</th>
                            <th class="px-3 py-3 text-right">Laundry</th>
                            <th class="px-3 py-3 text-right">PERA</th>
                            @foreach ($adjustmentTypes as $type)
                                <th class="px-3 py-3 text-right">{{ $type->name }}</th>
                            @endforeach
                            <th class="px-3 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            @php
                                $employeeExtraItems = (array) ($compensationAdjustments[$row['emp_id']]['extra_items'] ?? []);
                                $employeeAdjustmentTypeIds = collect(array_keys($employeeExtraItems))->map(fn ($id) => (int) $id)->all();
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 align-top">
                                    <div class="flex min-w-[230px] items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $row['emp_id'] }}</div>
                                        </div>
                                        <button type="button" x-on:click="start(@js($row['emp_id']), @js($row['employee_name']), @js($employeeAdjustmentTypeIds))" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            +
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right align-top">{{ number_format($row['deduction_days'], 3) }}</td>
                                <td class="px-4 py-3 text-right align-top font-medium">{{ number_format($row['gross'], 2) }}</td>
                                @foreach (['basic_salary', 'subsistence', 'laundry', 'pera'] as $field)
                                    <td class="px-3 py-2 text-right align-top">
                                        <input
                                            wire:model.blur="compensationAdjustments.{{ $row['emp_id'] }}.{{ $field }}"
                                            type="number"
                                            step="0.01"
                                            class="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"
                                        >
                                    </td>
                                @endforeach
                                @foreach ($adjustmentTypes as $type)
                                    @php
                                        $item = $row['compensation_adjustments']['extra_items'][(string) $type->id] ?? null;
                                    @endphp
                                    <td class="px-3 py-2 align-top">
                                        @if ($item)
                                            <div class="flex min-w-[130px] items-center justify-end gap-2">
                                                <button type="button" x-on:click="start(@js($row['emp_id']), @js($row['employee_name']), @js($employeeAdjustmentTypeIds), { typeId: {{ $type->id }}, operator: @js($item['operator'] ?? 'ADD'), amount: @js($item['amount'] ?? 0) })" class="text-right text-xs font-semibold {{ ($item['operator'] ?? 'ADD') === 'LESS' ? 'text-red-700' : 'text-emerald-700' }} hover:underline">
                                                    {{ ($item['operator'] ?? 'ADD') === 'LESS' ? '-' : '+' }}{{ number_format($item['amount'] ?? 0, 2) }}
                                                </button>
                                                <button type="button" wire:click="removeEmployeeAdjustmentType('{{ $row['emp_id'] }}', {{ $type->id }})" class="rounded border border-red-200 px-1.5 py-0.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                                    x
                                                </button>
                                            </div>
                                        @else
                                            <span class="block min-w-[80px] text-center text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 align-top">
                                    <input
                                        wire:model.blur="compensationAdjustments.{{ $row['emp_id'] }}.remarks"
                                        type="text"
                                        class="w-64 rounded-md border px-2 py-1.5 text-sm {{ $row['compensation_adjustments']['remarks_missing'] ? 'border-red-400 bg-red-50' : 'border-slate-300' }}"
                                    >
                                </td>
                                <td class="px-4 py-3 text-right align-top font-semibold">{{ number_format($row['net_compensation'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 9 + $adjustmentTypes->count() }}" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td class="px-4 py-3">Totals</td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['gross'], 2) }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['basic_salary'], 2) }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['subsistence'], 2) }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['laundry'], 2) }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['pera'], 2) }}</td>
                                @foreach ($adjustmentTypes as $type)
                                    <td class="px-3 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['compensation_adjustments']['extra_items'][(string) $type->id]['signed_amount'] ?? 0), 2) }}</td>
                                @endforeach
                                <td class="px-3 py-3 text-right">
                                    <div>{{ number_format($totals['compensation_adjustments']['total'], 2) }}</div>
                                    @if ($adjustmentTypes->isNotEmpty())
                                        <div class="text-xs font-normal text-slate-500">Other {{ number_format($totals['compensation_adjustments']['extra_total'], 2) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_compensation'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

                <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="height: 100dvh;">
                    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-slate-900" x-text="modal.typeId ? 'Edit Adjustment' : 'Add Adjustment'"></h3>
                                <p class="mt-1 text-sm text-slate-600" x-text="modal.employeeName"></p>
                            </div>
                            <button type="button" x-on:click="open = false" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close adjustment modal">
                                &times;
                            </button>
                        </div>

                        <div class="space-y-4 px-5 py-5">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Adjustment Type</label>
                                <select x-model="modal.typeId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" x-bind:disabled="Boolean(modal.typeId)">
                                    <option value="">Select type</option>
                                    <template x-for="type in availableTypes" x-bind:key="type.id">
                                        <option x-bind:value="type.id" x-text="type.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Operator</label>
                                    <select x-model="modal.operator" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="ADD">Add</option>
                                        <option value="LESS">Less</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Amount</label>
                                    <input x-model="modal.amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                            <button type="button" x-on:click="open = false" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="button" x-on:click="save()" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Save
                            </button>
                        </div>
                    </div>
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
                            <th colspan="3" class="border-b border-slate-200 px-4 py-3 text-center">Employee Statutory Deductions</th>
                            <th colspan="3" class="border-b border-l-4 border-l-indigo-500 border-slate-200 bg-indigo-50 px-4 py-3 text-center text-indigo-700">Government Shares</th>
                            <th rowspan="2" class="px-4 py-3 text-right align-middle">Net Pay</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3 text-right">Life &amp; Retirement</th>
                            <th class="px-4 py-3 text-right">PhilHealth</th>
                            <th class="px-4 py-3 text-right">Pag-IBIG</th>
                            <th class="border-l-4 border-l-indigo-500 bg-indigo-50 px-4 py-3 text-right text-indigo-700">Govt. Life &amp; Retirement</th>
                            <th class="bg-indigo-50 px-4 py-3 text-right text-indigo-700">Govt. PhilHealth</th>
                            <th class="bg-indigo-50 px-4 py-3 text-right text-indigo-700">Govt. Pag-IBIG</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $row['employee_name'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['life_retirement'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['phic'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['statutory_deductions']['mandatory_pagibig'], 2) }}</td>
                                <td class="border-l-4 border-l-indigo-500 bg-indigo-50 px-4 py-3 text-right text-indigo-900">{{ number_format($row['statutory_government_shares']['government_life_retirement'] ?? 0, 2) }}</td>
                                <td class="bg-indigo-50 px-4 py-3 text-right text-indigo-900">{{ number_format($row['statutory_government_shares']['government_phic'] ?? 0, 2) }}</td>
                                <td class="bg-indigo-50 px-4 py-3 text-right text-indigo-900">{{ number_format($row['statutory_government_shares']['government_pagibig'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_before_other_deductions'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
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
                                                        wire:model.blur="deductionProgramSelections.{{ $program->id }}.employee_amounts.{{ $row['emp_id'] }}"
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
        @php
            $loanEmployees = $rows->map(fn ($row) => [
                'emp_id' => $row['emp_id'],
                'name' => $row['employee_name'],
            ])->values();
            $loanTypeOptions = $loanTypes->map(fn ($type) => [
                'id' => (string) $type->id,
                'label' => ($type->entity?->name ?? $type->entity?->code).' - '.$type->name,
            ])->values();
            $recentLoanSuggestions = $this->recentLoanSuggestionsForModal($rows, $loanTypes);
        @endphp
        <div
            class="space-y-4"
            x-on:loan-deduction-saved.window="closeLoanModal()"
            x-data="{
                loanModalOpen: false,
                savingLoan: false,
                loanEmployees: @js($loanEmployees),
                loanTypeOptions: @js($loanTypeOptions),
                recentLoanSuggestions: @js($recentLoanSuggestions),
                editingLoanItemId: null,
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
                openLoanModal(empId = '', loan = null) {
                    this.editingLoanItemId = loan ? loan.id : null;
                    this.loanForm = {
                        emp_id: loan ? String(loan.emp_id || empId || '') : String(empId || ''),
                        loan_type_id: loan ? String(loan.loan_type_id || '') : '',
                        loan_account_no: loan ? String(loan.loan_account_no || '') : '',
                        monthly_amortization: loan ? String(loan.monthly_amortization || '') : '',
                        amount_due: loan ? String(loan.amount_due || '') : '',
                        outstanding_balance: loan ? String(loan.outstanding_balance || '') : '',
                        principal_due: loan ? String(loan.principal_due || '') : '',
                        interest_due: loan ? String(loan.interest_due || '') : '',
                        penalty_due: loan ? String(loan.penalty_due || '') : '',
                        remarks: loan ? String(loan.remarks || '') : '',
                    };
                    this.loanModalOpen = true;
                    this.applyRecentLoanSuggestion();
                    this.syncLoanSelects();
                },
                closeLoanModal() {
                    this.loanModalOpen = false;
                    this.savingLoan = false;
                },
                clearLoanReferenceAndAmount() {
                    this.loanForm.loan_account_no = '';
                    this.loanForm.amount_due = '';
                },
                get selectedRecentLoanSuggestion() {
                    return this.recentLoanSuggestions[`${this.loanForm.emp_id}|${this.loanForm.loan_type_id}`] || null;
                },
                applyRecentLoanSuggestion() {
                    if (this.editingLoanItemId) {
                        return;
                    }

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
                saveLoan() {
                    this.savingLoan = true;
                    $wire.saveLoanDeductionFromModal(this.editingLoanItemId, this.loanForm)
                        .then(() => { this.savingLoan = false; })
                        .catch(() => { this.savingLoan = false; });
                },
            }"
        >
            @if (session('loan_import_status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('loan_import_status') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <h3 class="font-semibold">Loan Deductions</h3>
                    <p class="text-sm text-slate-600">Loan deductions for {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }} can be imported or entered manually.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-on:click="openLoanModal()" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Add Employee Loan
                    </button>
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

            @error('loanDeductionForm')
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div x-cloak x-show="loanModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="display: none; height: 100dvh;">
                    <div x-on:click.outside="closeLoanModal()" class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-slate-900" x-text="editingLoanItemId ? 'Edit Loan Deduction' : 'Add Employee Loan'"></h3>
                                <p class="mt-1 text-sm text-slate-600">Included in Loan Deductions for {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }}.</p>
                            </div>
                            <button type="button" x-on:click="closeLoanModal()" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close loan deduction modal">
                                &times;
                            </button>
                        </div>

                        <div class="grid gap-4 px-5 py-5 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Employee</label>
                                <select x-ref="loanEmployee" x-model="loanForm.emp_id" x-on:change="$nextTick(() => applyRecentLoanSuggestion())" data-select2-searchable data-placeholder="Search employee" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select employee</option>
                                    <template x-for="employee in loanEmployees" :key="employee.emp_id">
                                        <option :value="employee.emp_id" x-text="`${employee.name} - ${employee.emp_id}`"></option>
                                    </template>
                                </select>
                                @error('loanDeductionForm.emp_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Loan Type</label>
                                <select x-ref="loanType" x-model="loanForm.loan_type_id" x-on:change="$nextTick(() => applyRecentLoanSuggestion())" data-select2-searchable data-placeholder="Search loan type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select loan type</option>
                                    <template x-for="loanType in loanTypeOptions" :key="loanType.id">
                                        <option :value="loanType.id" x-text="loanType.label"></option>
                                    </template>
                                </select>
                                @error('loanDeductionForm.loan_type_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-show="selectedRecentLoanSuggestion" class="md:col-span-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                                <span>Auto-filled from </span><span x-text="selectedRecentLoanSuggestion?.due_month"></span><span> for the same employee and loan type.</span>
                                <div x-show="amountChangedFromRecent()" class="mt-1 font-semibold text-amber-800">
                                    Same loan reference, but the amount differs from the previous <span x-text="Number(selectedRecentLoanSuggestion?.amount_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>.
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Reference/Account No.</label>
                                <div class="mt-1 flex gap-2">
                                    <input x-model="loanForm.loan_account_no" type="text" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <button type="button" x-on:click="clearLoanReferenceAndAmount()" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                                </div>
                                @error('loanDeductionForm.loan_account_no')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Monthly Amortization</label>
                                <input x-model="loanForm.monthly_amortization" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                @error('loanDeductionForm.monthly_amortization')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Amount Due</label>
                                <div class="mt-1 flex gap-2">
                                    <input x-model="loanForm.amount_due" type="number" min="0" step="0.01" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                    <button type="button" x-on:click="clearLoanReferenceAndAmount()" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                                </div>
                                @error('loanDeductionForm.amount_due')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Outstanding Balance</label>
                                <input x-model="loanForm.outstanding_balance" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Principal Due</label>
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
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                            <button type="button" x-on:click="closeLoanModal()" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="button" x-on:click="saveLoan()" x-bind:disabled="savingLoan" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                                <span x-show="!savingLoan">Save Loan Deduction</span>
                                <span x-show="savingLoan">Saving...</span>
                            </button>
                        </div>
                    </div>
                </div>

            @if ($showLoanImportModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="height: 100dvh;">
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
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Loan Deductions</th>
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
                                                <span class="float-right ml-2 font-semibold">{{ number_format($loan['amount_due'], 2) }}</span>
                                                <button type="button" x-on:click="openLoanModal(@js($row['emp_id']), @js($loan))" class="ml-2 rounded border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                                    Edit
                                                </button>
                                            </div>
                                        @empty
                                            <button type="button" x-on:click="openLoanModal(@js($row['emp_id']))" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Add loan deduction
                                            </button>
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
    @elseif ($currentStep === 7)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Tax Calculation</h3>
                <p class="text-sm text-slate-600">Annualized taxable income and monthly withholding tax before final review.</p>
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
                            <th class="px-4 py-3 text-right">Regular Tax</th>
                            <th class="px-4 py-3 text-right">Supplemental Tax</th>
                            <th class="px-4 py-3 text-right">Withholding Tax</th>
                            <th class="px-4 py-3 text-right">Net After Tax</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['emp_id'] }} Â· {{ $row['department'] }}</div>
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
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['regular_monthly_tax_due'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['supplemental_tax_due'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tax']['monthly_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_tax'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="20" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
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
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format(collect($totals['statutory_deductions'])->sum(), 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_gross_income'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_mandatory_deductions'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_taxable_income'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['regular_monthly_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['supplemental_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['withholding_tax'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_after_tax'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
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

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="exportRegularPayrollTemplate"
                    wire:loading.attr="disabled"
                    wire:target="exportRegularPayrollTemplate"
                    @disabled($rows->isEmpty())
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="exportRegularPayrollTemplate">Export Regular Payroll</span>
                    <span wire:loading wire:target="exportRegularPayrollTemplate">Preparing Excel...</span>
                </button>

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
                'adjustmentTypes' => $adjustmentTypes,
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
