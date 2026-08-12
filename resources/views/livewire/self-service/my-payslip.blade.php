<section class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My Payslip</h2>
            <p class="text-sm text-slate-600">View and print your payslips from finalized payroll snapshots.</p>
        </div>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <p class="font-semibold text-slate-800">{{ $employee->full_name }}</p>
        <p class="text-sm text-slate-600">
            {{ $employee->emp_id }}
            · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}
            · {{ $employee->position?->position ?? $employee->position?->position_name ?? '—' }}
        </p>
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-[220px_auto] sm:items-end">
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Payroll period</span>
                <input wire:model.live="period" type="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <button type="button" wire:click="clearFilters" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Clear filter
            </button>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-right">Gross</th>
                    <th class="px-4 py-3 text-right">Net</th>
                    <th class="px-4 py-3 text-right">15th</th>
                    <th class="px-4 py-3 text-right">30th</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $record)
                    @php
                        $batch = $record->batch;
                        $totals = $record->snapshot_json['totals'] ?? [];
                        $gross = $record->gross ?? data_get($totals, 'gross', 0);
                        $net = $record->net ?? data_get($totals, 'net_after_loan_deductions', 0);
                        $fifteenth = $record->fifteenth ?? data_get($totals, 'fifteenth', 0);
                        $thirtieth = $record->thirtieth ?? data_get($totals, 'thirtieth', 0);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $batch?->payroll_period ?? '—' }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $batch?->snapshot_created_at?->format('M d, Y g:i A') ?? '—' }}
                            </p>
                        </td>
                        <td class="px-4 py-3">{{ $batch?->payroll_type ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $gross, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ number_format((float) $net, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $fifteenth, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $thirtieth, 2) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a
                                href="{{ route('self-service.payslip.print', $record->id) }}"
                                target="_blank"
                                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
                            >
                                View / Print
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                            No payslip snapshots found for your employee ID yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($records->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $records->links() }}
            </div>
        @endif
    </section>
</section>
