<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Leave Reports</h2>
            <p class="text-sm text-slate-600">Monthly leave activity and totals by leave type.</p>
        </div>
        <a href="{{ route('leave.requests') }}" class="text-sm font-semibold text-[#696cff] hover:underline">Requests</a>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Month</span>
                <select wire:model.live="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ sprintf('%02d', $m) }}">{{ \Carbon\CarbonImmutable::create((int) $year, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Year</span>
                <input wire:model.live="year" type="number" min="2000" max="2100" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block sm:col-span-2">
                <span class="text-xs font-semibold uppercase text-slate-500">Leave type filter</span>
                <select wire:model.live="leaveTypeId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All types</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->leave_type_id }}">{{ $type->leave_name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p class="mt-3 text-xs text-slate-500">Period: {{ $from->toDateString() }} → {{ $to->toDateString() }}</p>
    </section>

    <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Requests</p>
            <p class="mt-1 text-2xl font-semibold">{{ $rows->count() }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Pending</p>
            <p class="mt-1 text-2xl font-semibold">{{ $statusCounts->get('pending', 0) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Approved</p>
            <p class="mt-1 text-2xl font-semibold">{{ $statusCounts->get('approved', 0) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Cancelled / Disapproved</p>
            <p class="mt-1 text-2xl font-semibold">{{ $statusCounts->get('cancelled', 0) + $statusCounts->get('disapproved', 0) }}</p>
        </div>
    </div>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">By leave type</div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead>
                <tr class="text-xs uppercase text-slate-500">
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-right">Requests</th>
                    <th class="px-4 py-3 text-right">Days WP</th>
                    <th class="px-4 py-3 text-right">Days WOP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byType as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row->leave_name }}</td>
                        <td class="px-4 py-3 text-right">{{ $row->request_count }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $row->total_wpay, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $row->total_wopay, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No type totals for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">Monthly detail (max 500)</div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead>
                <tr class="text-xs uppercase text-slate-500">
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">WP / WOP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $leave)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $leave->employee?->full_name ?: $leave->emp_id }}</p>
                            <p class="text-xs text-slate-500">{{ $leave->emp_id }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $leave->leave_type_name }}</td>
                        <td class="px-4 py-3">{{ optional($leave->start_date)->format('Y-m-d') }} → {{ optional($leave->end_date)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $leave->status_name ?: \App\Support\Hris\LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $leave->days_wpay, 2) }} / {{ number_format((float) $leave->days_wopay, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No leave in this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
