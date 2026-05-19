<section class="space-y-4 pb-24">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">DTR Encoding and Review</h2>
            <p class="text-sm text-slate-600">
                Label blank weekdays first, then encode schedules and computed tardiness/undertime for {{ $department?->department ?? 'your department' }}.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($isLocked)
        <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            This month is locked because the MRA has already been generated.
        </div>
    @elseif (! $isLabelComplete && filled($selectedEmpId))
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            This employee has unlabeled blank weekdays. Schedules can be encoded after required labels are saved.
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[160px_160px_220px_minmax(260px,1fr)_260px]">
            <div>
                <label class="text-sm font-medium">Month</label>
                <select wire:model.live="monthFilter" wire:loading.attr="disabled" wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('monthFilter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Year</label>
                <select wire:model.live="yearFilter" wire:loading.attr="disabled" wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
                    @foreach ($yearOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                @error('yearFilter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium">Employee Type</label>
                <select wire:model.live="employeeTypeFilter" wire:loading.attr="disabled" wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
                    @foreach ($employeeTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Employee</label>
                <select wire:model.live="selectedEmpId" wire:loading.attr="disabled" wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500">
                    <option value="">Select employee</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->emp_id }}">{{ $employee->lastname }}, {{ $employee->firstname }} ({{ $employee->emp_id }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <div class="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    {{ count($rows) }} day(s), {{ collect($rows)->where('needs_label', true)->count() }} need label
                </div>
            </div>
        </div>
    </div>

    <div class="pointer-events-none fixed inset-x-0 bottom-5 z-30 flex justify-center px-4">
        <div class="pointer-events-auto flex flex-wrap items-center justify-center gap-2 rounded-lg border border-white/50 bg-white/70 px-3 py-2 shadow-lg shadow-slate-900/10 backdrop-blur-md">
            <button
                type="button"
                wire:click="previousEmployee"
                wire:loading.attr="disabled"
                wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                @disabled(! $employeeNavigation['has_previous'])
                class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Previous Employee
            </button>
            <span class="hidden text-sm text-slate-600 sm:inline">
                {{ filled($selectedEmpId) ? 'Employee '.$selectedEmpId : 'Select an employee' }}
            </span>
            <div class="mx-1 hidden h-6 w-px bg-slate-300/70 sm:block"></div>
            <button
                type="button"
                wire:click="selectAll"
                wire:loading.attr="disabled"
                wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                @disabled($isLocked || blank($selectedEmpId))
                class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Select All
            </button>
            <button
                type="button"
                wire:click="clearSelection"
                wire:loading.attr="disabled"
                wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                @disabled($isLocked || blank($selectedEmpId))
                class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Clear
            </button>
            @if (filled($selectedEmpId))
                <div class="mx-1 hidden h-6 w-px bg-slate-300/70 lg:block"></div>
                @if (! $isLabelComplete)
                    <label class="sr-only" for="floating-batch-label">Batch Label</label>
                    <select
                        id="floating-batch-label"
                        wire:model="selectedBatchLabel"
                        @disabled($isLocked)
                        class="w-48 rounded-md border border-slate-300/70 bg-white/70 px-3 py-2 text-sm text-slate-800 shadow-sm disabled:bg-slate-100/70 disabled:text-slate-500 sm:w-56"
                    >
                        <option value="">Batch label</option>
                        @foreach ($labelOptions as $option)
                            <option value="{{ $option->code }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        wire:click="applyBatchLabel"
                        @disabled($isLocked || blank($selectedBatchLabel))
                        class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Label Selected
                    </button>
                @else
                    <label class="sr-only" for="floating-batch-schedule">Batch Schedule</label>
                    <select
                        id="floating-batch-schedule"
                        wire:model="selectedBatchTemplateId"
                        @disabled($isLocked)
                        class="w-48 rounded-md border border-slate-300/70 bg-white/70 px-3 py-2 text-sm text-slate-800 shadow-sm disabled:bg-slate-100/70 disabled:text-slate-500 sm:w-64"
                    >
                        <option value="">Batch schedule</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $this->formatTemplate($template) }}</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        wire:click="applyBatchTemplate"
                        @disabled($isLocked || blank($selectedBatchTemplateId))
                        class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Apply Schedule
                    </button>
                @endif
            @endif
            <button
                type="button"
                wire:click="loadState"
                wire:loading.attr="disabled"
                wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                class="rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white/90"
            >
                Load
            </button>
            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                @disabled($isLocked || blank($selectedEmpId))
                class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Save
            </button>
            <div class="mx-1 hidden h-6 w-px bg-slate-300/70 sm:block"></div>
            <button
                type="button"
                wire:click="nextEmployee"
                wire:loading.attr="disabled"
                wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                @disabled(! $employeeNavigation['has_next'])
                class="rounded-md bg-blue-600/90 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Next Employee
            </button>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div
            wire:loading.flex
            wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
            class="items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800"
        >
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
            Loading DTR rows...
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[1080px] divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Batch</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Time In</th>
                        <th class="px-4 py-3">Time Out</th>
                        <th class="px-4 py-3">Worked</th>
                        <th class="px-4 py-3">Label</th>
                        @if ($isLabelComplete)
                            <th class="px-4 py-3">Schedule</th>
                            <th class="px-4 py-3">Tardiness</th>
                            <th class="px-4 py-3">Undertime</th>
                        @endif
                    </tr>
                </thead>
                <tbody
                    wire:loading.class.remove="hidden"
                    wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                    class="hidden divide-y divide-slate-100"
                >
                    @for ($i = 0; $i < 8; $i++)
                        <tr class="animate-pulse">
                            <td class="px-4 py-4"><div class="h-4 w-4 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4">
                                <div class="h-4 w-16 rounded bg-slate-200"></div>
                                <div class="mt-2 h-3 w-10 rounded bg-slate-100"></div>
                            </td>
                            <td class="px-4 py-4"><div class="h-4 w-20 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-20 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-14 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-9 w-48 rounded bg-slate-200"></div></td>
                            @if ($isLabelComplete)
                                <td class="px-4 py-4"><div class="h-9 w-56 rounded bg-slate-200"></div></td>
                                <td class="px-4 py-4"><div class="h-4 w-16 rounded bg-slate-200"></div></td>
                                <td class="px-4 py-4"><div class="h-4 w-16 rounded bg-slate-200"></div></td>
                            @endif
                        </tr>
                    @endfor
                </tbody>
                <tbody
                    wire:loading.remove
                    wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                    class="divide-y divide-slate-100"
                >
                    @forelse ($rows as $date => $row)
                        <tr class="{{ $row['needs_label'] ? 'bg-amber-50' : 'hover:bg-slate-50' }}">
                            <td class="px-4 py-3">
                                @if (($isLabelComplete && $row['can_encode_schedule']) || (! $isLabelComplete && $row['can_edit_label']))
                                    <input wire:model="selectedRows.{{ $date }}" type="checkbox" @disabled($isLocked)>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="font-medium">{{ \Carbon\CarbonImmutable::parse($date)->format('M d') }}</div>
                                <div class="text-xs text-slate-500">{{ $row['day'] }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $row['time_in_display'] }}</td>
                            <td class="px-4 py-3">{{ $row['time_out_display'] }}</td>
                            <td class="px-4 py-3">{{ number_format($row['worked_hours'], 2) }}</td>
                            <td class="px-4 py-3">
                                @if ($row['is_holiday'])
                                    <div class="font-medium text-blue-800">{{ str_replace('_', ' ', $row['label'] ?? 'HOLIDAY') }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['holiday_name'] }}</div>
                                @elseif ($row['is_weekend'])
                                    <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium">{{ \Carbon\CarbonImmutable::parse($date)->format('l') }}</span>
                                @elseif ($row['has_label_without_dtr'])
                                    <span class="rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-800">{{ str_replace('_', ' ', $row['label']) }}</span>
                                @else
                                    <select wire:model="rows.{{ $date }}.label" @disabled(! $row['can_edit_label'] || $isLocked) class="w-48 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                        <option value="">No label</option>
                                        @foreach ($labelOptions as $option)
                                            <option value="{{ $option->code }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            @if ($isLabelComplete)
                                <td class="px-4 py-3">
                                    <select wire:model.live="rows.{{ $date }}.template_id" @disabled(! $row['can_encode_schedule'] || $isLocked || $row['is_scheduler_synced']) class="w-56 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                        <option value="">Schedule</option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}">{{ $this->formatTemplate($template) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($row['is_scheduler_synced'])
                                        <div class="mt-1 text-xs font-medium text-emerald-700">From scheduler</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $this->formatMinutes((int) $row['tardiness_minutes']) }}</td>
                                <td class="px-4 py-3">{{ $this->formatMinutes((int) $row['undertime_minutes']) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">Select an employee to encode DTR schedules.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
