<section class="space-y-4">
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

    <div x-data="{ filtersOpen: localStorage.getItem('erp-dtr-filters-open') !== 'false' }"
        x-effect="localStorage.setItem('erp-dtr-filters-open', filtersOpen ? 'true' : 'false')"
        class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-semibold">Filters &amp; employee</h3>
            <button type="button" x-on:click="filtersOpen = !filtersOpen"
                :aria-expanded="filtersOpen.toString()" aria-controls="dtr-encoding-filters"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium"
                x-text="filtersOpen ? 'Hide filters' : 'Show filters'">Hide filters</button>
        </div>
        <div id="dtr-encoding-filters" x-collapse.duration.220ms x-show="filtersOpen" class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[160px_160px_220px_minmax(260px,1fr)_260px]">
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
                <select
                    wire:loading.attr="disabled"
                    wire:target="monthFilter,yearFilter,employeeTypeFilter,selectedEmpId,previousEmployee,nextEmployee,loadState"
                    data-select2-employee-picker
                    data-model="selectedEmpId"
                    data-placeholder="Select employee"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100 disabled:text-slate-500"
                >
                    <option value="" @selected(blank($selectedEmpId))>Select employee</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->emp_id }}" @selected($selectedEmpId === $employee->emp_id)>{{ $employee->lastname }}, {{ $employee->firstname }} ({{ $employee->emp_id }})</option>
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

    <div class="erp-record-actions erp-dtr-batch-controls rounded-lg border border-slate-200 bg-white"
        x-data="{ batchControlsOpen: window.matchMedia('(min-width: 1024px)').matches }"
        x-on:keydown.escape.window="batchControlsOpen = false">
        <div class="erp-dtr-batch-controls-heading flex items-center justify-between gap-3 px-3 py-2">
            <div>
                <span class="text-sm font-semibold">Batch controls</span>
                <span class="ml-2 text-xs text-slate-500" x-text="Object.values($wire.selectedRows || {}).filter(Boolean).length + ' selected'"></span>
            </div>
            <button type="button" x-on:click="batchControlsOpen = !batchControlsOpen"
                :aria-expanded="batchControlsOpen.toString()" aria-controls="dtr-batch-controls"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium"
                x-text="batchControlsOpen ? 'Hide controls' : 'Show controls'">Show controls</button>
        </div>
        <button type="button" class="erp-dtr-batch-fab" x-on:click="batchControlsOpen = !batchControlsOpen"
            :aria-expanded="batchControlsOpen.toString()" aria-controls="dtr-batch-controls"
            :aria-label="batchControlsOpen ? 'Hide batch controls' : 'Show batch controls'"
            :title="batchControlsOpen ? 'Hide batch controls' : 'Show batch controls'">
            <svg x-show="!batchControlsOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2" fill="currentColor"/><circle cx="16" cy="12" r="2" fill="currentColor"/><circle cx="10" cy="18" r="2" fill="currentColor"/></svg>
            <svg x-cloak x-show="batchControlsOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            <span class="erp-dtr-batch-count" x-show="Object.values($wire.selectedRows || {}).filter(Boolean).length > 0"
                x-text="Object.values($wire.selectedRows || {}).filter(Boolean).length" aria-hidden="true"></span>
        </button>
        <div id="dtr-batch-controls" class="erp-dtr-batch-bubble" x-cloak x-show="batchControlsOpen" x-transition.origin.bottom.right.duration.180ms
            x-on:click.outside="if (window.matchMedia('(max-width: 1023px)').matches && !$event.target.closest('.erp-dtr-batch-controls')) batchControlsOpen = false">
        <div class="erp-dtr-batch-bubble-title">Batch controls <span class="text-xs font-normal" x-text="Object.values($wire.selectedRows || {}).filter(Boolean).length + ' selected'"></span></div>
        <div class="erp-dtr-batch-controls-body flex w-full flex-wrap items-center justify-center gap-2 border-t border-slate-200 p-3">
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
                <hr class="my-1 w-full basis-full border-0 border-t border-slate-200">
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
                <hr class="my-1 w-full basis-full border-0 border-t border-slate-200">
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
            <a
                href="{{ route('payroll.dtr-encoding.print-bulk', ['month' => $monthFilter, 'year' => $yearFilter, 'employee_type' => $employeeTypeFilter]) }}"
                target="_blank"
                class="inline-flex min-w-[96px] items-center justify-center rounded-md border border-slate-300/70 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-white/90"
                title="Print CS Form 48 for all active employees in this department for the selected month"
            >
                Bulk PDF
            </a>
            @if (filled($selectedEmpId))
                <a
                    href="{{ route('payroll.dtr-encoding.print', ['emp_id' => $selectedEmpId, 'month' => $monthFilter, 'year' => $yearFilter]) }}"
                    target="_blank"
                    class="inline-flex min-w-[96px] items-center justify-center rounded-md bg-blue-600/90 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-600"
                >
                    PDF View
                </a>
            @endif
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
                        <tr data-card-selectable class="{{ $row['needs_label'] ? 'bg-amber-50' : 'hover:bg-slate-50' }}">
                            <td data-card-selection class="px-4 py-3">
                                @if (($isLabelComplete && $row['can_encode_schedule']) || (! $isLabelComplete && $row['can_edit_label']))
                                    <label class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center">
                                        <input wire:model="selectedRows.{{ $date }}" type="checkbox" aria-label="Select {{ \Carbon\CarbonImmutable::parse($date)->format('F j, Y') }} for batch editing" @disabled($isLocked)>
                                    </label>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="font-medium">{{ \Carbon\CarbonImmutable::parse($date)->format('M d') }}</div>
                                <div class="text-xs text-slate-500">{{ $row['day'] }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $row['time_in_display'] }}</td>
                            <td class="px-4 py-3">{{ $row['time_out_display'] }}</td>
                            <td class="px-4 py-3">{{ number_format($row['worked_hours'], 2) }}</td>
                            <td data-card-editor class="px-4 py-3">
                                @if ($row['is_holiday'])
                                    <div class="font-medium text-blue-800">{{ str_replace('_', ' ', $row['label'] ?? 'HOLIDAY') }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['holiday_name'] }}</div>
                                @elseif ($row['is_weekend'])
                                    <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium">{{ \Carbon\CarbonImmutable::parse($date)->format('l') }}</span>
                                @elseif ($row['has_leave'])
                                    <span class="rounded bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800">{{ str_replace('_', ' ', $row['label']) }}</span>
                                    <div class="mt-1 text-xs text-slate-500">{{ $row['leave_name'] }}</div>
                                @elseif ($row['has_label_without_dtr'])
                                    <span class="rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-800">{{ str_replace('_', ' ', $row['label']) }}</span>
                                @else
                                    <select aria-label="Label for {{ $date }}" wire:model="rows.{{ $date }}.label" @disabled(! $row['can_edit_label'] || $isLocked) class="w-48 rounded-md border border-slate-300 px-2 py-1.5 text-sm disabled:bg-slate-100">
                                        <option value="">No label</option>
                                        @foreach ($labelOptions as $option)
                                            <option value="{{ $option->code }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            @if ($isLabelComplete)
                                <td data-card-editor class="px-4 py-3">
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
