<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Daily Attendance</h2>
            <p class="text-sm text-slate-600">Review same-day attendance status for {{ $department?->department ?? 'your department' }}.</p>
        </div>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[170px_220px_minmax(0,1fr)] md:items-end">
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Date</span>
                <input wire:model.lazy="date" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Employee Type</span>
                <select wire:model.lazy="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($employeeTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Search</span>
                <input wire:model.lazy="search" type="search" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee or ID">
            </label>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Employees</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">{{ $summary['employees'] }}</p>
        </div>
        <div class="rounded-md border border-emerald-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-emerald-700">Present</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $summary['present'] }}</p>
        </div>
        <div class="rounded-md border border-amber-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-amber-700">Incomplete</p>
            <p class="mt-1 text-2xl font-semibold text-amber-700">{{ $summary['incomplete'] }}</p>
        </div>
        <div class="rounded-md border border-rose-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-rose-700">Absent</p>
            <p class="mt-1 text-2xl font-semibold text-rose-700">{{ $summary['absent'] }}</p>
        </div>
        <div class="rounded-md border border-sky-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-sky-700">Holiday</p>
            <p class="mt-1 text-2xl font-semibold text-sky-700">{{ $summary['holiday'] }}</p>
        </div>
        <div class="rounded-md border border-violet-100 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-violet-700">Leave</p>
            <p class="mt-1 text-2xl font-semibold text-violet-700">{{ $summary['leave'] }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Off</p>
            <p class="mt-1 text-2xl font-semibold text-slate-700">{{ $summary['off'] }}</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[980px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Employee</th>
                        <th class="px-4 py-3 text-left">Shift</th>
                        <th class="px-4 py-3 text-left">AM In</th>
                        <th class="px-4 py-3 text-left">AM Out</th>
                        <th class="px-4 py-3 text-left">PM In</th>
                        <th class="px-4 py-3 text-left">PM Out</th>
                        <th class="px-4 py-3 text-left">Hours</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php($dtr = $row['dtr'])
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800">{{ $row['employee']->lastname }}, {{ $row['employee']->firstname }}</p>
                                <p class="text-xs text-slate-500">{{ $row['employee']->emp_id }} &middot; {{ $row['employee']->position?->position_title ?? 'No position' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-700">{{ $row['shift']?->code ?? 'Regular' }}</p>
                                <p class="text-xs text-slate-500">{{ $row['span'] }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $dtr?->timein_am ? $this->formatTime(\Carbon\CarbonImmutable::parse($dtr->dtr_date->toDateString().' '.$dtr->timein_am), $row['duty_date']) : '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $dtr?->timeout_am ? $this->formatTime(\Carbon\CarbonImmutable::parse($dtr->dtr_date->toDateString().' '.$dtr->timeout_am), $row['duty_date']) : '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $dtr?->timein_pm ? $this->formatTime(\Carbon\CarbonImmutable::parse($dtr->dtr_date->toDateString().' '.$dtr->timein_pm), $row['duty_date']) : '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $this->formatTime($row['last_out'], $row['duty_date']) }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ number_format($row['worked_minutes'] / 60, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold @class([
                                    'bg-emerald-50 text-emerald-700' => $row['status'] === 'Present',
                                    'bg-amber-50 text-amber-700' => $row['status'] === 'Incomplete',
                                    'bg-rose-50 text-rose-700' => $row['status'] === 'Absent',
                                    'bg-sky-50 text-sky-700' => $row['status'] === 'Holiday',
                                    'bg-violet-50 text-violet-700' => $row['status'] === 'Leave',
                                    'bg-slate-100 text-slate-700' => $row['status'] === 'Off',
                                ])">{{ $row['status'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">No active employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
