<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">My Schedule</h2>
        <p class="text-sm text-slate-600">Your published schedule (approved or locked) for the selected month.</p>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <p class="font-semibold text-slate-800">{{ $employee->full_name }}</p>
        <p class="text-sm text-slate-600">
            {{ $employee->emp_id }}
            · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}
        </p>
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Month</span>
                <select wire:model.live="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Year</span>
                <select wire:model.live="year" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($yearOptions as $yearOption)
                        <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                    @endforeach
                </select>
            </label>
            <div class="sm:col-span-2 flex items-end">
                <p class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ $periodLabel }} · {{ $assignmentCount }} assignment(s) · {{ $workDays }} work day(s)
                </p>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-3 text-left">Day</th>
                    <th class="px-3 py-3 text-left">Shift</th>
                    <th class="px-3 py-3 text-left">Hours</th>
                    <th class="px-3 py-3 text-left">Unit</th>
                    <th class="px-3 py-3 text-left">Roster status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="{{ empty($row['code']) ? 'bg-slate-50/60' : '' }}">
                        <td class="px-3 py-2 font-medium whitespace-nowrap">
                            {{ $row['day'] }}
                            <span class="ml-1 text-xs font-normal text-slate-500">{{ $row['weekday'] }}</span>
                        </td>
                        <td class="px-3 py-2">
                            @if ($row['code'])
                                <span class="font-semibold {{ $row['is_night'] ? 'text-indigo-700' : 'text-slate-900' }}">{{ $row['code'] }}</span>
                                <span class="ml-1 text-slate-500">{{ $row['shift_name'] }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums">
                            {{ $row['hours'] !== null && $row['hours'] !== '' ? number_format((float) $row['hours'], 2) : '—' }}
                        </td>
                        <td class="px-3 py-2">{{ $row['unit'] ?: '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($row['status'])
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs uppercase">{{ $row['status'] }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-slate-500">No published schedule for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <p class="text-xs text-slate-500">
        Only approved or locked monthly schedules appear here. Draft and reviewed rosters stay invisible until published.
        Lock → DTR sync is unchanged and remains the payroll path.
    </p>
</section>
