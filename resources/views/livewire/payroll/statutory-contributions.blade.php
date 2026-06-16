<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Mandatory Deductions</h2>
            <p class="text-sm text-slate-600">Manage government contribution rules, effective periods, salary ranges, rates, and caps.</p>
        </div>
        <button wire:click="createContribution" type="button" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
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
                            <button wire:click="editContribution({{ $contribution->id }})" type="button" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium hover:bg-white">Edit</button>
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
                <button wire:click="createBracket" type="button" @disabled(! $selectedContribution) class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50">
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
                                    <button wire:click="editBracket({{ $bracket->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
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

    @if ($showContributionModal)
        <div class="fixed inset-0 z-50 grid place-items-center bg-slate-900/45 px-4 py-6">
            <div class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <h3 class="font-semibold">{{ $editingContributionId ? 'Edit Contribution' : 'New Contribution' }}</h3>
                        <p class="text-xs text-slate-500">Contribution records define the payroll labels and status.</p>
                    </div>
                    <button wire:click="closeContributionModal" type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm font-medium text-slate-600 hover:bg-slate-50">Close</button>
                </div>

                <form wire:submit="saveContribution" class="p-4">
                    <div class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-[0.8fr_1.2fr]">
                            <div>
                                <label class="text-sm font-medium">Code</label>
                                <input wire:model.blur="code" type="text" placeholder="philhealth" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium">Name</label>
                                <input wire:model.blur="name" type="text" placeholder="PhilHealth" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm sm:grid-cols-3">
                            <label class="flex items-center gap-2 font-medium">
                                <input wire:model.change="isActive" type="checkbox" class="rounded border-slate-300">
                                Active
                            </label>
                            <label class="flex items-center gap-2 font-medium">
                                <input wire:model.change="splitAcrossCuts" type="checkbox" class="rounded border-slate-300">
                                Split cuts
                            </label>
                            <label class="flex items-center gap-2 font-medium">
                                <input wire:model.change="isMpf" type="checkbox" class="rounded border-slate-300">
                                MPF
                            </label>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Remarks</label>
                            <textarea wire:model.blur="remarks" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                            @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                        <button wire:click="closeContributionModal" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                        <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save Contribution</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showBracketModal)
        <div class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-900/45 px-4 py-6">
            <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <h3 class="font-semibold">{{ $editingBracketId ? 'Edit Bracket' : 'New Bracket' }}</h3>
                        <p class="text-xs text-slate-500">
                            @if ($selectedContribution)
                                For {{ $selectedContribution->name }}
                            @else
                                Select a contribution first.
                            @endif
                        </p>
                    </div>
                    <button wire:click="closeBracketModal" type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-sm font-medium text-slate-600 hover:bg-slate-50">Close</button>
                </div>

                <form wire:submit="saveBracket" class="p-4">
                    @error('selectedContributionId') <p class="mb-3 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium">Effective Start</label>
                            <input wire:model.blur="effectiveStart" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('effectiveStart') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Effective End</label>
                            <input wire:model.blur="effectiveEnd" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('effectiveEnd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Minimum Salary</label>
                            <input wire:model.blur="minSalary" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('minSalary') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Maximum Salary</label>
                            <input wire:model.blur="maxSalary" type="number" step="0.01" min="0" placeholder="No ceiling" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('maxSalary') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Employee Rate</label>
                            <input wire:model.blur="employeeRate" type="number" step="0.0001" min="0" max="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Use 0.025 for 2.5%.</p>
                            @error('employeeRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Government Rate</label>
                            <input wire:model.blur="employerRate" type="number" step="0.0001" min="0" max="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Use 0.12 for 12%.</p>
                            @error('employerRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Employee Fixed Amount</label>
                            <input wire:model.blur="employeeFixedAmount" type="number" step="0.01" min="0" placeholder="No fixed amount" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('employeeFixedAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Government Fixed Amount</label>
                            <input wire:model.blur="employerFixedAmount" type="number" step="0.01" min="0" placeholder="No fixed amount" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('employerFixedAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Employee Cap</label>
                            <input wire:model.blur="employeeCap" type="number" step="0.01" min="0" placeholder="No cap" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('employeeCap') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium">Government Cap</label>
                            <input wire:model.blur="employerCap" type="number" step="0.01" min="0" placeholder="No cap" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('employerCap') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="text-sm font-medium">Bracket Remarks</label>
                        <textarea wire:model.blur="bracketRemarks" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('bracketRemarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                        <button wire:click="closeBracketModal" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                        <button @disabled(! $selectedContribution) class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50">Save Bracket</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</section>
