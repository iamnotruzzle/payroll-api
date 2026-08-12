<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Leave Approvals</h2>
            <p class="text-sm text-slate-600">Pending queue gated by Spatie <code>leave.approve</code> (replaces legacy user_level checks).</p>
        </div>
        <a href="{{ route('leave.requests') }}" class="text-sm font-semibold text-[#696cff] hover:underline">Back to requests</a>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search</span>
                <input wire:model.live.debounce.500ms="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee or ID">
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Rows
                <select wire:model.live="perPage" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="20">20</option>
                    <option value="40">40</option>
                </select>
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Leave</th>
                    <th class="px-4 py-3 text-left">Period / Days</th>
                    <th class="px-4 py-3 text-left">Remarks</th>
                    <th class="px-4 py-3 text-right">Decision</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($leaves as $leave)
                    <tr wire:key="approval-{{ $leave->leave_id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $leave->employee?->full_name ?: 'Unknown' }}</p>
                            <p class="text-xs text-slate-500">{{ $leave->emp_id }} · {{ $leave->employee?->department?->department ?? $leave->employee?->department?->department_name ?? '' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ $leave->leave_type_name }}</p>
                            <p class="text-xs text-slate-500">Filed {{ optional($leave->filing_date)->format('Y-m-d') }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <p>{{ optional($leave->start_date)->format('Y-m-d') }} → {{ optional($leave->end_date)->format('Y-m-d') }}</p>
                            <p class="text-xs text-slate-500">{{ number_format((float) $leave->days_wpay, 2) }} WP / {{ number_format((float) $leave->days_wopay, 2) }} WOP · {{ $leave->leave_spent ?: '—' }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="mb-2 text-xs text-slate-600">{{ $leave->remarks ?: '—' }}</p>
                            @if ($canApprove)
                                <input wire:model="remarks.{{ $leave->leave_id }}" type="text" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" placeholder="Approver remarks">
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($canApprove)
                                <button wire:click="approve({{ $leave->leave_id }})" wire:confirm="Approve this leave and deduct credits if applicable?" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                                <button wire:click="disapprove({{ $leave->leave_id }})" wire:confirm="Disapprove this leave request?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">Disapprove</button>
                            @else
                                <span class="text-xs text-slate-400">View only</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No pending leave requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>{{ $leaves->links() }}</div>
</div>
