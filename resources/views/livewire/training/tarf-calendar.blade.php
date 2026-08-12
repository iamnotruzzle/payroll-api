<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Training calendar</h2>
            <p class="text-sm text-slate-600">Month list of LDIs overlapping the selected month (full interactive calendar deferred).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="previousMonth" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Prev</button>
            <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold">{{ $monthLabel }}</span>
            <button wire:click="nextMonth" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Next</button>
            <a href="{{ route('training.requests') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Requests</a>
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Dates</th>
                    <th class="px-4 py-3 text-left">TARF</th>
                    <th class="px-4 py-3 text-left">Training</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <tr wire:key="cal-{{ $item->tarf_no }}">
                        <td class="px-4 py-3 text-slate-700">
                            {{ optional($item->start_date)->format('Y-m-d') }}
                            <span class="text-slate-400">→</span>
                            {{ optional($item->end_date)->format('Y-m-d') }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $item->tarf_no }}</td>
                        <td class="px-4 py-3">
                            <p class="text-slate-800">{{ $item->training_name }}</p>
                            <p class="text-xs text-slate-500">{{ $item->training_venue ?: '—' }} · {{ $item->requests->count() }} pax</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                {{ $statusLabels[(int) $item->status] ?? 'Status '.$item->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('training.show', $item->tarf_no) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No LDIs in this month.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
