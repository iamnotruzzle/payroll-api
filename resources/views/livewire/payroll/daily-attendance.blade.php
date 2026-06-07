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

    @php
        $summaryCards = [
            [
                'key' => '',
                'label' => 'Employees',
                'value' => number_format($summary['employees']),
                'helper' => 'Show all employees',
                'cardClass' => 'border-slate-200 bg-slate-50 text-slate-900 hover:bg-slate-100',
                'valueClass' => 'text-slate-900',
                'helperClass' => 'text-slate-500',
                'activeClass' => 'ring-slate-400',
            ],
            [
                'key' => 'scheduled',
                'label' => 'Scheduled',
                'value' => number_format($summary['scheduled']),
                'helper' => 'Has work schedule',
                'cardClass' => 'border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100',
                'valueClass' => 'text-indigo-900',
                'helperClass' => 'text-indigo-700',
                'activeClass' => 'ring-indigo-500',
            ],
            [
                'key' => 'present',
                'label' => 'Present',
                'value' => number_format($summary['present']),
                'helper' => 'Complete time in/out',
                'cardClass' => 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100',
                'valueClass' => 'text-emerald-900',
                'helperClass' => 'text-emerald-700',
                'activeClass' => 'ring-emerald-500',
            ],
            [
                'key' => 'incomplete',
                'label' => 'Incomplete',
                'value' => number_format($summary['incomplete']),
                'helper' => 'Missing time entry',
                'cardClass' => 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100',
                'valueClass' => 'text-amber-900',
                'helperClass' => 'text-amber-700',
                'activeClass' => 'ring-amber-500',
            ],
            [
                'key' => 'absent',
                'label' => 'Absent',
                'value' => number_format($summary['absent']),
                'helper' => 'No DTR record',
                'cardClass' => 'border-rose-200 bg-rose-50 text-rose-900 hover:bg-rose-100',
                'valueClass' => 'text-rose-900',
                'helperClass' => 'text-rose-700',
                'activeClass' => 'ring-rose-500',
            ],
            [
                'key' => 'leave',
                'label' => 'Leave',
                'value' => number_format($summary['leave']),
                'helper' => 'Approved leave',
                'cardClass' => 'border-violet-200 bg-violet-50 text-violet-900 hover:bg-violet-100',
                'valueClass' => 'text-violet-900',
                'helperClass' => 'text-violet-700',
                'activeClass' => 'ring-violet-500',
            ],
            [
                'key' => 'holiday',
                'label' => 'Holiday',
                'value' => number_format($summary['holiday']),
                'helper' => 'Holiday date',
                'cardClass' => 'border-sky-200 bg-sky-50 text-sky-900 hover:bg-sky-100',
                'valueClass' => 'text-sky-900',
                'helperClass' => 'text-sky-700',
                'activeClass' => 'ring-sky-500',
            ],
            [
                'key' => 'off',
                'label' => 'Off',
                'value' => number_format($summary['off']),
                'helper' => 'Rest/off duty',
                'cardClass' => 'border-slate-300 bg-slate-100 text-slate-900 hover:bg-slate-200',
                'valueClass' => 'text-slate-900',
                'helperClass' => 'text-slate-600',
                'activeClass' => 'ring-slate-500',
            ],
        ];
    @endphp

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
        @foreach ($summaryCards as $card)
            @php($isActive = $statusFilter === $card['key'])

            <button
                type="button"
                wire:key="daily-attendance-summary-card-{{ $card['key'] === '' ? 'all' : $card['key'] }}"
                wire:click="setStatusFilter('{{ $card['key'] }}')"
                @class([
                    'rounded-xl border p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2',
                    $card['cardClass'],
                    'ring-2 ring-offset-2' => $isActive,
                    $card['activeClass'] => $isActive,
                ])
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold {{ $card['valueClass'] }}">{{ $card['value'] }}</p>
                    </div>

                    @if ($isActive)
                        <span class="rounded-full bg-white/80 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide">
                            Active
                        </span>
                    @endif
                </div>

                <p class="mt-2 text-xs {{ $card['helperClass'] }}">{{ $card['helper'] }}</p>
            </button>
        @endforeach
    </section>

    @if ($statusFilter !== '')
        <div class="flex flex-col gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                Showing employees with
                <span class="font-semibold">{{ $activeStatusFilterLabel }}</span>
                records.
                <span class="text-indigo-600">
                    {{ number_format($filteredEmployeeCount) }} of {{ number_format($totalEmployeeCount) }} employees shown.
                </span>
            </div>

            <button
                type="button"
                wire:click="clearStatusFilter"
                class="text-left font-semibold text-indigo-700 hover:text-indigo-900 sm:text-right"
            >
                Clear filter
            </button>
        </div>
    @endif

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
