<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Schedule {{ $unitNounPlural ?? 'Units' }}</h2>
        <p class="text-sm text-slate-600">
            @if (!empty($isCno))
                Manage wards, clinics, and nursing areas under
                {{ $department?->department ?? 'your department' }}.
                Assign handled units to limit which schedulers can work each unit.
            @else
                Manage multiple areas under
                {{ $department?->department ?? 'your office/department' }}.
                Assign handled areas to limit which schedulers can work each area.
            @endif
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
        @if ($canManage)
            <form wire:submit="save" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold">{{ $editingId ? 'Edit '.$unitNoun : 'New '.$unitNoun }}</h3>

                <div>
                    <label class="text-sm font-medium">Code</label>
                    <input wire:model="code" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase" maxlength="40">
                    @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-sm font-medium">Type</label>
                        <select wire:model="unit_type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($unitTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input wire:model="sort_order" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="is_active" type="checkbox"> Active
                </label>

                <div>
                    <label class="text-sm font-medium">Description</label>
                    <textarea wire:model="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>

                <div class="flex gap-2">
                    <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save</button>
                    <button wire:click="resetForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
                </div>
            </form>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">{{ $unitNounPlural ?? 'Units' }}</h3>
                <input wire:model.lazy="search" placeholder="Search code or name" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Status</th>
                            @if ($canManage)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($units as $unit)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $unit->code }}</td>
                                <td class="px-3 py-2">{{ $unit->name }}</td>
                                <td class="px-3 py-2">{{ $unit->typeLabel() }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-0.5 text-xs {{ $unit->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $unit->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                @if ($canManage)
                                    <td class="px-3 py-2 text-right">
                                        <button wire:click="edit({{ $unit->id }})" type="button" class="text-sm font-medium text-blue-700 hover:underline">Edit</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManage ? 5 : 4 }}" class="px-3 py-6 text-center text-slate-500">No units yet. Create wards/sections when this department uses units.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3">
            <h3 class="font-semibold">Handled units (scheduler scope)</h3>
            <p class="mt-1 text-sm text-slate-600">
                Leave a scheduler unchecked for all units to keep full department access.
                Selecting one or more units limits their dashboard/assignment view to those units.
            </p>
        </div>

        @if ($units->isEmpty())
            <p class="text-sm text-slate-500">Create units before assigning handled scope.</p>
        @elseif ($schedulers->isEmpty())
            <p class="text-sm text-slate-500">No department users with schedule permissions were found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Scheduler</th>
                            @foreach ($units as $unit)
                                <th class="px-3 py-2 text-center">{{ $unit->code }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($schedulers as $scheduler)
                            <tr>
                                <td class="px-3 py-2 font-medium whitespace-nowrap">
                                    {{ $scheduler->lastname }}, {{ $scheduler->firstname }}
                                    <span class="block text-xs font-normal text-slate-500">{{ $scheduler->emp_id }}</span>
                                </td>
                                @foreach ($units as $unit)
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            value="{{ $unit->id }}"
                                            wire:model="handlerUnits.{{ $scheduler->emp_id }}"
                                            @disabled(! $canManage)
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canManage)
                <div class="mt-4">
                    <button wire:click="saveHandlers" type="button" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                        Save handled units
                    </button>
                </div>
            @endif
        @endif
    </section>
</section>
