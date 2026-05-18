<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Holiday Management</h2>
        <p class="text-sm text-slate-600">Manage paid holidays used by DTR, MRA, and schedule calendar labels.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[380px_1fr]">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Holiday' : 'New Holiday' }}</h3>

            <div>
                <label class="text-sm font-medium">Date</label>
                <input wire:model="holiday_date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('holiday_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Name</label>
                <input wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-sm font-medium">Type</label>
                    <select wire:model="holiday_type" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="REGULAR">Regular</option>
                        <option value="SPECIAL">Special</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Scope</label>
                    <select wire:model="holiday_scope" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="FULL_DAY">Full day</option>
                        <option value="FIRST_HALF">First half</option>
                        <option value="SECOND_HALF">Second half</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium">Label Code</label>
                <input wire:model="label_code" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('label_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm">
                <label class="flex items-center gap-2"><input wire:model="is_paid" type="checkbox"> Paid</label>
                <label class="flex items-center gap-2"><input wire:model="is_active" type="checkbox"> Active</label>
            </div>

            <div class="flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save</button>
                <button wire:click="resetForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
            </div>
        </form>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 space-y-3">
                <h3 class="font-semibold">Holidays</h3>
                <div class="grid gap-2 md:grid-cols-[minmax(180px,1fr)_120px_150px]">
                    <input wire:model.live.debounce.250ms="search" placeholder="Search holiday, label, or type" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <input wire:model.live.debounce.250ms="yearFilter" type="number" min="1900" max="2200" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All statuses</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Scope</th>
                            <th class="px-3 py-2">Label</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $holiday->holiday_date?->format('Y-m-d') }}</td>
                                <td class="px-3 py-2">{{ $holiday->name }}</td>
                                <td class="px-3 py-2">{{ $holiday->holiday_type }}</td>
                                <td class="px-3 py-2">{{ str_replace('_', ' ', $holiday->holiday_scope) }}</td>
                                <td class="px-3 py-2">{{ $holiday->label_code }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($holiday->is_paid)<span class="rounded bg-emerald-50 px-2 py-1 text-xs text-emerald-700">paid</span>@endif
                                        @if ($holiday->is_active)
                                            <span class="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">active</span>
                                        @else
                                            <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">inactive</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="edit({{ $holiday->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No holidays found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
