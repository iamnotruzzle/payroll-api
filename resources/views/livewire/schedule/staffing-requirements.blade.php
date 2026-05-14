<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Staffing Requirement Management</h2>
        <p class="text-sm text-slate-600">Set minimum coverage per shift for {{ $department?->department ?? 'your department' }}.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
        <form wire:submit="save" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">{{ $editingId ? 'Edit Requirement' : 'New Requirement' }}</h3>

            <div>
                <label class="text-sm font-medium">Rotation Group</label>
                <select wire:model="rotation_group_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All groups</option>
                    @foreach ($rotationGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Shift</label>
                <select wire:model="shift_code_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select shift</option>
                    @foreach ($shiftCodes as $shiftCode)
                        <option value="{{ $shiftCode->id }}">{{ $shiftCode->code }} - {{ $shiftCode->name }}</option>
                    @endforeach
                </select>
                @error('shift_code_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Day</label>
                <select wire:model="day_of_week" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Every day</option>
                    @foreach ($days as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Minimum Staff</label>
                <input wire:model="minimum_staff" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium">Maximum Staff</label>
                <input wire:model="maximum_staff" type="number" min="1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('maximum_staff') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-sm font-medium">Effective From</label>
                    <input wire:model="effective_from" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Effective To</label>
                    <input wire:model="effective_to" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input wire:model="is_active" type="checkbox"> Active
            </label>

            <div class="flex gap-2">
                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save</button>
                <button wire:click="resetForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
            </div>
        </form>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Requirements</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Group</th>
                            <th class="px-3 py-2">Shift</th>
                            <th class="px-3 py-2">Day</th>
                            <th class="px-3 py-2">Min / Max</th>
                            <th class="px-3 py-2">Effective</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($requirements as $requirement)
                            <tr>
                                <td class="px-3 py-2">{{ $requirement->rotationGroup?->name ?? 'All groups' }}</td>
                                <td class="px-3 py-2 font-medium">{{ $requirement->shiftCode?->code }} - {{ $requirement->shiftCode?->name }}</td>
                                <td class="px-3 py-2">{{ $this->dayName($requirement->day_of_week) }}</td>
                                <td class="px-3 py-2">{{ $requirement->minimum_staff }} / {{ $requirement->maximum_staff ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    {{ $requirement->effective_from?->format('Y-m-d') ?? 'Any' }}
                                    to
                                    {{ $requirement->effective_to?->format('Y-m-d') ?? 'Any' }}
                                </td>
                                <td class="px-3 py-2">{{ $requirement->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="edit({{ $requirement->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">No staffing requirements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold">Weekly Hours Planner</h3>
                <p class="text-sm text-slate-600">Estimated weekly hours from active group staffing requirements. Standard target is 40 hours; 12-hour patterns allow 36-48 hours.</p>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            @forelse ($weeklyPlans as $plan)
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="bg-slate-50 px-4 py-3">
                        <h4 class="font-semibold">{{ $plan['group'] }}</h4>
                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-600">
                            <span>Required: {{ number_format($plan['required_hours'], 2) }}h</span>
                            <span>40h capacity: {{ number_format($plan['capacity_hours'], 2) }}h</span>
                            @if ($plan['capacity_delta'] > 0)
                                <span class="font-semibold text-red-700">Over by {{ number_format($plan['capacity_delta'], 2) }}h</span>
                            @elseif ($plan['capacity_delta'] < 0)
                                <span class="font-semibold text-amber-700">Under by {{ number_format(abs($plan['capacity_delta']), 2) }}h</span>
                            @else
                                <span class="font-semibold text-emerald-700">Balanced</span>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-white text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Employee</th>
                                    <th class="px-3 py-2">Planned Shifts</th>
                                    <th class="px-3 py-2">Hours</th>
                                    <th class="px-3 py-2">Target</th>
                                    <th class="px-3 py-2">Gap</th>
                                    <th class="px-3 py-2">Suggested Shift to Add</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($plan['members'] as $member)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-600">{{ $member['employee_id'] }}</span>
                                                <span class="font-medium">{{ $member['employee_name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($member['assignments'])
                                                <div class="flex max-w-xl flex-wrap gap-1">
                                                    @foreach ($member['assignments'] as $assignment)
                                                        <span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-800">{{ $assignment }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-slate-400">No required shift</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-semibold {{ $member['is_ok'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                            {{ number_format($member['hours'], 2) }}
                                        </td>
                                        <td class="px-3 py-2">{{ $member['target'] }}</td>
                                        <td class="px-3 py-2">{{ number_format($member['gap'], 2) }}</td>
                                        <td class="px-3 py-2">
                                            @if ($member['suggestions'])
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($member['suggestions'] as $suggestion)
                                                        <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">
                                                            {{ $suggestion['code'] }} ({{ number_format($suggestion['hours'], 2) }}h)
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-emerald-700">Enough hours</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-md border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                    Add active staffing requirements for a rotation group to see weekly hour estimates.
                </div>
            @endforelse
        </div>
    </div>
</section>
