<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Monthly Draft Schedule</h2>
        <p class="text-sm text-slate-600">Generate, validate, review, approve, and lock monthly schedules.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-5">
            <div>
                <label class="text-sm font-medium">Year</label>
                <input wire:model="year" type="number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Month</label>
                <input wire:model="month" type="number" min="1" max="12" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Office / Department</label>
                <div class="mt-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ $department?->department ?? 'Unassigned' }}
                </div>
            </div>
            <div>
                <label class="text-sm font-medium">Template</label>
                <select wire:model="schedule_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Auto/default</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:cursor-wait disabled:opacity-70"
                >
                    <span
                        wire:loading
                        wire:target="generate"
                        class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    ></span>
                    <span wire:loading.remove wire:target="generate">Generate Draft</span>
                    <span wire:loading wire:target="generate">Generating...</span>
                </button>
            </div>
        </div>

        <div wire:loading wire:target="generate" class="mt-4">
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full w-1/3 animate-pulse rounded-full bg-blue-700"></div>
            </div>
            <p class="mt-2 text-xs font-medium text-slate-600">Building assignments and checking conflicts...</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="font-semibold">Recent Schedules</h3>
            <div class="mt-3 space-y-2">
                @foreach ($schedules as $item)
                    <button wire:click="$set('selectedScheduleId', {{ $item->id }})" class="w-full rounded-md border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50">
                        <span class="font-medium">{{ $item->year }}-{{ str_pad($item->month, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-xs">{{ $item->status }}</span>
                    </button>
                @endforeach
            </div>
        </aside>

        <div class="min-w-0 space-y-4">
            @if ($schedule)
                <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Schedule #{{ $schedule->id }}</h3>
                            <p class="text-sm text-slate-600">{{ $schedule->year }}-{{ str_pad($schedule->month, 2, '0', STR_PAD_LEFT) }} &middot; {{ ucfirst($schedule->status) }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="inline-flex rounded-md border border-slate-300 bg-white p-1">
                                <button
                                    type="button"
                                    wire:click="$set('viewMode', 'table')"
                                    class="rounded px-3 py-1.5 text-sm font-medium {{ $viewMode === 'table' ? 'bg-blue-700 text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    Table
                                </button>
                                <button
                                    type="button"
                                    wire:click="$set('viewMode', 'calendar')"
                                    class="rounded px-3 py-1.5 text-sm font-medium {{ $viewMode === 'calendar' ? 'bg-blue-700 text-white' : 'text-slate-700 hover:bg-slate-100' }}"
                                >
                                    Calendar
                                </button>
                            </div>
                            <a
                                href="{{ route('schedule.print', $schedule) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Print / Export
                            </a>
                            <button wire:click="validateSchedule" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Validate</button>
                            <button wire:click="review" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" @disabled($schedule->isLocked())>Review</button>
                            <button wire:click="approve" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600" @disabled($schedule->isLocked())>Approve</button>
                            <button wire:click="lock" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" @disabled($schedule->status !== 'approved')>Lock</button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium">Employee</label>
                            <select wire:model.live="employee_filter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All employees</option>
                                @foreach ($employeeOptions as $employee)
                                    <option value="{{ $employee['id'] }}">{{ $employee['id'] }} - {{ $employee['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium">Shift</label>
                            <select wire:model.live="shift_filter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All shifts</option>
                                @foreach ($shiftOptions as $shift)
                                    <option value="{{ $shift['id'] }}">{{ $shift['code'] }} - {{ $shift['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                @if ($conflicts)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <h3 class="font-semibold">Conflicts</h3>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($conflicts as $conflict)
                                <li>{{ $conflict['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($viewMode === 'table')
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Editable Schedule Table</h3>
                            <p class="text-sm text-slate-600">Employees are listed by row. Days are shown as columns. Use each cell to update the assigned shift code.</p>
                        </div>
                        @if ($schedule->isLocked())
                            <span class="rounded-md bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Locked read-only</span>
                        @else
                            <span class="rounded-md bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Editable</span>
                        @endif
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-[2200px] border-separate border-spacing-0 text-xs">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 z-20 w-44 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-700">
                                        Employee
                                    </th>
                                    @foreach ($tableDays as $day)
                                        <th class="w-16 border-b border-r border-slate-200 bg-slate-50 px-2 py-2 text-center font-semibold text-slate-700">
                                            <span class="block">{{ $day['day'] }}</span>
                                            <span class="block text-[11px] font-medium text-slate-400">{{ $day['weekday'] }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($scheduleTable as $row)
                                    <tr>
                                        <th class="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-3 py-2 text-left font-semibold text-slate-800">
                                            <div class="flex items-center gap-2">
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-600">
                                                    {{ $row['employee_id'] }}
                                                </span>
                                                <span class="truncate">{{ $row['employee_name'] }}</span>
                                            </div>
                                        </th>
                                        @foreach ($tableDays as $day)
                                            @php($cell = $row['assignments'][$day['key']] ?? null)
                                            <td class="border-b border-r border-slate-200 bg-white px-1.5 py-1.5 text-center">
                                                @if ($cell)
                                                    <select
                                                        wire:change="updateAssignmentShift({{ $cell['id'] }}, $event.target.value)"
                                                        class="w-full rounded-md border border-slate-300 px-1 py-1 text-[11px] font-medium {{ $cell['night'] ? 'bg-indigo-50 text-indigo-800' : 'bg-blue-50 text-blue-800' }}"
                                                        @disabled($schedule->isLocked())
                                                    >
                                                        @foreach ($shiftCodeOptions as $shiftCode)
                                                            <option value="{{ $shiftCode['id'] }}" @selected($cell['shift_code_id'] === $shiftCode['id'])>
                                                                {{ $shiftCode['code'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-slate-300">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tableDays) + 1 }}" class="border-b border-slate-200 px-3 py-6 text-center text-sm text-slate-500">
                                            No assignments match the current filters.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                @if ($viewMode === 'calendar')
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid min-w-[1080px] grid-cols-7 gap-2 text-sm">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                            <div class="text-xs font-semibold uppercase text-slate-500">{{ $day }}</div>
                        @endforeach
                        @for ($date = \Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->startOfWeek(\Carbon\CarbonInterface::SUNDAY); $date <= \Carbon\CarbonImmutable::create($schedule->year, $schedule->month, 1)->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY); $date = $date->addDay())
                            @php($key = $date->toDateString())
                            <div class="min-h-28 rounded-md border border-slate-200 p-2 {{ $date->month === $schedule->month ? 'bg-white' : 'bg-slate-50 text-slate-400' }}">
                                <div class="text-xs font-semibold">{{ $date->day }}</div>
                                <div class="mt-2 space-y-1">
                                    @foreach ($calendar[$key] ?? [] as $assignment)
                                        <div class="truncate rounded px-2 py-1 text-xs {{ $assignment['night'] ? 'bg-indigo-50 text-indigo-800' : 'bg-blue-50 text-blue-800' }}">
                                            <span class="font-mono text-[10px] font-semibold">{{ $assignment['employee_id'] }}</span>
                                            <span>{{ $assignment['employee_name'] }} &middot; {{ $assignment['code'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">
                    Generate or select a schedule to view the calendar.
                </div>
            @endif
        </div>
    </div>
</section>
