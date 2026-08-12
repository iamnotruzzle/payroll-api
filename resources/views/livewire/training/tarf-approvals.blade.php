<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">TARF Approvals</h2>
            <p class="text-sm text-slate-600">PETU then MCC approval queue (legacy status codes).</p>
        </div>
        <a href="{{ route('training.requests') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">All requests</a>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <input wire:model.lazy="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search TARF or employee">
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Request</th>
                    <th class="px-4 py-3 text-left">Queue</th>
                    <th class="px-4 py-3 text-left">Notes</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tarfs as $tarf)
                    @php $pendingPetu = (int) $tarf->status === \App\Support\Hris\TarfStatuses::PENDING_PETU; @endphp
                    <tr wire:key="approve-{{ $tarf->tarf_no }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $tarf->tarf_no }}</p>
                            <p class="text-slate-700">{{ $tarf->training_name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ optional($tarf->start_date)->format('Y-m-d') }} → {{ optional($tarf->end_date)->format('Y-m-d') }}
                                · {{ $tarf->requests->count() }} participant(s)
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                {{ \App\Support\Hris\TarfStatuses::nameFor((int) $tarf->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <input wire:model="notes.{{ $tarf->tarf_no }}" type="text" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Approval notes">
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('training.show', $tarf->tarf_no) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Open</a>
                            @if ($canApprove)
                                @if ($pendingPetu)
                                    <button wire:click="approvePetu('{{ $tarf->tarf_no }}')" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">PETU Approve</button>
                                    <button wire:click="disapprovePetu('{{ $tarf->tarf_no }}')" wire:confirm="Disapprove this TARF?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">Disapprove</button>
                                @else
                                    <div class="mb-2 space-y-1 text-left">
                                        @foreach ($tarf->requests as $request)
                                            <label class="flex items-center justify-end gap-2 text-xs text-slate-600">
                                                <span class="truncate">{{ $request->employee?->lastname ?: $request->emp_id }}</span>
                                                <select wire:model="obOt.{{ $tarf->tarf_no }}.{{ $request->emp_id }}" class="rounded border border-slate-300 px-2 py-1 text-xs">
                                                    <option value="0">Personal</option>
                                                    <option value="1">OB</option>
                                                    <option value="2">OT</option>
                                                    <option value="3">Disapprove pax</option>
                                                </select>
                                            </label>
                                        @endforeach
                                        <label class="mt-1 flex items-center justify-end gap-2 text-xs text-slate-600">
                                            <input wire:model="approveAsOt" type="checkbox" class="rounded border-slate-300">
                                            Approve as OT status
                                        </label>
                                    </div>
                                    <button wire:click="approveMcc('{{ $tarf->tarf_no }}')" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">MCC Approve</button>
                                    <button wire:click="disapproveMcc('{{ $tarf->tarf_no }}')" wire:confirm="Disapprove this TARF?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">Disapprove</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No TARFs awaiting approval.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-100 px-4 py-3">{{ $tarfs->links() }}</div>
    </section>
</div>
