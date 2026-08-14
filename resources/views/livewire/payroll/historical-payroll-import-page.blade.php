<div class="space-y-5 p-5 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Payroll history</p><h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">Import past payroll</h1><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Preserve workbook results and compare them with a system-generated payroll for the same period and scope.</p></div>
        <a href="{{ route('payroll.history.imports') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Past payroll imports</a>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @php($workflowSteps = [
        1 => ['label' => 'Upload', 'description' => 'Choose workbook'],
        2 => ['label' => 'Sheets', 'description' => 'Select worksheets'],
        3 => ['label' => 'Organizations', 'description' => 'Map HRIS scope'],
        4 => ['label' => 'Reconcile', 'description' => 'Compare results'],
        5 => ['label' => 'Finalize', 'description' => 'Save history'],
    ])
    <nav aria-label="Historical payroll import steps" class="historical-stepper rounded-xl border border-slate-200 bg-white px-3 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <ol class="grid grid-cols-5">
            @foreach($workflowSteps as $number => $step)
                @php($state = $workflowStep === $number ? 'active' : ($workflowStep > $number ? 'completed' : 'upcoming'))
                <li class="historical-step-item relative min-w-0 {{ $state === 'completed' ? 'historical-step-item--completed' : '' }}">
                    @if(!$loop->last)<span aria-hidden="true" class="historical-step-connector"></span>@endif
                    <button type="button" wire:click="goToWorkflowStep({{ $number }})" @disabled((!$import && $number > 1) || ($import && $number === 1)) @if($state === 'active') aria-current="step" @endif class="historical-step historical-step--{{ $state }} relative z-10 mx-auto flex w-[calc(100%-8px)] min-w-0 items-center gap-2 rounded-xl px-2 py-2.5 text-left transition sm:px-3">
                        <span class="historical-step-number grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-extrabold">{{ $state === 'completed' ? '✓' : $number }}</span>
                        <span class="min-w-0">
                            <span class="historical-step-label block truncate text-xs font-bold sm:text-sm">{{ $step['label'] }}</span>
                            <span class="historical-step-description mt-0.5 hidden truncate text-[11px] xl:block">{{ $step['description'] }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    @unless($import)
        <form wire:submit="preview" x-data="{ uploading: false, uploadProgress: 0, dragging: false }" x-on:livewire-upload-start="uploading = true; uploadProgress = 0" x-on:livewire-upload-progress="uploadProgress = $event.detail.progress" x-on:livewire-upload-finish="uploadProgress = 100; window.setTimeout(() => uploading = false, 350)" x-on:livewire-upload-error="uploading = false; uploadProgress = 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100">Stage workbook</h2>
                <p class="mt-1 text-sm text-slate-500">Upload a finalized payroll workbook, then identify its period and payroll type.</p>
            </div>
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,0.55fr)]">
                <div class="flex min-w-0 flex-col">
                    <div class="mb-2 flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">Excel workbook</span><span class="text-xs text-slate-400">Maximum 100 MB</span></div>
                    <input x-ref="workbookInput" id="historical-payroll-workbook" type="file" wire:model="file" accept=".xlsx,.xlsm,.xls" class="sr-only">
                    @if($file)
                        <div class="historical-import-file-card flex min-h-72 flex-1 items-center justify-center rounded-xl border p-5">
                            <div class="w-full text-center">
                                <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/></svg>
                                </div>
                                <p class="mt-3 break-all text-sm font-bold text-slate-900 dark:text-white">{{ $file->getClientOriginalName() }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format($file->getSize() / 1048576, 2) }} MB · Ready to preview</p>
                                <div class="mt-4 flex justify-center gap-2">
                                    <button type="button" x-on:click="$refs.workbookInput.click()" class="historical-import-file-action historical-import-file-action--replace rounded-lg border px-3 py-2 text-xs font-bold">Replace file</button>
                                    <button type="button" wire:click="clearFile" class="historical-import-file-action historical-import-file-action--remove rounded-lg border px-3 py-2 text-xs font-bold">Remove</button>
                                </div>
                            </div>
                        </div>
                    @else
                        <div role="button" tabindex="0" x-on:click="$refs.workbookInput.click()" x-on:keydown.enter.prevent="$refs.workbookInput.click()" x-on:keydown.space.prevent="$refs.workbookInput.click()" x-on:dragenter.prevent="dragging = true" x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="if (!$el.contains($event.relatedTarget)) dragging = false" x-on:drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { const transfer = new DataTransfer(); transfer.items.add($event.dataTransfer.files[0]); $refs.workbookInput.files = transfer.files; $refs.workbookInput.dispatchEvent(new Event('change', { bubbles: true })) }" x-bind:class="dragging ? 'historical-import-dropzone--active' : ''" class="historical-import-dropzone flex min-h-72 flex-1 cursor-pointer items-center justify-center rounded-xl border-2 border-dashed p-5 text-center transition">
                            <div>
                                <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M20 15v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4"/></svg>
                                </div>
                                <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Drop the payroll workbook here</p>
                                <p class="mt-1 text-xs text-slate-500">or click to browse your computer</p>
                                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">XLSX · XLSM · XLS</p>
                            </div>
                        </div>
                    @endif
                    @error('file') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="historical-import-details-panel rounded-xl border p-4">
                    <h3 class="historical-import-details-title text-sm font-bold">Payroll details</h3>
                    <p class="historical-import-details-muted mt-1 text-xs leading-5">These values identify where the imported snapshots appear in Payroll History.</p>
                    <div class="mt-4 space-y-4">
                        <label class="block"><span class="historical-import-details-label text-xs font-bold uppercase tracking-wide">Payroll period</span><input type="month" wire:model="period" class="historical-import-details-control mt-1.5 block w-full rounded-lg border p-2.5 text-sm"></label>
                        <label class="block"><span class="historical-import-details-label text-xs font-bold uppercase tracking-wide">Payroll type</span><select wire:model="payrollType" class="historical-import-details-control mt-1.5 block w-full rounded-lg border p-2.5 text-sm"><option value="general">General</option><option value="hazard">Hazard</option><option value="medicare">Medicare</option></select></label>
                    </div>
                    <div class="historical-import-details-note mt-5 rounded-lg border p-3 text-xs leading-5">Nothing is added to Payroll History until you review the mappings and confirm the final step.</div>
                </div>
            </div>
            <div x-cloak x-show="uploading" class="mx-5 mt-4 rounded-lg border border-indigo-100 bg-indigo-50 p-3 dark:border-indigo-900 dark:bg-indigo-950/40">
                <div class="historical-import-progress-text flex items-center justify-between text-xs font-bold"><span>Uploading workbook</span><span x-text="`${Math.round(uploadProgress)}%`">0%</span></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-900"><span class="block h-full rounded-full bg-indigo-600 transition-all" x-bind:style="`width: ${uploadProgress}%`"></span></div>
            </div>
            <div wire:loading.flex wire:target="preview" class="mx-5 mt-4 items-center gap-3 rounded-lg border border-indigo-100 bg-indigo-50 p-3 dark:border-indigo-900 dark:bg-indigo-950/40">
                <div class="min-w-0 flex-1">
                    <div class="historical-import-progress-text flex items-center justify-between text-xs font-bold"><span wire:stream="import-processing-label">Starting workbook processing</span><span wire:stream="import-processing-percent">0%</span></div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-900" wire:stream="import-processing-bar"><span class="block h-full rounded-full bg-indigo-600" style="width: 0%"></span></div>
                </div>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 px-5 py-4 dark:border-slate-700"><span class="text-xs text-slate-500">{{ $file ? 'Workbook ready for validation.' : 'Select or drop a workbook to continue.' }}</span><button class="historical-import-primary-action rounded-lg px-5 py-2.5 text-sm font-bold shadow-sm" wire:loading.attr="disabled" wire:target="preview" @disabled(!$file)><span wire:loading.remove wire:target="preview">{{ $file ? 'Preview workbook' : 'Select workbook first' }}</span><span wire:loading wire:target="preview">Processing…</span></button></div>
        </form>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['Sheets',$import->sheet_count],['Rows',$import->row_count],['Matched',$import->matched_count],['Material differences',$import->difference_count],['Status',ucfirst($import->status)]] as [$label,$value])
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"><div class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</div><div class="mt-1 text-xl font-semibold text-slate-900 dark:text-white">{{ $value }}</div></div>
            @endforeach
        </div>

        @if($workflowStep === 2)
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-700"><h2 class="font-semibold text-slate-900 dark:text-white">Map workbook sheets</h2><p class="text-sm text-slate-500">Mapping limits automatic employee matching to the selected division or department.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($import->sheets as $sheet)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div><button type="button" wire:click="selectSheet({{ $sheet->id }})" class="text-left font-semibold {{ $selectedSheetId === $sheet->id ? 'text-indigo-600' : 'text-slate-800 dark:text-slate-200' }}">{{ $sheet->sheet_name }}</button><div class="mt-1 text-xs text-slate-500">{{ $sheet->row_count }} rows · {{ $sheet->matched_count }} matched · {{ $sheet->difference_count }} differences</div><label class="mt-2 inline-flex items-center gap-2 text-xs"><input type="checkbox" wire:model="sheetMappings.{{ $sheet->id }}.included"> Include sheet</label></div>
                        <button type="button" wire:click="saveSheetMapping({{ $sheet->id }})" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-semibold text-indigo-700">Save sheet</button>
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        @if($selectedSheet)
            @if($workflowStep === 3)
            <nav aria-label="Workbook organization mapping progress" class="mb-4 rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-2 flex items-center justify-between gap-3"><div><h2 class="text-sm font-semibold text-slate-900 dark:text-white">Review every included worksheet</h2><p class="text-xs text-slate-500">Each worksheet must be confirmed before reconciliation becomes available.</p></div><span class="text-xs font-semibold text-slate-500">{{ count($reviewedOrganizationSheetIds) }} of {{ $includedOrganizationSheets->count() }} reviewed</span></div>
                <ol class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($includedOrganizationSheets as $sheetStep)
                        @php($sheetReviewed = in_array($sheetStep->id, $reviewedOrganizationSheetIds, true))
                        @php($sheetCurrent = $selectedSheetId === $sheetStep->id)
                        <li>
                            <button type="button" wire:click="selectSheet({{ $sheetStep->id }})" class="flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition {{ $sheetCurrent ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-950/30' : ($sheetReviewed ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/20' : 'border-slate-200 hover:border-indigo-300 dark:border-slate-700') }}">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold {{ $sheetReviewed ? 'bg-emerald-600 text-white' : ($sheetCurrent ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300') }}">{{ $sheetReviewed ? '✓' : $loop->iteration }}</span>
                                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $sheetStep->sheet_name }}</span><span class="block text-xs {{ $sheetReviewed ? 'text-emerald-700 dark:text-emerald-300' : ($sheetCurrent ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-500') }}">{{ $sheetReviewed ? 'Reviewed' : ($sheetCurrent ? 'Reviewing now' : 'Review required') }}</span></span>
                                @if(($sheetUnmappedCounts[$sheetStep->id] ?? 0) > 0)<span class="shrink-0 whitespace-nowrap rounded-full bg-amber-100 px-2 py-1 text-[11px] font-bold text-amber-800">{{ $sheetUnmappedCounts[$sheetStep->id] }} unmapped</span>@else<span class="shrink-0 whitespace-nowrap rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-bold text-emerald-700">0 unmapped</span>@endif
                            </button>
                        </li>
                    @endforeach
                </ol>
            </nav>
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
                    <div><h2 class="font-semibold text-slate-900 dark:text-white">{{ $selectedSheet->sheet_name }} organization mappings</h2><p class="text-sm text-slate-500">Each division and department found in columns C and D is mapped independently.</p></div>
                    <div class="flex flex-wrap items-end gap-2">
                        <button type="button" wire:click="nextWorkflowStep" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ count($reviewedOrganizationSheetIds) + 1 >= $includedOrganizationSheets->count() ? 'Confirm final sheet' : 'Confirm & review next sheet' }}</button>
                    </div>
                </div>
                <div class="space-y-4 p-4">
                        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                            <div class="historical-org-table-header grid min-w-[1100px] gap-3 px-3 py-2 text-xs font-bold uppercase tracking-wide lg:grid-cols-[minmax(180px,1fr)_minmax(280px,0.85fr)_minmax(300px,1.4fr)_auto]">
                                <span>Workbook organization</span><span>HRIS division</span><span>HRIS department</span><span>Status</span>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($visibleOrganizationMappings as $organizationKey => $mapping)
                                    @php($organizationMatched = ($mapping['match_status'] ?? null) === 'exact' && filled($mapping['division_id'] ?? null))
                                    <div wire:key="organization-{{ $selectedSheet->id }}-{{ $organizationKey }}" data-historical-org-row class="grid min-w-[1100px] items-end gap-3 p-3 lg:grid-cols-[minmax(180px,1fr)_minmax(280px,0.85fr)_minmax(300px,1.4fr)_auto]">
                                        <div><div class="whitespace-nowrap text-xs font-semibold uppercase text-slate-500">{{ filled($mapping['source_division'] ?? null) ? $mapping['source_division'] : 'Unspecified division' }}</div><div class="mt-0.5 whitespace-nowrap text-sm font-medium text-slate-800 dark:text-slate-200">{{ filled($mapping['source_department'] ?? null) ? $mapping['source_department'] : 'No department in workbook' }}</div><div class="text-xs text-slate-500">{{ $organizationCounts[$organizationKey] ?? 0 }} employee rows</div><label class="mt-1 inline-flex items-center gap-2 text-xs"><input type="checkbox" wire:model="organizationMappings.{{ $selectedSheet->id }}.{{ $organizationKey }}.included"> Include</label></div>
                                        @if($organizationMatched)
                                            @php($matchedDivision = $divisions->firstWhere('division_id', (int) $mapping['division_id']))
                                            @php($matchedDepartment = $departments->firstWhere('department_id', (int) ($mapping['department_id'] ?? 0)))
                                            <div class="min-w-max"><span class="whitespace-nowrap text-xs font-semibold uppercase text-slate-500">Matched HRIS division</span><div class="mt-1 whitespace-nowrap rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">{{ $matchedDivision?->division }}</div></div>
                                            <div class="min-w-max"><span class="whitespace-nowrap text-xs font-semibold uppercase text-slate-500">Matched HRIS department</span><div class="mt-1 whitespace-nowrap rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">{{ $matchedDepartment?->department ?? 'Division-wide' }}</div></div>
                                            <span class="mb-2 inline-flex whitespace-nowrap rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">Matched · review only</span>
                                        @else
                                            <label><span class="text-xs font-semibold uppercase text-slate-500">HRIS division</span><select data-historical-division data-select2-searchable data-placeholder="Search division" data-model="organizationMappings.{{ $selectedSheet->id }}.{{ $organizationKey }}.division_id" data-defer-request="true" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><option value="">Choose division</option>@foreach($divisions as $division)<option value="{{ $division->division_id }}" @selected((string) ($mapping['division_id'] ?? '') === (string) $division->division_id)>{{ $division->division }}</option>@endforeach</select></label>
                                            <label><span class="text-xs font-semibold uppercase text-slate-500">HRIS department</span><select data-historical-department data-select2-searchable data-placeholder="Search department" data-model="organizationMappings.{{ $selectedSheet->id }}.{{ $organizationKey }}.department_id" data-defer-request="true" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"><option value="">Division-wide</option>@foreach($departments as $department)<option value="{{ $department->department_id }}" data-division-id="{{ $department->division_id }}" @selected((string) ($mapping['department_id'] ?? '') === (string) $department->department_id)>{{ $department->division?->division }} — {{ $department->department }}</option>@endforeach</select></label>
                                            <button type="button" wire:click="saveOrganizationMapping({{ $selectedSheet->id }}, '{{ $organizationKey }}')" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-semibold text-indigo-700">Save mapping</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                </div>
            </section>
            @endif

            @if($workflowStep === 4)
            @if($comparisonDrafts->isNotEmpty())
                <section class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-indigo-950 dark:text-indigo-100">Generated comparison drafts</h2><p class="mt-1 text-sm text-indigo-800 dark:text-indigo-300">Each included worksheet has its own payroll scope, matched employees, formula divisors, and independently finalizable draft.</p></div><button type="button" wire:click="regenerateComparisonDraft" class="rounded-lg border border-indigo-300 px-3 py-2 text-sm font-semibold text-indigo-700 dark:text-indigo-200">Regenerate all</button></div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">@foreach($comparisonDrafts as $draft)<div class="flex items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-white p-3 dark:border-indigo-800 dark:bg-slate-900"><div class="min-w-0"><div class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $draft['sheet_name'] }}</div><div class="text-xs text-slate-500">{{ count(data_get($draft, 'configuration.employee_ids', [])) }} matched employees</div></div><a href="{{ $draft['url'] }}" target="_blank" class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Review draft</a></div>@endforeach</div>
                </section>
            @endif
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-700"><div><h2 class="font-semibold text-slate-900 dark:text-white">{{ $selectedSheet->sheet_name }} reconciliation</h2><p class="text-xs text-slate-500">Positive differences mean the system amount is higher than Excel.</p></div><div class="flex flex-wrap gap-2"><select wire:model.live="selectedSheetId" wire:change="selectSheet($event.target.value)" class="rounded-lg border border-slate-300 p-2 text-sm">@foreach($import->sheets->where('included', true) as $sheet)<option value="{{ $sheet->id }}">{{ $sheet->sheet_name }}</option>@endforeach</select><select wire:model.live="comparisonFilter" class="rounded-lg border border-slate-300 p-2 text-sm"><option value="all">All rows</option><option value="unmatched">Unmatched</option><option value="exact">Exact</option><option value="rounding">Rounding</option><option value="different">Different</option><option value="unavailable">Not calculated</option></select><button wire:click="refreshComparison" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">Refresh comparison</button></div></div>
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-3 py-2 text-left">Excel row / employee</th><th class="px-3 py-2 text-left">HRIS match</th><th class="px-3 py-2 text-left">Comparison</th><th class="px-3 py-2 text-right">Excel gross</th><th class="px-3 py-2 text-right">System gross</th><th class="px-3 py-2 text-right">Excel net</th><th class="px-3 py-2 text-right">System net</th><th class="px-3 py-2 text-left">Differences</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($rows as $row)
                        @php($badge = ['exact'=>'bg-emerald-100 text-emerald-700','rounding'=>'bg-amber-100 text-amber-700','different'=>'bg-red-100 text-red-700','unavailable'=>'bg-slate-100 text-slate-600'][$row->comparison_status] ?? 'bg-slate-100 text-slate-600')
                        <tr><td class="whitespace-nowrap px-3 py-3"><div class="font-medium">{{ $row->source_employee_no }} · {{ $row->source_employee_name }}</div><div class="text-xs text-slate-500">{{ $row->source_division ?: 'Unspecified division' }} · {{ $row->source_department ?: 'No department' }} · Row {{ $row->source_row }}</div></td><td class="px-3 py-3">@if($row->matched_emp_id)<span class="font-medium text-emerald-700">{{ $row->matched_emp_id }}</span><div class="text-xs text-slate-500">{{ str_replace('_',' ',$row->match_status) }}</div>@else<div class="flex min-w-72 gap-2"><input list="employees-{{ $selectedSheet->id }}" wire:model="employeeMappings.{{ $row->id }}" placeholder="Employee ID" class="w-full rounded border border-slate-300 px-2 py-1"><button wire:click="mapEmployee({{ $row->id }})" class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white">Map</button></div>@endif</td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $badge }}">{{ str_replace('_',' ',$row->comparison_status) }}</span></td><td class="px-3 py-3 text-right">{{ number_format($row->workbook_values['gross_compensation'] ?? 0,2) }}</td><td class="px-3 py-3 text-right">{{ isset($row->system_values['gross_compensation']) ? number_format($row->system_values['gross_compensation'],2) : '—' }}</td><td class="px-3 py-3 text-right font-medium">{{ number_format($row->workbook_values['net_pay'] ?? 0,2) }}</td><td class="px-3 py-3 text-right font-medium">{{ isset($row->system_values['net_pay']) ? number_format($row->system_values['net_pay'],2) : '—' }}</td><td class="max-w-sm px-3 py-3 text-xs">@forelse(($row->differences ?? []) as $field => $difference)<div>{{ str($field)->replace('_',' ')->title() }}: <span class="font-semibold {{ abs($difference['difference']) > .05 ? 'text-red-600' : 'text-amber-600' }}">{{ number_format($difference['difference'],2) }}</span></div>@empty<span class="text-slate-400">—</span>@endforelse</td></tr>
                    @endforeach
                    </tbody></table></div>
                <datalist id="employees-{{ $selectedSheet->id }}">@foreach($employeeSuggestions as $employee)<option value="{{ $employee->emp_id }}">{{ $employee->lastname }}, {{ $employee->firstname }}</option>@endforeach</datalist>
                <div class="p-3">{{ $rows->links() }}</div>
            </section>
            @endif
        @endif

        @if($workflowStep === 5 && $import->status !== 'applied')
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4"><h2 class="font-semibold text-amber-900">Finalize historical import</h2><p class="mt-1 text-sm text-amber-800">Excel values become the official historical snapshots. Comparisons remain audit data and do not change current HRIS or recurring payroll setup.</p><div class="mt-3 flex flex-wrap gap-2"><input wire:model="confirmation" placeholder="Type IMPORT {{ $import->id }}" class="rounded-lg border border-amber-300 px-3 py-2 text-sm"><button wire:click="apply" wire:confirm="Import this workbook into finalized Payroll History?" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Import finalized history</button></div></section>
        @endif

        <div class="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-700">
            <button type="button" wire:click="previousWorkflowStep" @disabled($workflowStep <= 2) class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:opacity-40 dark:text-slate-200">Previous</button>
            <span class="text-sm text-slate-500">Step {{ $workflowStep }} of 5</span>
            <button type="button" wire:click="nextWorkflowStep" @disabled($workflowStep >= 5) class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-40">{{ $workflowStep === 3 ? (count($reviewedOrganizationSheetIds) + 1 >= $includedOrganizationSheets->count() ? 'Confirm final sheet' : 'Confirm & next sheet') : 'Next' }}</button>
        </div>
    @endunless
</div>
