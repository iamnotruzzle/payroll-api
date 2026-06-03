<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Attendance Report</h2>
            <p class="text-sm text-slate-600">Monthly-style attendance matrix for {{ $department?->department ?? 'your department' }}.</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white px-3 py-2 text-right shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Total Hours</p>
            <p class="text-lg font-semibold text-slate-800">{{ number_format($summary['worked_minutes'] / 60, 2) }}</p>
        </div>
    </div>

    <form wire:submit.prevent="applyFilters" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[160px_220px_220px_minmax(220px,1fr)_140px] lg:items-end">
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Year</span>
                <select wire:model="filterYear" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($yearOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('filterYear') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Month</span>
                <select wire:model="filterMonth" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('filterMonth') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Employee Type</span>
                <select wire:model="filterEmployeeType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($employeeTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Search</span>
                <input wire:model="filterSearch" type="search" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee or ID">
            </label>
            <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                Show Report
            </button>
        </div>
    </form>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 text-sm">
            <div class="text-slate-600">
                Showing <span class="font-semibold text-slate-800">{{ $rows->count() }}</span> employees across
                <span class="font-semibold text-slate-800">{{ $dateCount }}</span> days
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                <span class="text-emerald-700">Present</span>
                <span class="text-rose-700">Absent</span>
                <span class="text-violet-700">Leave</span>
                <span class="text-sky-700">Holiday</span>
                <span class="text-indigo-700">Weekend</span>
                <span class="text-slate-600">Off</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
                        <th class="sticky left-0 z-20 w-16 min-w-[4rem] border-r border-slate-200 bg-slate-100 px-3 py-3">No.</th>
                        <th class="sticky left-16 z-20 w-64 min-w-[16rem] border-r border-slate-200 bg-slate-100 px-3 py-3">Employee</th>
                        <th class="w-24 min-w-[6rem] border-r border-slate-200 px-3 py-3">Summary</th>
                        @foreach ($dates as $date)
                            @php($dateValue = \Carbon\CarbonImmutable::parse($date))
                            <th class="w-28 min-w-[7rem] border-r border-slate-200 px-3 py-2 text-center">
                                <span class="block text-sm text-slate-800">{{ $dateValue->day }}</span>
                                <span class="block text-[10px] normal-case text-slate-500">{{ $dateValue->format('D') }}</span>
                            </th>
                        @endforeach
                        <th class="w-24 min-w-[6rem] px-3 py-3 text-right">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-slate-100 odd:bg-white even:bg-slate-50/60">
                            <td class="sticky left-0 z-10 border-r border-slate-200 bg-inherit px-3 py-3 text-slate-500">{{ $loop->iteration }}</td>
                            <td class="sticky left-16 z-10 border-r border-slate-200 bg-inherit px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $row['employee']->lastname }}, {{ $row['employee']->firstname }}</p>
                                <p class="text-xs text-slate-500">{{ $row['employee']->emp_id }} &middot; {{ $row['employee']->position?->position_title ?? 'No position' }}</p>
                            </td>
                            <td class="border-r border-slate-200 px-3 py-3 font-semibold text-slate-700">
                                {{ $row['present'] }}/{{ $row['scheduled'] }}
                            </td>
                            @foreach ($dates as $date)
                                @php($cell = $row['days'][$date] ?? ['status' => 'Blank', 'label' => '-'])
                                <td class="border-r border-slate-200 px-3 py-3 text-center align-middle">
                                    <span @class([
                                        'inline-flex max-w-24 items-center justify-center truncate text-xs font-semibold',
                                        'text-emerald-700' => $cell['status'] === 'Present',
                                        'text-amber-700' => $cell['status'] === 'Incomplete',
                                        'text-rose-700 italic' => $cell['status'] === 'Absent',
                                        'text-violet-700 italic' => $cell['status'] === 'Leave',
                                        'text-sky-700 italic' => $cell['status'] === 'Holiday',
                                        'text-indigo-700 italic' => $cell['status'] === 'Weekend',
                                        'text-slate-600' => $cell['status'] === 'Off',
                                        'text-slate-400' => $cell['status'] === 'Blank',
                                    ]) title="{{ $cell['label'] }}">
                                        {{ $cell['status'] === 'Weekend' ? $cell['label'] : $cell['status'] }}
                                    </span>
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-right font-semibold text-slate-800">{{ number_format($row['worked_minutes'] / 60, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + count($dates) }}" class="px-4 py-8 text-center text-slate-500">No active employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50 text-sm font-semibold text-slate-800">
                    <tr>
                        <td class="sticky left-0 z-10 bg-slate-50 px-3 py-3" colspan="2">Total</td>
                        <td class="border-r border-slate-200 px-3 py-3">{{ $summary['present'] }}/{{ $summary['scheduled'] }}</td>
                        @foreach ($dates as $date)
                            <td class="border-r border-slate-200 px-3 py-3 text-center text-slate-400">-</td>
                        @endforeach
                        <td class="px-3 py-3 text-right">{{ number_format($summary['worked_minutes'] / 60, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</section>
