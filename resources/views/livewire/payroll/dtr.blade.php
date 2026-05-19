<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Daily Time Record</h2>
            <p class="text-sm text-slate-600">
                Review DTR punches and encode labels, tardiness, and undertime for {{ $department?->department ?? 'your department' }}.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="loadState" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Load</button>
            <button wire:click="save" wire:loading.attr="disabled" @disabled($isLocked)
                class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:opacity-50">
                Save DTR
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($isLocked)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            This period already has an MRA report. DTR changes are locked for this exact range.
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-4">
            <div>
                <label class="text-sm font-medium">Month</label>
                <select wire:model.live="monthFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('monthFilter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Year</label>
                <select wire:model.live="yearFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($yearOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                @error('yearFilter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Employee Type</label>
                <select wire:model.live="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($employeeTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <div class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ count($employees) }} employee(s), {{ count($dates) }} day(s)
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[1280px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="sticky left-0 z-10 bg-slate-50 px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">AM In</th>
                        <th class="px-4 py-3">AM Out</th>
                        <th class="px-4 py-3">PM In</th>
                        <th class="px-4 py-3">PM Out</th>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3">Late</th>
                        <th class="px-4 py-3">UT</th>
                        <th class="px-4 py-3">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $employee)
                        @foreach ($dates as $date)
                            @php($key = $this->key($employee->emp_id, $date))
                            @php($dtr = $dtrs->get($key))
                            <tr class="hover:bg-slate-50">
                                <td class="sticky left-0 z-10 bg-white px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $employee->lastname }}, {{ $employee->firstname }}</div>
                                    <div class="text-xs text-slate-500">{{ $employee->emp_id }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="font-medium">{{ \Carbon\CarbonImmutable::parse($date)->format('M d') }}</div>
                                    <div class="text-xs text-slate-500">{{ \Carbon\CarbonImmutable::parse($date)->format('D') }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr?->timein_am ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr?->timeout_am ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr?->timein_pm ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $dtr?->timeout_pm ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    <select wire:model="entries.{{ $key }}.label" @disabled($isLocked) class="w-44 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                        <option value="">None</option>
                                        @foreach ($labelOptions as $option)
                                            <option value="{{ $option->code }}">{{ $option->code }} - {{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input wire:model="entries.{{ $key }}.tardiness" @disabled($isLocked) type="number" min="0" class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                </td>
                                <td class="px-4 py-3">
                                    <input wire:model="entries.{{ $key }}.undertime" @disabled($isLocked) type="number" min="0" class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                </td>
                                <td class="px-4 py-3">
                                    <input wire:model="entries.{{ $key }}.adjustment_remarks" @disabled($isLocked) type="text" class="w-64 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-slate-500">No active employees found for this department.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
