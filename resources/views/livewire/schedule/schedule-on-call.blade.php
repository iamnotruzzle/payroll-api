<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">On Call</h2>
        <p class="text-sm text-slate-600">
            Primary and second on-call pools for {{ $department?->department ?? 'your department' }}.
            Pools are organizational lists; duty OC shifts remain regular schedule assignments when used.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($canManage)
        <form wire:submit="save" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium">Employee</label>
                <select wire:model="emp_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select…</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->emp_id }}">{{ $employee->lastname }}, {{ $employee->firstname }} ({{ $employee->emp_id }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Pool</label>
                <select wire:model="is_second" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="0">Primary on-call</option>
                    <option value="1">Second on-call</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Sort</label>
                <input type="number" wire:model="sort_order" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            @if ($unitOptions->isNotEmpty())
                <div class="md:col-span-2">
                    <label class="text-sm font-medium">Unit</label>
                    <select wire:model="unit_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($unitOptions as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->code }} · {{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <label class="flex items-center gap-2 text-sm md:col-span-2">
                <input type="checkbox" wire:model="is_active"> Active
            </label>
            <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 md:col-span-4">Save</button>
        </form>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ([['Primary on-call', $primary], ['Second on-call', $second]] as [$title, $rows])
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-semibold">{{ $title }}</h3>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Employee</th>
                            <th class="px-3 py-2">Unit</th>
                            <th class="px-3 py-2">Status</th>
                            @if ($canManage)<th class="px-3 py-2"></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $member)
                            @php $emp = $names->get($member->emp_id); @endphp
                            <tr>
                                <td class="px-3 py-2">{{ $emp ? $emp->lastname.', '.$emp->firstname : $member->emp_id }}</td>
                                <td class="px-3 py-2">{{ $member->unit?->code ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $member->is_active ? 'Active' : 'Inactive' }}</td>
                                @if ($canManage)
                                    <td class="px-3 py-2 text-right">
                                        <button wire:click="remove({{ $member->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</section>
