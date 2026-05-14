<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Rotation Group Management</h2>
        <p class="text-sm text-slate-600">Create ordered employee groups for rotating schedule templates.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[380px_1fr]">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Group' : 'New Group' }}</h3>

            <div>
                <label class="text-sm font-medium">Name</label>
                <input wire:model="name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Description</label>
                <textarea wire:model="description" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input wire:model="is_active" type="checkbox"> Active
            </label>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium">Members</label>
                    <button wire:click="addMember" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add</button>
                </div>

                @foreach ($members as $index => $member)
                    <div class="flex gap-2">
                        <select wire:model="members.{{ $index }}" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select employee</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->emp_id }}">{{ $this->employeeName($employee) }}</option>
                            @endforeach
                        </select>
                        <button wire:click="removeMember({{ $index }})" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Remove</button>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save</button>
                <button wire:click="resetForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
            </div>
        </form>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Groups</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Members</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($groups as $group)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $group->name }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $group->members->count() }}</td>
                                <td class="px-3 py-2">{{ $group->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="edit({{ $group->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No rotation groups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
