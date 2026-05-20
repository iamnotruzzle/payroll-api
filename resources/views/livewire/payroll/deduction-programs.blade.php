<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Deduction Programs</h2>
        <p class="text-sm text-slate-600">Manage reusable deductions such as Death Aid, cooperative dues, association dues, and similar payroll programs.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
        <form wire:submit.prevent="save" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Program' : 'New Program' }}</h3>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model.live="name" type="text" placeholder="Program name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Computation</label>
                    <select wire:model.live="computationType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percentage">Percentage of basic salary</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Value</label>
                    <input wire:model.live="value" type="number" step="0.0001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">For percentage, use 25 or 0.25 for 25%.</p>
                    @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model.live="sortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm font-medium">
                        <input wire:model.live="isActive" type="checkbox" class="rounded border-slate-300">
                        Active
                    </label>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                    {{ $editingId ? 'Update Program' : 'Save Program' }}
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
                <h3 class="font-semibold">Programs</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Value</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $item)
                            @if ($editingId === $item->id)
                                <tr wire:key="deduction-program-management-editing-{{ $item->id }}" class="bg-blue-50/70">
                                    <td class="px-4 py-3 align-top">
                                        <input wire:model.live="name" type="text" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                                            <span>Sort</span>
                                            <input wire:model.live="sortOrder" type="number" min="0" class="w-20 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs">
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <select wire:model.live="computationType" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                                            <option value="fixed">Fixed amount</option>
                                            <option value="percentage">Percentage</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
                                        <input wire:model.live="value" type="number" step="0.0001" min="0" class="w-28 rounded-md border border-slate-300 bg-white px-2 py-1.5 text-right text-sm">
                                        @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium">
                                            <input wire:model.live="isActive" type="checkbox" class="rounded border-slate-300">
                                            Active
                                        </label>
                                    </td>
                                    <td class="px-4 py-3 text-right align-top">
                                        <button type="button" wire:click="save" class="rounded-md bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-600">Save</button>
                                        <button type="button" wire:click="resetForm" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Cancel</button>
                                    </td>
                                </tr>
                            @else
                                <tr wire:key="deduction-program-management-{{ $item->id }}" class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $item->name }}</div>
                                        <div class="text-xs text-slate-500">Sort {{ (int) ($item->sort_order ?? 0) }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $item->is_percentage ? 'Percentage' : 'Fixed amount' }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $item->value, 4) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $item->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" wire:click="edit({{ $item->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Edit</button>
                                        <button type="button" wire:click="delete({{ $item->id }})" wire:confirm="Delete this deduction program?" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">No deduction programs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
