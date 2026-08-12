<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Training / TARF</h3>
            <p class="text-sm text-slate-600">Recent training authority request forms for this employee.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['training.view', 'training.manage', 'training.approve'])
                <a href="{{ route('training.requests') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Training requests
                </a>
            @endcanany
            @can('self-service.training')
                <a href="{{ route('self-service.training') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    My Training
                </a>
            @endcan
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">TARF history <span class="font-normal text-slate-500">({{ $requests->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">TARF</th>
                        <th class="px-4 py-2 font-semibold">Training</th>
                        <th class="px-4 py-2 font-semibold">Dates</th>
                        <th class="px-4 py-2 font-semibold">Status</th>
                        <th class="px-4 py-2 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $req)
                        @php
                            $detail = $req->trainingDetail;
                            $statusId = (int) ($detail?->status ?? -1);
                            $statusKey = \App\Support\Hris\TarfStatuses::keyFor($statusId);
                            $statusName = \App\Support\Hris\TarfStatuses::nameFor($statusId);
                            $pendingPetu = $statusId === \App\Support\Hris\TarfStatuses::PENDING_PETU;
                            $pendingMcc = $statusId === \App\Support\Hris\TarfStatuses::PENDING_MCC;
                        @endphp
                        <tr wire:key="hub-tarf-{{ $req->id }}">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $req->tarf_no }}</td>
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-slate-800">{{ $detail?->training_name ?: '—' }}</p>
                                <p class="text-xs text-slate-500">{{ $detail?->training_venue ?: '' }}</p>
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">
                                {{ optional($detail?->start_date)->format('Y-m-d') ?: '—' }}
                                <span class="text-slate-400">→</span>
                                {{ optional($detail?->end_date)->format('Y-m-d') ?: '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if (in_array($statusKey, ['approved', 'completed'], true))
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $statusName }}</span>
                                @elseif ($statusKey === 'pending')
                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ $statusName }}</span>
                                @elseif ($statusKey === 'disapproved')
                                    <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $statusName }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $statusName }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap space-x-1">
                                <a href="{{ route('training.show', $req->tarf_no) }}"
                                   class="inline-block rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50">View</a>
                                @if ($canApprove && $pendingPetu)
                                    <button type="button" wire:click="approvePetu('{{ $req->tarf_no }}')"
                                            class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-500">PETU approve</button>
                                    <button type="button" wire:click="disapprovePetu('{{ $req->tarf_no }}')" wire:confirm="Disapprove this TARF as PETU?"
                                            class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">PETU deny</button>
                                @endif
                                @if ($canApprove && $pendingMcc)
                                    <button type="button" wire:click="approveMcc('{{ $req->tarf_no }}')"
                                            class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-500">MCC approve</button>
                                    <button type="button" wire:click="disapproveMcc('{{ $req->tarf_no }}')" wire:confirm="Disapprove this TARF as MCC?"
                                            class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">MCC deny</button>
                                @endif
                                @if ($canCancel && ($pendingPetu || $pendingMcc))
                                    <button type="button" wire:click="cancel('{{ $req->tarf_no }}')" wire:confirm="Cancel this TARF?"
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50">Cancel</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No TARF requests for this employee.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
