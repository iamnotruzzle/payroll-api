<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">DTR</h3>
            <p class="text-sm text-slate-600">Punches since {{ $from }} (latest {{ $rows->count() }} days with records).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('payroll.view')
                <a href="{{ route('payroll.dtr-encoding') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    DTR encoding
                </a>
            @endcan
            @canany(['self-service.dtr', 'self-service.access'])
                <a href="{{ route('self-service.dtr') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Self-service DTR
                </a>
            @endcanany
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Date</th>
                        <th class="px-4 py-2 font-semibold">AM In</th>
                        <th class="px-4 py-2 font-semibold">AM Out</th>
                        <th class="px-4 py-2 font-semibold">PM In</th>
                        <th class="px-4 py-2 font-semibold">PM Out</th>
                        @if ($canManage)
                            <th class="px-4 py-2 font-semibold text-right">Save</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr wire:key="hub-dtr-{{ $row->dtr_id }}">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ optional($row->dtr_date)->format('Y-m-d') ?: '—' }}</td>
                            @if ($canManage)
                                <td class="px-2 py-2"><input wire:model.lazy="punches.{{ $row->dtr_id }}.timein_am" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm" placeholder="—"></td>
                                <td class="px-2 py-2"><input wire:model.lazy="punches.{{ $row->dtr_id }}.timeout_am" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm" placeholder="—"></td>
                                <td class="px-2 py-2"><input wire:model.lazy="punches.{{ $row->dtr_id }}.timein_pm" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm" placeholder="—"></td>
                                <td class="px-2 py-2"><input wire:model.lazy="punches.{{ $row->dtr_id }}.timeout_pm" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm" placeholder="—"></td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button" wire:click="savePunch({{ $row->dtr_id }})"
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50">Save</button>
                                </td>
                            @else
                                <td class="px-4 py-2.5 text-slate-700">{{ $row->timein_am ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $row->timeout_am ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $row->timein_pm ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-700">{{ $row->timeout_pm ?: '—' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-8 text-center text-sm text-slate-500">No DTR punches in the last 30 days.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
