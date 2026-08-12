<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My IPCR</h2>
            <p class="text-sm text-slate-600">Your performance commitments and ratings for available periods.</p>
        </div>
        @if ($selectedPeriod)
            <a href="{{ route('performance.print', [$empId, $selectedPeriod->id]) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
        @endif
    </div>

    @if ($periods->isEmpty())
        <div class="rounded-md border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500 shadow-sm">
            No IPCR targets are assigned to you yet.
        </div>
    @else
        <section class="flex flex-wrap gap-2">
            @foreach ($periods as $period)
                <button
                    wire:click="selectPeriod({{ $period->id }})"
                    type="button"
                    class="rounded-md border px-3 py-2 text-sm font-medium {{ (int) $selectedPeriodId === (int) $period->id ? 'border-[#696cff] bg-[#696cff] text-white' : 'border-slate-300 bg-white hover:bg-slate-50' }}"
                >
                    {{ $period->label }}
                </button>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Function</th>
                        <th class="px-4 py-3 text-left">Target</th>
                        <th class="px-4 py-3 text-left">Accomplishment</th>
                        <th class="px-4 py-3 text-left">Ratings</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($targets as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->mfoSet?->mfo?->functionType?->function_type ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row->mfoSet?->mfo?->mfo, 100) }}</p>
                                <p class="font-medium">{{ $row->target }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $row->accomplishment ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @forelse ($row->ratings as $rating)
                                    <div class="text-xs">Q{{ $rating->quality }}/E{{ $rating->effectiveness }}/T{{ $rating->timeliness }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No targets in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endif
</div>
