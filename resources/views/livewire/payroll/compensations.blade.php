<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Compensation Rules</h2>
        <p class="text-sm text-slate-600">Manage fixed, percentage, and formula-based compensation columns used in payroll previews.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
        <form wire:submit="save" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Rule' : 'New Rule' }}</h3>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model="name" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Computation</label>
                    <select wire:model.live="computationType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percentage">Percentage of basic salary</option>
                        <option value="formula">Formula</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Value</label>
                    <input wire:model="value" type="number" step="0.0001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">For percentage, use 25 or 0.25 for 25%.</p>
                    @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($computationType === 'formula')
                    <div>
                        <label class="text-sm font-medium">Formula</label>
                        <input wire:model="formula" type="text" placeholder="basic_salary / working_days * paid_days * 0.25" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Allowed variables: basic_salary, salary, sg, step, hazard_rate, working_days, paid_days, and prior variable names.</p>
                        @error('formula') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="text-sm font-medium">Variable Name</label>
                    <input wire:model="variableName" type="text" placeholder="hazard_pay" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('variableName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model="sortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium">
                        <input wire:model="isActive" type="checkbox" class="rounded border-slate-300">
                        Active
                    </label>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                    Save Rule
                </button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Cancel
                    </button>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Rules</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Value</th>
                            <th class="px-4 py-3">Formula</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $item->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->variable_name ?: str($item->name)->snake() }}</div>
                                </td>
                                <td class="px-4 py-3">{{ ucfirst($item->computation_type ?: ($item->is_percentage ? 'percentage' : 'fixed')) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->value, 4) }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $item->formula ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $item->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="edit({{ $item->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this compensation rule?" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No compensation rules yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
