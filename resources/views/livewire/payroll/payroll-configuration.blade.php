<section class="payroll-configuration max-w-6xl space-y-4" x-data="{
    configurationStep: @entangle('configurationStep'),
    reviewStep: 'details',
    reviewProceedAnyway: false,
    reviewRevision: 0,
    openStepReview(proceedAnyway = false) {
        this.reviewStep = this.configurationStep;
        this.reviewProceedAnyway = proceedAnyway;
        this.reviewRevision++;
        erpOverlay.open($wire, 'configuration-step-review', {}, false);
    },
    selectedLabels(field) {
        void this.reviewRevision;
        const select = document.querySelector('.payroll-configuration select[data-review-field=' + field + '], .payroll-configuration select[data-model=' + field + ']');
        return select ? Array.from(select.selectedOptions).map(option => option.textContent.trim()).join(', ') : '';
    },
    selectedLeaveLabels() {
        void this.reviewRevision;
        return Array.from(document.querySelectorAll('.payroll-configuration input[data-review-leave]:checked')).map(input => input.closest('label').textContent.trim()).join(', ');
    },
    confirmStepReview() {
        erpOverlay.close('configuration-step-review');
        if (this.reviewProceedAnyway) return $wire.continueToPayrollGeneration();
        return this.reviewStep === 'review' ? $wire.submitConfiguration() : $wire.nextConfigurationStep();
    }
}">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Payroll Configuration</h2>
            <p class="text-sm text-slate-600">Complete the configuration steps before opening payroll generation.</p>
        </div>
        <x-payroll-generation-guide :section="'configuration-'.$configurationStep" />
    </div>

    <form novalidate x-on:submit.prevent="openStepReview()" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <nav class="configuration-progress border-b border-slate-200 p-4" aria-label="Payroll configuration progress">
            <ol class="grid gap-3 sm:grid-cols-2 {{ $isStandalone ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
                @foreach ($configurationSteps as $key => $label)
                    <li class="configuration-progress-item" :class="{ 'is-current': configurationStep === @js($key) }" :aria-current="configurationStep === @js($key) ? 'step' : null">
                        <span class="configuration-progress-number">{{ $loop->iteration }}</span>
                        <span class="text-sm font-semibold">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        </nav>
        <div class="p-4 sm:p-6">
            <div x-show="configurationStep === 'details'" class="space-y-4" role="region" aria-label="Run details">

                <div>
                    <h3 class="text-sm font-semibold uppercase text-slate-500">Run Details</h3>
                </div>

                <div class="grid gap-3 md:grid-cols-3" x-data="{ payrollMonth: @entangle('period').live, gsisDays: @entangle('gsisDays') }"
                    x-effect="if (payrollMonth) { const [year, month] = payrollMonth.split('-').map(Number); gsisDays = new Date(year, month, 0).getDate(); }">
                    <div>
                        <label class="text-sm font-medium">Payroll Month</label>
                        <input x-model="payrollMonth" type="month"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('period')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">No of Day Paid</label>
                        <input wire:model="workingDays" type="number" min="1" max="31"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('workingDays')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">GSIS No. of Days</label>
                        <input x-model="gsisDays" type="number" readonly
                            class="mt-1 w-full cursor-not-allowed rounded-md border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600">
                        <p class="mt-1 text-xs text-slate-500">Automatically based on the selected payroll month.</p>
                        @error('gsisDays')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-md border border-slate-200">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-3 py-3">
                        <div>
                            <div class="text-sm font-medium">Holidays &amp; Work Suspensions</div>
                            <p class="mt-1 text-xs text-slate-500">Calendar entries for {{ $periodLabel }} used by DTR
                                and MRA processing.</p>
                        </div>
                        @if ($canManageHolidays)
                            <div class="flex flex-wrap gap-2">
                            @if ($isStandalone)
                                <button type="button" wire:click="syncHolidays" wire:loading.attr="disabled" wire:target="syncHolidays"
                                    class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="syncHolidays">Sync holidays from HRIS</span>
                                    <span wire:loading wire:target="syncHolidays" role="status">Syncing holidays…</span>
                                </button>
                            @endif
                            <button type="button"
                                x-on:click="erpOverlay.open($wire, 'payroll-holiday', { holidayEditingId: null, holidayDate: @js($period . '-01'), holidayName: '', holidayType: 'REGULAR', holidayScope: 'FULL_DAY', holidayIsPaid: true, holidayIsActive: true })"
                                class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50">
                                Add calendar entry
                            </button>
                            </div>
                        @endif
                    </div>
                    @error('holiday_sync')
                        <p class="px-3 py-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                    @if (session('holiday_status'))
                        <div
                            class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                            {{ session('holiday_status') }}</div>
                    @endif
                    <div class="divide-y divide-slate-100">
                        @forelse ($holidays as $holiday)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5">
                                <div class="flex min-w-0 items-start gap-3">
                                    <time class="w-12 shrink-0 text-center">
                                        <span
                                            class="block text-xs font-semibold uppercase text-slate-500">{{ $holiday->holiday_date->format('D') }}</span>
                                        <span
                                            class="block text-sm font-semibold text-slate-800">{{ $holiday->holiday_date->format('M d') }}</span>
                                    </time>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-slate-800">{{ $holiday->name }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            {{ $holiday->holiday_type === 'WORK_SUSPENSION' ? 'Work suspension' : ucfirst(strtolower($holiday->holiday_type)) . ' holiday' }}
                                            · {{ str($holiday->holiday_scope)->replace('_', ' ')->lower()->title() }}
                                            · {{ $holiday->is_paid ? 'Paid' : 'Unpaid' }}
                                            @unless ($holiday->is_active)
                                                · Inactive
                                            @endunless
                                        </div>
                                    </div>
                                </div>
                                @if ($canManageHolidays)
                                    <button type="button"
                                        x-on:click="erpOverlay.open($wire, 'payroll-holiday', { holidayEditingId: {{ $holiday->id }}, holidayDate: @js($holiday->holiday_date->format('Y-m-d')), holidayName: @js($holiday->name), holidayType: @js($holiday->holiday_type), holidayScope: @js($holiday->holiday_scope), holidayIsPaid: @js((bool) $holiday->is_paid), holidayIsActive: @js((bool) $holiday->is_active) }, true)"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Edit
                                    </button>
                                @endif
                            </div>
                        @empty
                            <div class="px-3 py-5 text-center text-xs text-slate-500">No holidays or work suspensions
                                recorded for this payroll month.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 p-3">
                    <div class="text-sm font-medium">Inclusive Dates for Leaves</div>
                    <p class="mt-1 text-xs text-slate-500">Only HRIS leave dates within this range will be processed.
                        Dates finalized in an earlier payroll run remain blocked.</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium text-slate-600">From</label>
                            <input wire:model="leavePeriodStart" type="date"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('leavePeriodStart')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">To</label>
                            <input wire:model="leavePeriodEnd" type="date"
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('leavePeriodEnd')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>


            </div>
            <div x-show="configurationStep === 'scope'" x-cloak class="space-y-4" role="region" aria-label="Payroll scope">

                <div>
                    <h3 class="text-sm font-semibold uppercase text-slate-500">Payroll Scope</h3>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Payroll Type</label>
                        <select wire:model="payrollType" data-review-field="payrollType"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm">
                            @foreach ($payrollTypes as $type)
                                <option value="{{ $type->code }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('payrollType')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Employee Type</label>
                        <select data-select2-searchable data-model="employeeTypeFilter" data-defer-request="true"
                            data-placeholder="Select employee types" multiple
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm">
                            @foreach ($employeeTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(in_array($value, $employeeTypeFilter, true))>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('employeeTypeFilter')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        @error('employeeTypeFilter.*')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div>
                        <label class="text-sm font-medium">Division</label>
                        <select data-select2-searchable data-model="selectedDivisionIds" data-defer-request="false"
                            data-placeholder="Search and select divisions" multiple
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm">
                            @foreach ($divisions as $division)
                                <option value="{{ $division->division_id }}" @selected(in_array((int) $division->division_id, $selectedDivisionIds, true))>
                                    {{ $division->division }}</option>
                            @endforeach
                        </select>
                        @error('selectedDivisionIds')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        @error('selectedDivisionIds.*')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Department <span
                                class="font-normal text-slate-500">(Optional)</span></label>
                        <select data-select2-searchable data-model="selectedDepartmentIds" data-defer-request="true"
                            @disabled($selectedDivisionIds === [])
                            data-placeholder="Search and select departments" multiple
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm">
                            @foreach ($departments as $department)
                                <option value="{{ $department->department_id }}" @selected(in_array((int) $department->department_id, $selectedDepartmentIds, true))>
                                    {{ $department->department }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Choose divisions first. Only their departments are shown; leave empty to include all of them.</p>
                        @error('selectedDepartmentIds')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        @error('selectedDepartmentIds.*')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>



                <div class="rounded-md border border-slate-200 p-3">
                    <div class="text-sm font-medium">Inclusive Leave Types</div>
                    <p class="mt-1 text-xs text-slate-500">Checked leave types are included in this payroll run.</p>
                    <div class="mt-2 grid gap-x-3 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($leaveTypes as $leaveType)
                            <label class="flex min-h-7 items-center gap-2 text-xs text-slate-700">
                                <input wire:model="selectedLeaveTypeIds" type="checkbox" data-review-leave
                                    value="{{ $leaveType->leave_type_id }}"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="leading-tight">{{ $leaveType->leave_name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedLeaveTypeIds')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('selectedLeaveTypeIds.*')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div x-show="configurationStep === 'preparation'" x-cloak role="region" aria-label="Prepare payroll data">
                @if ($isStandalone)
                    <section class="border-t border-slate-200 py-4" aria-labelledby="api-preparation-title">
                        <h3 id="api-preparation-title" class="text-sm font-semibold text-slate-900">Prepare payroll data from HRIS</h3>
                        <p class="mt-1 text-sm text-slate-600">Complete these steps for the payroll scope and run details selected earlier. Employee requests use scoped IDs, salary requests use the payroll year, and leave requests use the cutoff dates.</p>
                        <ol class="mt-4 divide-y divide-slate-200">
                            @foreach ($apiPreparationSteps as $key => $step)
                                @php
                                    $result = $apiPreparationResults[$key] ?? null;
                                    $previousKey = array_keys($apiPreparationSteps)[$loop->index - 1] ?? null;
                                    $available = $loop->first || isset($apiPreparationResults[$previousKey]);
                                @endphp
                                <li class="py-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <h4 class="text-sm font-semibold text-slate-900">Step {{ $loop->iteration }} — {{ $step['title'] }}</h4>
                                            <p class="mt-1 text-xs text-slate-600">{{ $step['description'] }}</p>
                                            @if ($key === 'timekeeping')
                                                <label class="mt-3 block text-xs font-medium">Timekeeping source
                                                    <select wire:model="timekeepingSyncSource" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                                        <option value="api">HRIS API attendance summaries</option>
                                                        <option value="hris_db">HRIS database DTR punches</option>
                                                    </select>
                                                </label>
                                                @error('timekeepingSyncSource') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                            @endif
                                            @if ($result)
                                                <p class="mt-2 text-xs font-medium text-emerald-700" role="status">Complete · Batch #{{ $result['batch'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    @foreach ($result['counts'] as $table => $count)
                                                        @if (is_numeric($count)) {{ $table }}: {{ $count }}{{ !$loop->last ? ' · ' : '' }} @endif
                                                    @endforeach
                                                </p>
                                            @elseif (!$available)
                                                <p class="mt-2 text-xs text-slate-500">Complete the previous step to unlock.</p>
                                            @else
                                                <p class="mt-2 text-xs text-slate-500">Ready to synchronize</p>
                                            @endif
                                        </div>
                                        @if ($canSyncPayrollInputs)
                                            <button type="button" wire:click="syncApi('{{ $key }}')" wire:loading.attr="disabled" @disabled(!$available)
                                                class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50">
                                                {{ $result ? 'Sync again' : 'Sync '.$step['title'] }}
                                            </button>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <p wire:loading wire:target="syncApi" class="mt-2 text-sm text-blue-700" role="status">Synchronizing HRIS data. Please wait...</p>
                        @if (!$canSyncPayrollInputs)
                            <p class="mt-2 text-sm text-slate-600">Your account needs payroll generation access to run these steps.</p>
                        @endif
                        @if (count($apiPreparationResults) === count($apiPreparationSteps))
                            <p class="mt-3 text-sm font-medium text-emerald-700">HRIS preparation complete. Review the scope and timekeeping, then proceed to generation.</p>
                        @endif
                        <p class="mt-3 text-xs text-slate-500">Leaves are filtered by cutoff and departments, then restricted locally to scoped employees. Step 4 loads the month before payroll: API summaries use the HRIS database only to fill a null DTR-day count; database punches require DTR encoding and MRA finalization. Previously prepared data can be used without starting a new sync.</p>
                        @error('sync') <p role="alert" class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
                    </section>
                @endif
            </div>
            <div x-show="configurationStep === 'review'" x-cloak class="space-y-5" role="region" aria-label="Review payroll configuration">
                <h3 class="text-base font-semibold">Review before opening payroll generation</h3>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-xs text-slate-500">Payroll month</dt><dd class="mt-1 text-sm font-semibold">{{ $periodLabel }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Payroll type</dt><dd class="mt-1 text-sm font-semibold">{{ $payrollTypes->firstWhere('code', $payrollType)?->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Employee types</dt><dd class="mt-1 text-sm font-semibold">{{ collect($employeeTypeFilter)->map(fn ($type) => $employeeTypeOptions[$type] ?? $type)->implode(', ') }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Divisions</dt><dd class="mt-1 text-sm font-semibold">{{ $divisions->whereIn('division_id', $selectedDivisionIds)->pluck('division')->implode(', ') ?: 'None selected' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Departments</dt><dd class="mt-1 text-sm font-semibold">{{ $departments->whereIn('department_id', $selectedDepartmentIds)->pluck('department')->implode(', ') ?: 'All departments in selected divisions' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Days paid / GSIS days</dt><dd class="mt-1 text-sm font-semibold">{{ $workingDays }} / {{ $gsisDays }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Leave cutoff</dt><dd class="mt-1 text-sm font-semibold">{{ $leavePeriodStart }} through {{ $leavePeriodEnd }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Included leave types</dt><dd class="mt-1 text-sm font-semibold">{{ $leaveTypes->whereIn('leave_type_id', $selectedLeaveTypeIds)->pluck('leave_name')->implode(', ') ?: 'None' }}</dd></div>
                    @if ($isStandalone)
                        <div><dt class="text-xs text-slate-500">Data preparation</dt><dd class="mt-1 text-sm font-semibold">{{ count($apiPreparationResults) === count($apiPreparationSteps) ? 'All four sync steps complete' : 'Using previously prepared local data' }}</dd></div>
                    @endif
                </dl>
                <p class="text-sm text-slate-600">The next screen opens the main generation steps. Attendance and MRA checks still apply.</p>
            </div>
        </div>

        <div x-show="configurationStep === 'review'" x-cloak>
        @if ($showExistingGenerationNotice)
            <div class="border-t border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <div class="font-semibold">Existing payroll generation found</div>
                <p class="mt-1 text-amber-900">
                    Review the existing record before creating another payroll generation with this configuration.
                </p>
                <div class="mt-3 divide-y divide-amber-200 rounded-md border border-amber-200 bg-white">
                    @foreach ($existingGenerations as $existing)
                        <div class="flex flex-wrap items-start justify-between gap-2 px-3 py-2.5">
                            <div>
                                <div class="font-medium text-slate-900">{{ $existing['label'] }}</div>
                                <div class="text-xs text-slate-600">{{ $existing['description'] }}</div>
                            </div>
                            <div class="text-right text-xs text-slate-600">
                                <div>{{ $existing['date'] ?? 'Date unavailable' }}</div>
                                @if ($existing['by'])
                                    <div>By {{ $existing['by'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3">
            <button type="button" x-show="configurationStep !== 'details'" x-cloak wire:click="previousConfigurationStep" wire:loading.attr="disabled" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">Back</button>
            <span x-show="configurationStep === 'details'" class="text-xs text-slate-500">Start with the payroll month and leave cutoff.</span>
            <button type="button" x-show="configurationStep !== 'review'" x-on:click="openStepReview()" wire:loading.attr="disabled" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                <span wire:loading.remove wire:target="nextConfigurationStep">Review &amp; Continue</span><span wire:loading wire:target="nextConfigurationStep">Checking...</span>
            </button>
            <div x-show="configurationStep === 'review'" x-cloak class="flex flex-wrap gap-2">
            @if ($showExistingGenerationNotice)
                <button type="button" wire:click="dismissExistingGenerationNotice"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    Review Configuration
                </button>
                <button type="button" x-on:click="openStepReview(true)" wire:loading.attr="disabled"
                    wire:target="continueToPayrollGeneration,syncApi"
                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="continueToPayrollGeneration">Proceed Anyway</span>
                    <span wire:loading wire:target="continueToPayrollGeneration">Opening...</span>
                </button>
            @else
                <button type="submit" wire:loading.attr="disabled" wire:target="submitConfiguration,syncApi"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="submitConfiguration">Proceed to Payroll Generation</span>
                    <span wire:loading wire:target="submitConfiguration">Preparing...</span>
                </button>
            @endif
            </div>
        </div>
    </form>

    <x-setup-form-modal name="configuration-step-review" title="Review and continue" size="lg">
        <form x-on:submit.prevent="confirmStepReview()" class="payroll-configuration space-y-5">
            <p class="text-sm text-slate-600" x-text="reviewStep === 'review' ? 'Confirm the configuration before opening payroll generation.' : 'Review this step before continuing. Choose Back to edit to change any selections.'"></p>

            <section x-show="reviewStep === 'details' || reviewStep === 'review'" class="space-y-3">
                <h3 class="text-sm font-semibold">Run details</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Payroll month</dt><dd class="mt-1 text-sm font-medium" x-text="$wire.period || 'No entry'"></dd></div>
                    <div><dt class="text-xs text-slate-500">Days paid / GSIS days</dt><dd class="mt-1 text-sm font-medium" x-text="(($wire.workingDays ?? '') === '' ? 'No entry' : $wire.workingDays) + ' / ' + (($wire.gsisDays ?? '') === '' ? 'No entry' : $wire.gsisDays)"></dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Leave cutoff</dt><dd class="mt-1 text-sm font-medium" x-text="($wire.leavePeriodStart || 'No entry') + ' through ' + ($wire.leavePeriodEnd || 'No entry')"></dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500">Holidays &amp; Work Suspensions</dt>
                        <dd class="mt-2">
                            <ul class="space-y-2 text-sm">
                                @forelse ($holidays as $holiday)
                                    <li>
                                        <div class="font-medium">{{ $holiday->holiday_date->format('M d, Y') }} — {{ $holiday->name }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            {{ $holiday->holiday_type === 'WORK_SUSPENSION' ? 'Work suspension' : ucfirst(strtolower($holiday->holiday_type)).' holiday' }}
                                            · {{ str($holiday->holiday_scope)->replace('_', ' ')->lower()->title() }}
                                            · {{ $holiday->is_paid ? 'Paid' : 'Unpaid' }}
                                            · {{ $holiday->is_active ? 'Active' : 'Inactive' }}
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-slate-500">No entries for the selected payroll month.</li>
                                @endforelse
                            </ul>
                        </dd>
                    </div>
                </dl>
            </section>

            <section x-show="reviewStep === 'scope' || reviewStep === 'review'" class="space-y-3">
                <h3 class="text-sm font-semibold">Payroll scope</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Payroll type</dt><dd class="mt-1 text-sm font-medium" x-text="selectedLabels('payrollType') || 'No entry'"></dd></div>
                    <div><dt class="text-xs text-slate-500">Employee types</dt><dd class="mt-1 text-sm font-medium" x-text="selectedLabels('employeeTypeFilter') || 'No entry'"></dd></div>
                    <div><dt class="text-xs text-slate-500">Divisions</dt><dd class="mt-1 text-sm font-medium" x-text="selectedLabels('selectedDivisionIds') || 'No entry'"></dd></div>
                    <div><dt class="text-xs text-slate-500">Departments</dt><dd class="mt-1 text-sm font-medium" x-text="selectedLabels('selectedDepartmentIds') || 'No entry — includes all departments in selected divisions'"></dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Included leave types</dt><dd class="mt-1 text-sm font-medium" x-text="selectedLeaveLabels() || 'No entry'"></dd></div>
                </dl>
            </section>

            @if ($isStandalone)
                <section x-show="reviewStep === 'preparation' || reviewStep === 'review'" class="space-y-3">
                    <h3 class="text-sm font-semibold">Data preparation</h3>
                    <p class="text-sm"><span class="text-slate-500">Selected timekeeping sync source:</span> <span x-text="$wire.timekeepingSyncSource === 'api' ? 'HRIS API attendance summaries' : 'HRIS database DTR punches'"></span></p>
                    <ul class="divide-y divide-slate-200">
                        @foreach ($apiPreparationSteps as $key => $step)
                            <li class="flex flex-wrap justify-between gap-2 py-2 text-sm">
                                <span>{{ $step['title'] }}</span>
                                @if (isset($apiPreparationResults[$key]))
                                    <span class="font-medium text-emerald-700">Complete · Batch #{{ $apiPreparationResults[$key]['batch'] }}</span>
                                @else
                                    <span class="text-slate-500">Not synchronized in this configuration</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-slate-500">If you started syncing, complete all four steps. API summaries require attendance for every scoped employee in the previous month. Database punches require DTR encoding and MRA finalization.</p>
                </section>
            @endif

            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                <button type="button" x-on:click="erpOverlay.close('configuration-step-review')" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700">Back to edit</button>
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white" x-text="reviewStep === 'review' ? 'Confirm & Open Generation' : 'Confirm & Continue'"></button>
            </div>
        </form>
    </x-setup-form-modal>

    @if ($canManageHolidays)
        <x-setup-form-modal name="payroll-holiday" title="New Calendar Entry" edit-title="Edit Calendar Entry"
            size="sm">
            <form wire:submit="saveHoliday" class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Date</span>
                    <input wire:model="holidayDate" type="date"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('holidayDate')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Name</span>
                    <input wire:model="holidayName" type="text"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                        placeholder="e.g. National Heroes Day">
                    @error('holidayName')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Entry type</span>
                        <select wire:model="holidayType"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="REGULAR">Regular holiday</option>
                            <option value="SPECIAL">Special holiday</option>
                            <option value="WORK_SUSPENSION">Work suspension</option>
                        </select>
                        @error('holidayType')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Coverage</span>
                        <select wire:model="holidayScope"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="FULL_DAY">Full day</option>
                            <option value="FIRST_HALF">First half</option>
                            <option value="SECOND_HALF">Second half</option>
                        </select>
                        @error('holidayScope')
                            <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="inline-flex min-h-9 items-center gap-2 text-sm font-medium text-slate-700">
                        <input wire:model="holidayIsPaid" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                        <span>Paid day</span>
                    </label>
                    <label class="inline-flex min-h-9 items-center gap-2 text-sm font-medium text-slate-700">
                        <input wire:model="holidayIsActive" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                        <span>Active</span>
                    </label>
                </div>
                <p class="text-xs text-slate-500">Changes affect the shared DTR and MRA calendar for all employees.</p>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button x-on:click="erpOverlay.close('payroll-holiday')" type="button"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveHoliday"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveHoliday"
                            x-text="editing ? 'Update entry' : 'Save entry'"></span>
                        <span wire:loading wire:target="saveHoliday">Saving...</span>
                    </button>
                </div>
            </form>
        </x-setup-form-modal>
    @endif
</section>
