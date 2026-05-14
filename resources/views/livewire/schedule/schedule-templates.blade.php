<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Schedule Template Management</h2>
        <p class="text-sm text-slate-600">Build repeating shift patterns for {{ $department?->department ?? 'your department' }}.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[420px_1fr]">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Template' : 'New Template' }}</h3>

            <div>
                <label class="text-sm font-medium">Name</label>
                <input wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Rotation Group</label>
                <select wire:model="rotation_group_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No group</option>
                    @foreach ($rotationGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input wire:model="is_active" type="checkbox"> Active
            </label>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">Pattern Days</label>
                    <button wire:click="addDay" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add Day</button>
                </div>

                @foreach ($days as $index => $day)
                    <div class="flex items-center gap-2">
                        <span class="w-14 text-sm text-slate-600">Day {{ $index + 1 }}</span>
                        <select wire:model="days.{{ $index }}" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select shift</option>
                            @foreach ($shiftCodes as $shiftCode)
                                <option value="{{ $shiftCode->id }}">{{ $shiftCode->code }} - {{ $shiftCode->name }}</option>
                            @endforeach
                        </select>
                        <button wire:click="removeDay({{ $index }})" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Remove</button>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save</button>
                <button wire:click="resetForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
            </div>
        </form>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Templates</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Group</th>
                            <th class="px-3 py-2">Pattern</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($templates as $template)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $template->name }}</td>
                                <td class="px-3 py-2">{{ $template->rotationGroup?->name ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex max-w-xl flex-wrap gap-1">
                                        @foreach ($template->days as $day)
                                            <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ $day->shiftCode?->code }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-2">{{ $template->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="edit({{ $template->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No schedule templates yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
