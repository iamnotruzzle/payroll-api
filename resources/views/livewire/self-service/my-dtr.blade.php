<section class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My DTR</h2>
            <p class="text-sm text-slate-600">Read-only view of your daily time record for a selected month.</p>
        </div>
        <a
            href="{{ $printUrl }}"
            target="_blank"
            class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]"
        >
            Print DTR
        </a>
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
                    Showing {{ $period->format('F Y') }} · {{ $rows->where('has_punches', true)->count() }} day(s) with punches
                </p>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-3 text-left">Day</th>
                    <th class="px-3 py-3 text-left">AM In</th>
                    <th class="px-3 py-3 text-left">AM Out</th>
                    <th class="px-3 py-3 text-left">PM In</th>
                    <th class="px-3 py-3 text-left">PM Out</th>
                    <th class="px-3 py-3 text-left">Undertime</th>
                    <th class="px-3 py-3 text-left">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <tr class="{{ filled($row['label']) && ! $row['has_punches'] ? 'bg-slate-50/70' : '' }}">
                        <td class="px-3 py-2 font-medium whitespace-nowrap">
                            {{ $row['day'] }}
                            <span class="ml-1 text-xs font-normal text-slate-500">{{ $row['weekday'] ?? '' }}</span>
                        </td>
                        @if (filled($row['label']) && ! $row['has_punches'])
                            <td colspan="4" class="px-3 py-2 text-center text-slate-600">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-slate-500">—</td>
                            <td class="px-3 py-2 text-slate-500">—</td>
                        @else
                            <td class="px-3 py-2 tabular-nums">{{ $row['timein_am'] ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $row['timeout_am'] ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $row['timein_pm'] ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $row['timeout_pm'] ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">
                                @if ($row['undertime_hours'] !== '' || $row['undertime_minutes'] !== '')
                                    {{ $row['undertime_hours'] ?: '0' }}h {{ $row['undertime_minutes'] ?: '0' }}m
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-500">{{ $row['label'] ?: '—' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <p class="text-xs text-slate-500">
        Source: HRIS DTR punches (`tbl_employee_dtr`) plus payroll labels/adjustments used by DTR Encoding. This screen is read-only — request corrections through Timekeeping if needed.
    </p>
</section>
