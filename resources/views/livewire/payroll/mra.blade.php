<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Monthly Report of Attendance</h2>
            <p class="text-sm text-slate-600">
                Preview undertime and tardiness deductions, then finalize MRA for {{ $department?->department ?? 'your department' }}.
            </p>
        </div>
        <button wire:click="finalize" wire:loading.attr="disabled"
            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:opacity-50">
            Finalize MRA
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($report)
        <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Current range is finalized as MRA #{{ $report->id }} by {{ $report->generated_by }} on {{ $report->generated_at?->format('M d, Y g:i A') }}.
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_2fr]">
            <div>
                <label class="text-sm font-medium">From</label>
                <input wire:model.live="from" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">To</label>
                <input wire:model.live="to" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Employee Type</label>
                <select wire:model.live="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($employeeTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Remarks</label>
                <input wire:model="remarks" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">MRA Preview</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3 text-right">SL</th>
                            <th class="px-4 py-3 text-right">VL</th>
                            <th class="px-4 py-3 text-right">Undertime/Tardy</th>
                            <th class="px-4 py-3 text-right">Day Equiv</th>
                            <th class="px-4 py-3 text-right">Physically Reported</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($previewRows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $row['employee_name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['emp_id'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['position'] ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @forelse ($row['details'] as $detail)
                                        <div class="text-slate-700">
                                            {{ $detail['date'] }} - {{ $detail['remarks'] }}
                                            @if ($detail['minutes'] > 0)
                                                <span class="text-slate-500">({{ $this->formatMinutes($detail['minutes']) }})</span>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-slate-500">No exceptions</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['sick_leave_days'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['vacation_leave_days'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ $this->formatMinutes($row['undertime_minutes']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['day_equivalent'], 3) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['physically_reported_hours'], 2) }} hours</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No active employees found for this department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm font-semibold">
                        <tr>
                            <td colspan="2" class="px-4 py-3">Totals</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right">{{ $this->formatMinutes($previewRows->sum('undertime_minutes')) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($previewRows->sum('day_equivalent'), 3) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($previewRows->sum('physically_reported_hours'), 2) }} hours</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold">Recent MRA Reports</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($reports as $item)
                        <div class="rounded-md border border-slate-200 px-3 py-2 text-sm">
                            <div class="font-medium">{{ $item->period_start?->format('M d') }} - {{ $item->period_end?->format('M d, Y') }}</div>
                            <div class="text-xs text-slate-500">{{ $item->status }} by {{ $item->generated_by }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No MRA reports yet.</p>
                    @endforelse
                </div>
            </div>

            @if ($adjustments->isNotEmpty())
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="font-semibold">Finalized Adjustments</h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($adjustments as $adjustment)
                            <div class="rounded-md border border-slate-200 px-3 py-2 text-sm">
                                <div class="font-medium">{{ $adjustment->employee_name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ number_format($adjustment->adjustment_days, 3) }} VL day(s), {{ $adjustment->undertime_tardy_minutes }} min
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>
</section>
