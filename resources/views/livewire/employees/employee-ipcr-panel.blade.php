<div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">IPCR</h3>
            <p class="text-sm text-slate-600">Performance commitment sheets linked to this employee.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['performance.view', 'performance.manage', 'performance.approve'])
                <a href="{{ route('performance.periods') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    IPCR periods
                </a>
            @endcanany
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Sheets <span class="font-normal text-slate-500">({{ $sheets->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Period</th>
                        <th class="px-4 py-2 font-semibold">Type</th>
                        <th class="px-4 py-2 font-semibold">Accomplishment</th>
                        <th class="px-4 py-2 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sheets as $sheet)
                        @php
                            $period = $sheet->mfoSet?->period;
                            $periodId = $period?->id ?? $sheet->mfoSet?->period_id;
                        @endphp
                        <tr wire:key="hub-ipcr-{{ $sheet->id }}">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $period?->label ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $sheet->ipcrType?->type ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">
                                {{ optional($sheet->accomplishment_date)->format('Y-m-d') ?: '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if ($periodId)
                                    <a href="{{ route('performance.employee', ['empId' => $empId, 'periodId' => $periodId]) }}"
                                       class="text-sm font-medium text-[#696cff] hover:underline">Open</a>
                                    <a href="{{ route('performance.print', ['empId' => $empId, 'periodId' => $periodId]) }}" target="_blank"
                                       class="ml-2 text-sm font-medium text-slate-600 hover:underline">Print</a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No IPCR sheets for this employee.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
