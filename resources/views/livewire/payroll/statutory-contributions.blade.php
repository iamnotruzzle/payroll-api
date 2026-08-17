<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Mandatory Deductions</h2>
            <p class="text-sm text-slate-600">Manage government contribution rules, effective periods, salary ranges, rates, and caps.</p>
        </div>
        <button
            type="button"
            x-on:click="erpOverlay.open($wire, 'statutory-contribution', { editingContributionId: null, code: '', name: '', isActive: true, splitAcrossCuts: false, isMpf: false, remarks: null })"
            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600"
        >
            New Contribution
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Contributions</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($contributions as $contribution)
                    <div class="flex items-start justify-between gap-3 px-4 py-3 {{ $selectedContributionId === $contribution->id ? 'bg-blue-50' : 'hover:bg-slate-50' }}">
                        <button wire:click="selectContribution({{ $contribution->id }})" type="button" class="min-w-0 flex-1 text-left">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-slate-900">{{ $contribution->name }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $contribution->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $contribution->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $contribution->code }} · {{ $contribution->brackets_count }} brackets</div>
                        </button>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" x-on:click="erpOverlay.open($wire, 'statutory-contribution', { selectedContributionId: {{ $contribution->id }}, editingContributionId: {{ $contribution->id }}, code: @js($contribution->code), name: @js($contribution->name), isActive: @js((bool) $contribution->is_active), splitAcrossCuts: @js((bool) $contribution->split_across_cuts), isMpf: @js((bool) $contribution->is_mpf), remarks: @js($contribution->remarks) }, true)" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium hover:bg-white">Edit</button>
                            <button wire:click="deleteContribution({{ $contribution->id }})" wire:confirm="Delete this contribution and all of its brackets?" type="button" class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">No mandatory deductions yet.</div>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="font-semibold">Salary and Effective-Date Brackets</h3>
                    <p class="text-xs text-slate-500">
                        @if ($selectedContribution)
                            {{ $selectedContribution->name }}
                        @else
                            Select a contribution to manage its brackets.
                        @endif
                    </p>
                </div>
                <button
                    type="button"
                    @disabled(! $selectedContribution)
                    x-on:click="erpOverlay.open($wire, 'statutory-bracket', { editingBracketId: null, effectiveStart: null, effectiveEnd: null, minSalary: 0, maxSalary: null, employeeRate: 0, employerRate: 0, employeeFixedAmount: null, employerFixedAmount: null, employeeCap: null, employerCap: null, bracketRemarks: null })"
                    class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    New Bracket
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Effective</th>
                            <th class="px-4 py-3 text-right">Salary Range</th>
                            <th class="px-4 py-3 text-right">Employee</th>
                            <th class="px-4 py-3 text-right">Government</th>
                            <th class="px-4 py-3">Remarks</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($selectedContribution?->brackets ?? [] as $bracket)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div>{{ $bracket->effective_start?->format('M d, Y') ?: 'Any start' }}</div>
                                    <div class="text-xs text-slate-500">to {{ $bracket->effective_end?->format('M d, Y') ?: 'Open ended' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div>{{ number_format((float) $bracket->min_salary, 2) }}</div>
                                    <div class="text-xs text-slate-500">to {{ $bracket->max_salary !== null ? number_format((float) $bracket->max_salary, 2) : 'No ceiling' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div>{{ number_format((float) $bracket->employee_rate * 100, 2) }}%</div>
                                    <div class="text-xs text-slate-500">Fixed {{ $bracket->employee_fixed_amount !== null ? number_format((float) $bracket->employee_fixed_amount, 2) : 'None' }}</div>
                                    <div class="text-xs text-slate-500">Cap {{ $bracket->employee_cap !== null ? number_format((float) $bracket->employee_cap, 2) : 'None' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div>{{ number_format((float) $bracket->employer_rate * 100, 2) }}%</div>
                                    <div class="text-xs text-slate-500">Fixed {{ $bracket->employer_fixed_amount !== null ? number_format((float) $bracket->employer_fixed_amount, 2) : 'None' }}</div>
                                    <div class="text-xs text-slate-500">Cap {{ $bracket->employer_cap !== null ? number_format((float) $bracket->employer_cap, 2) : 'None' }}</div>
                                </td>
                                <td class="max-w-[280px] px-4 py-3 text-xs text-slate-600">{{ $bracket->remarks ?: '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" x-on:click="erpOverlay.open($wire, 'statutory-bracket', { editingBracketId: {{ $bracket->id }}, effectiveStart: @js($bracket->effective_start?->toDateString()), effectiveEnd: @js($bracket->effective_end?->toDateString()), minSalary: {{ (float) $bracket->min_salary }}, maxSalary: @js($bracket->max_salary !== null ? (float) $bracket->max_salary : null), employeeRate: {{ (float) $bracket->employee_rate }}, employerRate: {{ (float) $bracket->employer_rate }}, employeeFixedAmount: @js($bracket->employee_fixed_amount !== null ? (float) $bracket->employee_fixed_amount : null), employerFixedAmount: @js($bracket->employer_fixed_amount !== null ? (float) $bracket->employer_fixed_amount : null), employeeCap: @js($bracket->employee_cap !== null ? (float) $bracket->employee_cap : null), employerCap: @js($bracket->employer_cap !== null ? (float) $bracket->employer_cap : null), bracketRemarks: @js($bracket->remarks) }, true)" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
                                    <button wire:click="deleteBracket({{ $bracket->id }})" wire:confirm="Delete this bracket?" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Select a contribution or add its first bracket.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

        <x-setup-form-modal
            name="statutory-contribution"
            title="New Contribution"
            edit-title="Edit Contribution"
            description="Contribution records define the payroll labels and status."
            size="lg"
        >
            <form wire:submit="saveContribution" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <label class="text-sm font-medium">Code</label>
                        <input wire:model="code" type="text" placeholder="philhealth" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Name</label>
                        <input wire:model="name" type="text" placeholder="PhilHealth" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm sm:grid-cols-3">
                    <label class="flex items-center gap-2 font-medium">
                        <input wire:model="isActive" type="checkbox" class="rounded border-slate-300">
                        Active
                    </label>
                    <label class="flex items-center gap-2 font-medium">
                        <input wire:model="splitAcrossCuts" type="checkbox" class="rounded border-slate-300">
                        Split cuts
                    </label>
                    <label class="flex items-center gap-2 font-medium">
                        <input wire:model="isMpf" type="checkbox" class="rounded border-slate-300">
                        MPF
                    </label>
                </div>

                <div>
                    <label class="text-sm font-medium">Remarks</label>
                    <textarea wire:model="remarks" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                    <button x-on:click="erpOverlay.close('statutory-contribution')" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save Contribution</button>
                </div>
            </form>
        </x-setup-form-modal>

        <x-setup-form-drawer
            name="statutory-bracket"
            title="New Bracket"
            edit-title="Edit Bracket"
            :description="$selectedContribution ? 'For '.$selectedContribution->name : 'Select a contribution first.'"
            size="lg"
        >
            <form wire:submit="saveBracket" class="space-y-4">
                @error('selectedContributionId') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Effective Start</label>
                        <input wire:model="effectiveStart" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('effectiveStart') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Effective End</label>
                        <input wire:model="effectiveEnd" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('effectiveEnd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Minimum Salary</label>
                        <input wire:model="minSalary" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('minSalary') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Maximum Salary</label>
                        <input wire:model="maxSalary" type="number" step="0.01" min="0" placeholder="No ceiling" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('maxSalary') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Employee Rate</label>
                        <input wire:model="employeeRate" type="number" step="0.0001" min="0" max="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Use 0.025 for 2.5%.</p>
                        @error('employeeRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Government Rate</label>
                        <input wire:model="employerRate" type="number" step="0.0001" min="0" max="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Use 0.12 for 12%.</p>
                        @error('employerRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Employee Fixed Amount</label>
                        <input wire:model="employeeFixedAmount" type="number" step="0.01" min="0" placeholder="No fixed amount" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('employeeFixedAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Government Fixed Amount</label>
                        <input wire:model="employerFixedAmount" type="number" step="0.01" min="0" placeholder="No fixed amount" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('employerFixedAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Employee Cap</label>
                        <input wire:model="employeeCap" type="number" step="0.01" min="0" placeholder="No cap" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('employeeCap') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Government Cap</label>
                        <input wire:model="employerCap" type="number" step="0.01" min="0" placeholder="No cap" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('employerCap') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">Bracket Remarks</label>
                    <textarea wire:model="bracketRemarks" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('bracketRemarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                    <button x-on:click="erpOverlay.close('statutory-bracket')" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button @disabled(! $selectedContribution) class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50">Save Bracket</button>
                </div>
            </form>
        </x-setup-form-drawer>
</section>
