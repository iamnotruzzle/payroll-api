<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Duty Census</h2>
        <p class="text-sm text-slate-600">
            Headcount by day × work shift for {{ $department?->department ?? 'your department' }}.
            Counts come from the current monthly schedule assignments (work shifts only).
        </p>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Month</span>
                <select wire:model.live="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Year</span>
                <select wire:model.live="year" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($yearOptions as $yearOption)
                        <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                    @endforeach
                </select>
            </label>
            @if ($unitOptions->isNotEmpty())
                <label>
                    <span class="text-xs font-semibold uppercase text-slate-500">Unit</span>
                    <select wire:model.live="unit_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All units</option>
                        @foreach ($unitOptions as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <div class="flex items-end">
                <p class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    Roster: {{ $schedule?->status ? strtoupper($schedule->status) : 'none' }}
                </p>
            </div>
        </div>
    </section>

    <section class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="sticky left-0 bg-slate-50 px-3 py-3 text-left">Day</th>
                    @foreach ($shiftCodes as $shift)
                        <th class="px-3 py-3 text-center">{{ $shift->code }}</th>
                    @endforeach
                    <th class="px-3 py-3 text-center">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($days as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $row = $counts[$dateKey] ?? [];
                        $total = array_sum($row);
                    @endphp
                    <tr>
                        <td class="sticky left-0 bg-white px-3 py-2 font-medium whitespace-nowrap">
                            {{ $day->format('j') }}
                            <span class="ml-1 text-xs font-normal text-slate-500">{{ $day->format('D') }}</span>
                        </td>
                        @foreach ($shiftCodes as $shift)
                            <td class="px-3 py-2 text-center tabular-nums {{ ($row[$shift->code] ?? 0) === 0 ? 'text-slate-300' : '' }}">
                                {{ $row[$shift->code] ?? 0 }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-center font-semibold tabular-nums">{{ $total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $shiftCodes->count() + 2 }}" class="px-3 py-8 text-center text-slate-500">No days.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</section>
