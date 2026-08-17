<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Adjustment Types</h2>
            <p class="text-sm text-slate-600">Manage payroll adjustment columns used in Payroll Generation.</p>
        </div>
        <button
            type="button"
            x-on:click="erpOverlay.open($wire, 'adjustment-type', { editingId: null, name: '', isActive: true, sortOrder: 0 })"
            class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]"
        >
            New Adjustment Type
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-right">Sort</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($types as $type)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $type->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $type->code }}</td>
                        <td class="px-4 py-3 text-right">{{ $type->sort_order }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $type->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                x-on:click="erpOverlay.open($wire, 'adjustment-type', { editingId: {{ $type->id }}, name: @js($type->name), isActive: @js((bool) $type->is_active), sortOrder: {{ (int) $type->sort_order }} }, true)"
                                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
                            >Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No adjustment types yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <x-setup-form-modal name="adjustment-type" title="New Adjustment Type" edit-title="Edit Adjustment Type" size="sm">
        <form wire:submit="save" class="space-y-4">
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Name</span>
                <input wire:model="name" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Differential">
                @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Sort</span>
                    <input wire:model="sortOrder" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('sortOrder') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="inline-flex min-h-[2.25rem] items-end gap-2 pb-2 text-sm font-medium text-slate-700">
                    <input wire:model="isActive" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                    <span>Active</span>
                </label>
            </div>
            <div class="flex justify-end gap-2 border-t pt-4">
                <button x-on:click="erpOverlay.close('adjustment-type')" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]" x-text="editing ? 'Update' : 'Save'">Save</button>
            </div>
        </form>
    </x-setup-form-modal>
</div>
