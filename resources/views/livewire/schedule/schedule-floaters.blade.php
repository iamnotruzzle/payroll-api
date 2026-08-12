<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Floaters</h2>
        <p class="text-sm text-slate-600">
            Permanent floater pool and monthly temporary floaters for
            {{ $department?->department ?? 'your department' }}.
            Mark individual assignment days as temporary floater on the Schedule Dashboard.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Permanent pool</h3>
            @if ($canManage)
                <form wire:submit="addToPool" class="grid gap-2 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Employee</label>
                        <select wire:model="emp_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select…</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->emp_id }}">{{ $employee->lastname }}, {{ $employee->firstname }} ({{ $employee->emp_id }})</option>
                            @endforeach
                        </select>
                        @error('emp_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if ($unitOptions->isNotEmpty())
                        <div>
                            <label class="text-sm font-medium">Home unit</label>
                            <select wire:model="unit_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">—</option>
                                @foreach ($unitOptions as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }} · {{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="text-sm font-medium">Sort</label>
                        <input type="number" wire:model="sort_order" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" min="0">
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" wire:model="is_active"> Active
                    </label>
                    <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 sm:col-span-2">Save to pool</button>
                </form>
            @endif

            <div class="overflow-x-auto">
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
                        @forelse ($pool as $member)
                            @php $emp = $names->get($member->emp_id); @endphp
                            <tr>
                                <td class="px-3 py-2">{{ $emp ? $emp->lastname.', '.$emp->firstname : $member->emp_id }}</td>
                                <td class="px-3 py-2">{{ $member->unit?->code ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $member->is_active ? 'Active' : 'Inactive' }}</td>
                                @if ($canManage)
                                    <td class="px-3 py-2 text-right">
                                        <button wire:click="removeFromPool({{ $member->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No floater pool members yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <h3 class="font-semibold">Monthly floaters</h3>
                <select wire:model.live="month" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \Carbon\CarbonImmutable::createFromDate(2000, $m, 1)->format('M') }}</option>
                    @endforeach
                </select>
                <input type="number" wire:model.live="monthYear" class="w-24 rounded-md border border-slate-300 px-2 py-1 text-sm">
            </div>

            @if ($canManage)
                <form wire:submit="addMonthly" class="grid gap-2 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Employee</label>
                        <select wire:model="monthly_emp_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select…</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->emp_id }}">{{ $employee->lastname }}, {{ $employee->firstname }} ({{ $employee->emp_id }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($unitOptions->isNotEmpty())
                        <div class="sm:col-span-2">
                            <label class="text-sm font-medium">Host unit</label>
                            <select wire:model="monthly_unit_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">—</option>
                                @foreach ($unitOptions as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }} · {{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 sm:col-span-2">Add monthly floater</button>
                </form>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Employee</th>
                            <th class="px-3 py-2">Unit</th>
                            @if ($canManage)<th class="px-3 py-2"></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($monthly as $member)
                            @php $emp = $names->get($member->emp_id); @endphp
                            <tr>
                                <td class="px-3 py-2">{{ $emp ? $emp->lastname.', '.$emp->firstname : $member->emp_id }}</td>
                                <td class="px-3 py-2">{{ $member->unit?->code ?: '—' }}</td>
                                @if ($canManage)
                                    <td class="px-3 py-2 text-right">
                                        <button wire:click="removeMonthly({{ $member->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No monthly floaters for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
