<section class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Schedules</h2>
            <p class="text-sm text-slate-600">
                @if (!empty($isCno))
                    Open a monthly schedule to review, or import approved nursing schedules from NDOS.
                @else
                    Open a monthly schedule to edit, or generate a new draft for your office.
                @endif
            </p>
        </div>
        @can('schedule.manage')
            @if (!empty($isCno))
                <a
                    href="{{ $ndosImportUrl }}"
                    title="Nursing Division Online Scheduling — import approved schedules"
                    class="inline-flex items-center rounded-md bg-cyan-700 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-600"
                >
                    Import from NDOS
                </a>
            @else
                <button
                    type="button"
                    wire:click="openGenerateModal"
                    class="inline-flex items-center rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600"
                >
                    Generate New Schedule
                </button>
            @endif
        @endcan
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @error('generate')
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-sm font-medium">Year</label>
                <input wire:model.live="yearFilter" type="number" placeholder="All years" class="mt-1 w-28 rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Status</label>
                <select wire:model.live="statusFilter" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[12rem]">
                <label class="text-sm font-medium">Office / Department</label>
                <div class="mt-1 flex flex-wrap items-center gap-2 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <span>{{ $department?->department ?? 'Unassigned' }}</span>
                    @if (!empty($modeLabel))
                        <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ !empty($isCno) ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-200 text-slate-700' }}">
                            {{ $modeLabel }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Updated</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($schedules as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            {{ $item->year }}-{{ str_pad((string) $item->month, 2, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium uppercase text-slate-700">{{ $item->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ optional($item->updated_at)->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a
                                href="{{ route('schedule.show', $item) }}"
                                class="inline-flex rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-slate-500">
                            @if (!empty($isCno))
                                No schedules yet. Use <strong>Import from NDOS</strong> to load approved months.
                            @else
                                No schedules yet. Use <strong>Generate New Schedule</strong> to create a draft.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($schedules->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>

    @if ($showGenerateModal && empty($isCno))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" wire:click.self="closeGenerateModal">
            <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="generate-schedule-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="generate-schedule-title" class="text-lg font-semibold text-slate-900">Generate New Schedule</h3>
                        <p class="mt-1 text-sm text-slate-600">Choose how to build the draft, then period and employee type.</p>
                    </div>
                    <button type="button" wire:click="closeGenerateModal" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">&times;</button>
                </div>

                <div class="mt-4 space-y-3">
                    <p class="text-sm font-medium text-slate-800">Generation mode</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer flex-col rounded-lg border p-3 transition {{ $generationMode === 'automated' ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600' : 'border-slate-200 hover:border-slate-300' }}">
                            <span class="flex items-center gap-2">
                                <input type="radio" wire:model.live="generationMode" value="automated" class="text-blue-700">
                                <span class="text-sm font-semibold text-slate-900">Automated (Beta)</span>
                            </span>
                            <span class="mt-1 text-xs leading-5 text-slate-600">Automatically allocate shifts from weekly duties, templates, and staffing rules.</span>
                        </label>
                        <label class="flex cursor-pointer flex-col rounded-lg border p-3 transition {{ $generationMode === 'manual' ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600' : 'border-slate-200 hover:border-slate-300' }}">
                            <span class="flex items-center gap-2">
                                <input type="radio" wire:model.live="generationMode" value="manual" class="text-blue-700">
                                <span class="text-sm font-semibold text-slate-900">Manual</span>
                            </span>
                            <span class="mt-1 text-xs leading-5 text-slate-600">Create blank (OFF) shifts for each employee so you can assign duties yourself.</span>
                        </label>
                    </div>
                    @error('generationMode') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Year</label>
                        <input wire:model="year" type="number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('year') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium">Month</label>
                        <input wire:model="month" type="number" min="1" max="12" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('month') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Office / Department</label>
                        <div class="mt-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            {{ $department?->department ?? 'Unassigned' }}
                            @if (!empty($modeLabel))
                                <span class="ml-2 rounded bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ $modeLabel }}</span>
                            @endif
                        </div>
                    </div>
                    @if ($generationMode === 'automated')
                        <div class="sm:col-span-2">
                            <label class="text-sm font-medium">Template</label>
                            <select wire:model="schedule_template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Auto/default</option>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium">Employee Type</label>
                        <select wire:model="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($employeeTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('employeeTypeFilter') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="closeGenerateModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="generate"
                        wire:loading.attr="disabled"
                        wire:target="generate"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="generate">
                            {{ $generationMode === 'manual' ? 'Create Blank Draft' : 'Generate Draft' }}
                        </span>
                        <span wire:loading wire:target="generate">Generating…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
