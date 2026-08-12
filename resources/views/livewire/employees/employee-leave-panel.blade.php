<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Leave</h3>
            <p class="text-sm text-slate-600">Recent applications and credit activity for this employee.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['leave.view', 'leave.request', 'leave.approve'])
                <a href="{{ route('leave.requests', ['search' => $empId]) }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Leave requests
                </a>
            @endcanany
            @canany(['leave.view', 'leave.credits'])
                <a href="{{ route('leave.card', ['empId' => $empId]) }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Leave card
                </a>
            @endcanany
            @can('leave.credits')
                <a href="{{ route('leave.credits') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Leave credits
                </a>
            @endcan
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">VL balance</p>
            <p class="mt-1 text-xl font-semibold">{{ number_format((float) ($employee->vacation_leave_credits ?? 0), 3) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">SL balance</p>
            <p class="mt-1 text-xl font-semibold">{{ number_format((float) ($employee->sick_leave_credits ?? 0), 3) }}</p>
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Recent requests <span class="font-normal text-slate-500">({{ $leaves->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Type</th>
                        <th class="px-4 py-2 font-semibold">Dates</th>
                        <th class="px-4 py-2 font-semibold">Days</th>
                        <th class="px-4 py-2 font-semibold">Status</th>
                        <th class="px-4 py-2 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leaves as $leave)
                        @php
                            $statusKey = \App\Support\Hris\LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null);
                            $statusName = $leave->status_name ?: \App\Support\Hris\LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null);
                            $pending = $leaveService->isPending($leave);
                        @endphp
                        <tr wire:key="hub-leave-{{ $leave->leave_id }}">
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-slate-800">{{ $leave->leave_type_name ?: '—' }}</p>
                                <p class="text-xs text-slate-500">Filed {{ optional($leave->filing_date)->format('Y-m-d') ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">
                                {{ optional($leave->start_date)->format('Y-m-d') ?: '—' }}
                                <span class="text-slate-400">→</span>
                                {{ optional($leave->end_date)->format('Y-m-d') ?: '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">
                                {{ number_format((float) $leave->days_wpay, 2) }} WP /
                                {{ number_format((float) $leave->days_wopay, 2) }} WOP
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($statusKey === 'approved')
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $statusName }}</span>
                                @elseif ($statusKey === 'pending')
                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ $statusName }}</span>
                                @elseif ($statusKey === 'disapproved')
                                    <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $statusName }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $statusName }}</span>
                                @endif
                                @if ($pending && ($canApprove || $canCancel))
                                    <input wire:model.lazy="remarks.{{ $leave->leave_id }}" type="text" placeholder="Optional remarks"
                                           class="mt-2 w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap space-x-1">
                                <a href="{{ route('leave.requests.print', $leave->leave_id) }}" target="_blank"
                                   class="inline-block rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50">Print</a>
                                @if ($pending && $canApprove)
                                    <button type="button" wire:click="approve({{ $leave->leave_id }})"
                                            class="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-500">Approve</button>
                                    <button type="button" wire:click="disapprove({{ $leave->leave_id }})" wire:confirm="Disapprove this leave request?"
                                            class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Disapprove</button>
                                @endif
                                @if ($pending && $canCancel)
                                    <button type="button" wire:click="cancel({{ $leave->leave_id }})" wire:confirm="Cancel this leave request?"
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50">Cancel</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No leave filed.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Credit ledger <span class="font-normal text-slate-500">({{ $ledgerRows->count() }})</span></h4>
            <p class="text-xs text-slate-500">Additive VL/SL trail (parallel to leave logs). Balances still live on the employee record.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Date</th>
                        <th class="px-4 py-2 font-semibold">Bucket</th>
                        <th class="px-4 py-2 font-semibold">Delta</th>
                        <th class="px-4 py-2 font-semibold">Balance after</th>
                        <th class="px-4 py-2 font-semibold">Source</th>
                        <th class="px-4 py-2 font-semibold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($ledgerRows as $row)
                        <tr wire:key="hub-leave-ledger-{{ $row->id }}">
                            <td class="px-4 py-2.5 text-slate-700">{{ optional($row->effective_date)->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $row->bucket }}</td>
                            <td class="px-4 py-2.5 @if ($row->delta < 0) text-rose-700 @elseif ($row->delta > 0) text-emerald-700 @else text-slate-700 @endif">
                                {{ $row->delta > 0 ? '+' : '' }}{{ number_format((float) $row->delta, 3) }}
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">{{ number_format((float) $row->balance_after, 3) }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->source_label }}</td>
                            <td class="max-w-xs truncate px-4 py-2.5 text-slate-600" title="{{ $row->remarks }}">{{ $row->remarks ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No credit ledger rows yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Recent leave logs <span class="font-normal text-slate-500">({{ $logs->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">When</th>
                        <th class="px-4 py-2 font-semibold">Action</th>
                        <th class="px-4 py-2 font-semibold">Credits</th>
                        <th class="px-4 py-2 font-semibold">VL / SL</th>
                        <th class="px-4 py-2 font-semibold">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr wire:key="hub-leave-log-{{ $log->log_id }}">
                            <td class="px-4 py-2.5 text-slate-700">{{ optional($log->created_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $log->action_name }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ number_format((float) $log->credits, 3) }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ number_format((float) $log->vlc, 3) }} / {{ number_format((float) $log->slc, 3) }}</td>
                            <td class="max-w-xs truncate px-4 py-2.5 text-slate-600" title="{{ $log->remarks }}">{{ $log->remarks ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No leave logs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
