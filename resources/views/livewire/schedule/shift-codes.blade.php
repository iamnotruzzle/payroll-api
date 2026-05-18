<section class="space-y-4">
    <div>
        <div>
            <h2 class="text-xl font-semibold">Shift Code Management</h2>
            <p class="text-sm text-slate-600">Manage duty, leave, off, and request-off codes for {{ $department?->department ?? 'your department' }}.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[360px_1fr]">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Shift Code' : 'New Shift Code' }}</h3>

            <div>
                <label class="text-sm font-medium">Code</label>
                <input wire:model="code" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" maxlength="20">
                @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Name</label>
                <input wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Scope</label>
                <select wire:model="scope" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="office">Office - {{ $department?->department ?? 'Current department' }}</option>
                    <option value="global">Global - all departments</option>
                </select>
                @error('scope') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-2 grid-cols-1">
                <div>
                    <label class="text-sm font-medium">Start</label>
                    <input wire:model.live="start_time" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">End</label>
                    <input wire:model.live="end_time" type="time" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">+Day</label>
                    <input wire:model.live="end_day_offset" type="number" min="0" max="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Work Hours</label>
                    <input wire:model.live="work_hours" type="number" min="0" max="72" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('work_hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm">
                <label class="flex items-center gap-2"><input wire:model="is_work_shift" type="checkbox"> Work shift</label>
                <label class="flex items-center gap-2"><input wire:model="is_night_shift" type="checkbox"> Night shift</label>
                <label class="flex items-center gap-2"><input wire:model="is_leave_code" type="checkbox"> Leave code</label>
                <label class="flex items-center gap-2"><input wire:model="is_active" type="checkbox"> Active</label>
            </div>

            <div>
                <label class="text-sm font-medium">Description</label>
                <textarea wire:model="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>

            <div class="flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save</button>
                <button wire:click="resetForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
            </div>
        </form>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 space-y-3">
                <h3 class="font-semibold">Codes</h3>
                <div class="grid gap-2 md:grid-cols-[minmax(180px,1fr)_150px_150px_150px]">
                    <input wire:model.live.debounce.250ms="search" placeholder="Search code or name" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <select wire:model.live="scopeFilter" class="rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="office">Office scoped</option>
                        <option value="global">Global scoped</option>
                        <option value="all">All scopes</option>
                    </select>
                    <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All statuses</option>
                    </select>
                    <select wire:model.live="typeFilter" class="rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="all">All types</option>
                        <option value="work">Work shifts</option>
                        <option value="non_work">Non-work codes</option>
                        <option value="night">Night shifts</option>
                        <option value="leave">Leave codes</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Hours</th>
                            <th class="px-3 py-2">Scope</th>
                            <th class="px-3 py-2">Flags</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($shiftCodes as $shift)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $shift->code }}</td>
                                <td class="px-3 py-2">{{ $shift->name }}</td>
                                <td class="px-3 py-2">
                                    {{ $shift->start_time ? \Carbon\CarbonImmutable::createFromFormat('H:i:s', $shift->start_time)->format('h:i A') : '-' }}
                                    -
                                    {{ $shift->end_time ? \Carbon\CarbonImmutable::createFromFormat('H:i:s', $shift->end_time)->format('h:i A') : '-' }}
                                </td>
                                <td class="px-3 py-2">{{ $shift->work_hours !== null ? number_format((float) $shift->work_hours, 2) : '-' }}</td>
                                <td class="px-3 py-2">{{ $shift->department_id ? 'Office' : 'Global' }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($shift->is_work_shift)<span class="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">work</span>@endif
                                        @if ($shift->is_night_shift)<span class="rounded bg-indigo-50 px-2 py-1 text-xs text-indigo-700">night</span>@endif
                                        @if ($shift->is_leave_code)<span class="rounded bg-amber-50 px-2 py-1 text-xs text-amber-700">leave</span>@endif
                                        @unless ($shift->is_active)<span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">inactive</span>@endunless
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="edit({{ $shift->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No shift codes yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
