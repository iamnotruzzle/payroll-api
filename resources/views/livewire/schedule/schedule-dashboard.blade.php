<section class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('schedule.dashboard') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">&larr; Back to Schedules</a>
            <h2 class="mt-1 text-xl font-semibold">
                @if ($schedule)
                    {{ $schedule->year }}-{{ str_pad((string) $schedule->month, 2, '0', STR_PAD_LEFT) }}
                    <span class="ml-2 rounded bg-slate-100 px-2 py-0.5 text-sm font-medium uppercase text-slate-700">{{ $schedule->status }}</span>
                @else
                    Monthly Schedule
                @endif
            </h2>
            <p class="text-sm text-slate-600">
                {{ $department?->department ?? 'Unassigned' }}
                @if (!empty($modeLabel))
                    <span class="ml-2 rounded px-2 py-0.5 text-[11px] font-semibold {{ !empty($isCno) ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-200 text-slate-700' }}">
                        {{ $modeLabel }}
                    </span>
                @endif
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="min-w-0 space-y-4">
        @if ($schedule)
            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Schedule #{{ $schedule->id }}</h3>
                        <p class="text-sm text-slate-600">Validate, review, approve, and lock. Draft generation and NDOS import are on the Schedules list.</p>
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
                        @can('schedule.view')
                            <a
                                href="{{ route('schedule.pdf', $schedule) }}"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Download PDF
                            </a>
                        @endcan
                        @can('schedule.manage')
                            <a
                                href="{{ route('schedule.print-settings') }}#distribution"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Email PDF
                            </a>
                        @endcan
                        <button wire:click="validateSchedule" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Validate</button>
                        <button wire:click="review" class="rounded-md border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" @disabled($schedule->isLocked())>Review</button>
                        <button wire:click="approve" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-600" @disabled($schedule->isLocked())>Approve</button>
                        <button wire:click="lock" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800" @disabled($schedule->status !== 'approved')>Lock</button>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 {{ !empty($profile?->uses_units) ? 'md:grid-cols-3' : 'md:grid-cols-2' }}">
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
                    @if (!empty($profile?->uses_units))
                        <div>
                            <label class="text-sm font-medium">{{ $unitNoun ?? 'Unit' }}</label>
                            <select wire:model.live="unit_filter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">All {{ strtolower($unitNounPlural ?? 'units') }}</option>
                                @foreach ($unitOptions as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->code }} — {{ $unit->name }}</option>
                                @endforeach
                            </select>
                            @if ($handledUnitIds !== null)
                                <p class="mt-1 text-xs text-slate-500">Scoped to your handled {{ strtolower($unitNounPlural ?? 'units') }}.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

                @can('schedule.manage')
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Pattern fill</h3>
                            <p class="text-sm text-slate-600">
                                Apply a template/rotation pattern to selected employees or a date range. Preview first; locked months stay read-only.
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="$toggle('showPatternPanel')"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            {{ $showPatternPanel ? 'Hide' : 'Show' }}
                        </button>
                    </div>

                    @if ($showPatternPanel)
                        @if ($schedule->isLocked())
                            <p class="mt-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                This month is locked — pattern fill is disabled (approve → lock → DTR is unchanged).
                            </p>
                        @else
                            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                                <div class="xl:col-span-2">
                                    <label class="text-sm font-medium">Pattern / template</label>
                                    <select wire:model="pattern_fill_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Select pattern…</option>
                                        @foreach ($rowPatternOptions as $pattern)
                                            <option value="{{ $pattern->id }}">
                                                {{ $pattern->name }}
                                                @if ($pattern->days->isNotEmpty())
                                                    ({{ $pattern->days->count() === 7 ? 'weekly' : $pattern->days->count().'-day' }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium">Scope</label>
                                    <select wire:model="pattern_fill_scope" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="selected">Selected rows ({{ count($selectedEmployeeIds) }})</option>
                                        <option value="filtered">Current table filters</option>
                                        <option value="all">Entire schedule</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium">From</label>
                                    <input wire:model="pattern_fill_date_from" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="text-sm font-medium">To</label>
                                    <input wire:model="pattern_fill_date_to" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="selectVisibleEmployees" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                    Select visible employees
                                </button>
                                <button type="button" wire:click="clearEmployeeSelection" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                    Clear selection
                                </button>
                                <button
                                    type="button"
                                    wire:click="previewPatternFill"
                                    wire:loading.attr="disabled"
                                    class="rounded-md border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-900 hover:bg-blue-100 disabled:opacity-60"
                                >
                                    Preview
                                </button>
                                <button
                                    type="button"
                                    wire:click="applyPatternFill"
                                    wire:confirm="Apply the pattern to the chosen employees/date range? This updates draft shifts only."
                                    wire:loading.attr="disabled"
                                    class="rounded-md bg-blue-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-600 disabled:opacity-60"
                                    @disabled(empty($patternPreviewSummary))
                                >
                                    Apply pattern
                                </button>
                                @if (!empty($patternPreviewSummary))
                                    <button type="button" wire:click="clearPatternPreview" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                        Clear preview
                                    </button>
                                    <span class="text-xs text-slate-600">
                                        Preview: {{ $patternPreviewSummary['changed'] ?? 0 }} change(s) /
                                        {{ $patternPreviewSummary['unchanged'] ?? 0 }} unchanged /
                                        {{ $patternPreviewSummary['employees'] ?? 0 }} employee(s)
                                    </span>
                                @endif
                            </div>

                            @if (!empty($patternPreviewChanges))
                                <div class="mt-3 max-h-56 overflow-auto rounded-md border border-slate-200">
                                    <table class="min-w-full divide-y divide-slate-100 text-xs">
                                        <thead class="sticky top-0 bg-slate-50 text-left text-[10px] uppercase text-slate-500">
                                            <tr>
                                                <th class="px-2 py-1.5">Emp</th>
                                                <th class="px-2 py-1.5">Date</th>
                                                <th class="px-2 py-1.5">From</th>
                                                <th class="px-2 py-1.5">To</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            @foreach (array_slice($patternPreviewChanges, 0, 200) as $change)
                                                <tr>
                                                    <td class="px-2 py-1 font-mono">{{ $change['employee_id'] }}</td>
                                                    <td class="px-2 py-1">{{ $change['schedule_date'] }}</td>
                                                    <td class="px-2 py-1">{{ $change['from_code'] ?? '-' }}</td>
                                                    <td class="px-2 py-1 font-semibold text-blue-800">{{ $change['to_code'] ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @if (count($patternPreviewChanges) > 200)
                                        <p class="border-t border-slate-100 px-2 py-1.5 text-[11px] text-slate-500">
                                            Showing first 200 of {{ count($patternPreviewChanges) }} changes.
                                        </p>
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
                @endcan

                @if ($conflicts)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold">Conflicts</h3>
                            <button
                                type="button"
                                wire:click="toggleConflicts"
                                class="rounded-md border border-amber-300 bg-white/70 px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-white"
                            >
                                {{ $showConflicts ? 'Hide' : 'Show' }}
                            </button>
                        </div>
                        @if ($showConflicts)
                            <ul class="mt-2 list-disc pl-5">
                                @foreach ($conflicts as $conflict)
                                    <li>{{ $conflict['message'] }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-xs font-medium text-amber-800">{{ count($conflicts) }} conflict{{ count($conflicts) === 1 ? '' : 's' }} hidden.</p>
                        @endif
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
                        @php($weeklyTotalColumnCount = collect($tableDays)->where('ends_week', true)->count())
                        <table class="min-w-[2200px] border-separate border-spacing-0 text-xs">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 z-20 w-44 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-700">
                                        <div class="flex items-center gap-2">
                                            <span>Employee</span>
                                            @can('schedule.manage')
                                                <button type="button" wire:click="selectVisibleEmployees" class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-700 hover:bg-slate-300" title="Select all visible">All</button>
                                            @endcan
                                        </div>
                                    </th>
                                    <th class="w-72 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-semibold text-slate-700">
                                        Pattern
                                    </th>
                                    @foreach ($tableDays as $day)
                                        <th class="w-24 border-b border-r border-slate-200 bg-slate-50 px-2 py-2 text-center font-semibold text-slate-700">
                                            <span class="block">{{ $day['day'] }}</span>
                                            <span class="block text-[11px] font-medium text-slate-400">{{ $day['weekday'] }}</span>
                                            @if ($day['holiday_label'])
                                                <span class="mt-1 block truncate rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-800" title="{{ $day['holiday_name'] }}">
                                                    {{ $day['holiday_label'] }}
                                                </span>
                                            @endif
                                        </th>
                                        @if ($day['ends_week'])
                                            <th class="w-20 border-b border-r border-slate-200 bg-emerald-50 px-2 py-2 text-center font-semibold text-emerald-800">
                                                <span class="block">Week</span>
                                                <span class="block text-[11px] font-medium text-emerald-600">Hours</span>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($scheduleTable as $row)
                                    <tr>
                                        <th class="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-3 py-2 text-left font-semibold text-slate-800">
                                            <div class="flex items-center gap-2">
                                                @can('schedule.manage')
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-slate-300"
                                                        @checked(in_array($row['employee_id'], $selectedEmployeeIds, true))
                                                        wire:click="toggleEmployeeSelection('{{ $row['employee_id'] }}')"
                                                        @disabled($schedule->isLocked())
                                                        title="Select for pattern fill"
                                                    >
                                                @endcan
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-600">
                                                    {{ $row['employee_id'] }}
                                                </span>
                                                <span class="truncate">{{ $row['employee_name'] }}</span>
                                            </div>
                                        </th>
                                        <td class="border-b border-r border-slate-200 bg-white px-2 py-1.5">
                                            <select
                                                wire:change="applyEmployeePattern('{{ $row['employee_id'] }}', $event.target.value)"
                                                class="w-64 rounded-md border border-slate-300 py-1.5 pl-2 pr-8 text-xs font-medium text-slate-700"
                                                @disabled($schedule->isLocked())
                                                title="Loads this employee into Pattern fill and shows a preview (confirm with Apply pattern)"
                                            >
                                                <option value="">Preview pattern…</option>
                                                @foreach ($rowPatternOptions as $pattern)
                                                    <option value="{{ $pattern->id }}">{{ $pattern->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        @foreach ($tableDays as $day)
                                            @php($cell = $row['assignments'][$day['key']] ?? null)
                                            <td class="border-b border-r border-slate-200 bg-white px-2 py-1.5 text-center">
                                                @if ($cell)
                                                    <select
                                                        wire:change="updateAssignmentShift({{ $cell['id'] }}, $event.target.value)"
                                                        class="w-full min-w-20 appearance-none rounded-md border border-slate-300 px-2 py-1 text-center text-[11px] font-semibold {{ $cell['night'] ? 'bg-indigo-50 text-indigo-800' : 'bg-blue-50 text-blue-800' }}"
                                                        @disabled($schedule->isLocked())
                                                    >
                                                        @foreach ($shiftCodeOptions as $shiftCode)
                                                            <option value="{{ $shiftCode['id'] }}" @selected($cell['shift_code_id'] === $shiftCode['id'])>
                                                                {{ $shiftCode['code'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @if (!empty($profile?->uses_units))
                                                        <select
                                                            wire:change="updateAssignmentUnit({{ $cell['id'] }}, $event.target.value)"
                                                            class="mt-1 w-full appearance-none rounded-md border border-slate-200 bg-slate-50 px-1 py-0.5 text-center text-[10px] text-slate-600"
                                                            @disabled($schedule->isLocked())
                                                            title="Schedule {{ strtolower($unitNoun ?? 'unit') }}"
                                                        >
                                                            <option value="">{{ $unitNoun ?? 'Unit' }}…</option>
                                                            @foreach ($unitOptions as $unit)
                                                                @if ($handledUnitIds === null || in_array((int) $unit->id, $handledUnitIds, true))
                                                                    <option value="{{ $unit->id }}" @selected((int) $cell['unit_id'] === (int) $unit->id)>
                                                                        {{ $unit->code }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                    @if (!empty($profile?->uses_floaters))
                                                        <label class="mt-1 flex items-center justify-center gap-1 text-[9px] text-slate-500" title="Temporary floater">
                                                            <input
                                                                type="checkbox"
                                                                class="rounded border-slate-300"
                                                                @checked(!empty($cell['is_temporary_floater']))
                                                                wire:click="toggleTemporaryFloater({{ $cell['id'] }})"
                                                                @disabled($schedule->isLocked())
                                                            >
                                                            Float
                                                        </label>
                                                    @endif
                                                @else
                                                    <span class="text-slate-300">-</span>
                                                @endif
                                            </td>
                                            @if ($day['ends_week'])
                                                @php($weeklyHours = $row['weekly_hours'][$day['week_key']] ?? 0)
                                                <td class="border-b border-r border-slate-200 bg-emerald-50 px-2 py-1.5 text-center font-semibold text-emerald-800">
                                                    {{ rtrim(rtrim(number_format($weeklyHours, 2), '0'), '.') }}
                                                </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($tableDays) + $weeklyTotalColumnCount + 2 }}" class="border-b border-slate-200 px-3 py-6 text-center text-sm text-slate-500">
                                            No assignments match the current filters.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold">Daily Shift Summary</h3>
                            <p class="text-sm text-slate-600">Allocated shift counts per day for the current table filters.</p>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-[1200px] border-separate border-spacing-0 text-xs">
                            <thead>
                                <tr>
                                    @foreach ($dailyShiftSummary as $day)
                                        <th class="w-32 border-b border-r border-slate-200 bg-slate-50 px-2 py-2 text-center font-semibold text-slate-700">
                                            <span class="block">{{ $day['day'] }}</span>
                                            <span class="block text-[11px] font-medium text-slate-400">{{ $day['weekday'] }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @forelse ($dailyShiftSummary as $day)
                                        <td class="align-top border-b border-r border-slate-200 bg-white px-2 py-2">
                                            <div class="space-y-1">
                                                @foreach ($day['shifts'] as $shift)
                                                    <div class="flex items-center justify-between gap-2 rounded-md border border-slate-300 bg-slate-50 px-2 py-1.5 whitespace-nowrap shadow-sm">
                                                        <span class="font-mono text-[11px] font-bold uppercase tracking-wide text-slate-800 whitespace-nowrap">{{ $shift['code'] }}</span>
                                                        <span class="min-w-6 rounded bg-slate-900 px-1.5 py-0.5 text-center text-xs font-bold text-white">{{ $shift['count'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    @empty
                                        <td class="border-b border-slate-200 px-3 py-6 text-center text-sm text-slate-500">
                                            No assignments match the current filters.
                                        </td>
                                    @endforelse
                                </tr>
                            </tbody>
                            @if ($dailyShiftSummary)
                                <tfoot>
                                    <tr>
                                        @foreach ($dailyShiftSummary as $day)
                                            <td class="border-b border-r border-slate-200 bg-slate-50 px-2 py-2">
                                                <div class="flex items-center justify-between gap-2 rounded-md bg-white px-2 py-1 font-semibold text-slate-800 ring-1 ring-slate-200">
                                                    <span class="text-[11px] uppercase text-slate-500">Total</span>
                                                    <span class="text-sm">{{ $day['total'] }}</span>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            @endif
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
                    Schedule not found.
                    <div class="mt-3">
                        <a href="{{ route('schedule.dashboard') }}" class="text-sm font-medium text-blue-700 hover:underline">Back to Schedules</a>
                    </div>
                </div>
            @endif
    </div>
</section>
