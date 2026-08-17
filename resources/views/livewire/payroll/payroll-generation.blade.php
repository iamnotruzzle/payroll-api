@php
    $payrollLoadingTargets = 'applyEmployeeFilter,clearEmployeeFilter,goToStep,nextStep,previousStep';
    $selectedDepartments = $departments->whereIn('department_id', $selectedDepartmentIds ?? []);
    $selectedDivisions = $divisions->whereIn('division_id', $selectedDivisionIds ?? []);
    $scopeLabel = $selectedDepartments->count() === 1
        ? $selectedDepartments->first()->department
        : ($selectedDepartments->count() > 1
            ? $selectedDepartments->count() . ' Departments'
            : ($selectedDivisions->count() === 1
                ? $selectedDivisions->first()->division . ' Division'
                : max(1, $selectedDivisions->count()) . ' Divisions'));
    $canEditCurrentStep = (bool) ($payrollGenerationAccess['can_edit_current_step'] ?? false);
    $canEditStep1HrFields = (bool) ($payrollGenerationAccess['can_edit_step1_hr_fields'] ?? false);
    $canEditStep1Tev = (bool) ($payrollGenerationAccess['can_edit_step1_tev'] ?? false);
    $workbookCompensationLabel = static function ($item): string {
        $name = Illuminate\Support\Str::of((string) $item->name)->lower()->squish()->toString();

        return match (true) {
            str_contains($name, 'subsistence') => 'Subsistence',
            str_contains($name, 'laundry') => 'Laundry',
            str_contains($name, 'pera'), str_contains($name, 'personal economic relief') => 'PERA',
            default => (string) $item->name,
        };
    };
@endphp

<section
    class="flex h-screen min-h-0 flex-col"
    x-init="applyRowVisibility(); sortPayrollRows(); $nextTick(() => { syncSelectedProgramEmployees(); recalculateGrossCompensationTotals(); })"
    x-data="{
        stepDirty: false,
        programSelectorDrawerOpen: false,
        filtersOpen: @js(! empty($employeeFilterIds)),
        unsavedStepModalOpen: false,
        pendingStep: null,
        modalBusy: false,
        activeStep: @js($currentStep),
        mandatoryDeductionAdjustments: @js($mandatoryDeductionAdjustments),
        employeeFilterIds: @js(array_values($employeeFilterIds)),
        tableSearch: '',
        tableSort: @js($tableSort),
        tableSortDirection: @js($tableSortDirection),
        programSearch: '',
        programRevision: 0,
        programEnabled: @js($deductionPrograms->mapWithKeys(fn ($program) => [(string) $program->id => filter_var($deductionProgramSelections[(string) $program->id]['enabled'] ?? false, FILTER_VALIDATE_BOOL)])),
        programEmployeeOverrides: @js($deductionPrograms->mapWithKeys(fn ($program) => [(string) $program->id => (array) ($deductionProgramSelections[(string) $program->id]['employee_overrides'] ?? [])])),
        selectedProgramId: '',
        programConfigurations: @js($deductionPrograms->mapWithKeys(fn ($program) => [(string) $program->id => [
            'mode' => $deductionProgramSelections[(string) $program->id]['mode'] ?? 'all',
            'amount_mode' => $deductionProgramSelections[(string) $program->id]['amount_mode'] ?? 'program',
            'employee_ids' => array_values((array) ($deductionProgramSelections[(string) $program->id]['employee_ids'] ?? [])),
        ]])),
        unsavedChanges: {},
        get unsavedChangeRows() {
            return Object.values(this.unsavedChanges).slice(0, 10);
        },
        formSteps: [1, 2, 3, 4, 5, 6],
        markStepDirty(currentStep, event) {
            if (!this.formSteps.includes(currentStep) || event.target?.type === 'search') {
                return;
            }

            if (this.recordUnsavedChange(event)) {
                this.stepDirty = true;
            }
        },
        recordUnsavedChange(event) {
            const target = event.target;
            const model = target?.getAttribute('wire:model')
                || target?.getAttribute('wire:model.live')
                || target?.getAttribute('wire:model.blur')
                || target?.getAttribute('wire:model.defer')
                || target?.getAttribute('data-model')
                || target?.closest?.('[data-model]')?.getAttribute('data-model');

            if (!model) {
                return false;
            }

            const row = target?.closest('tr');
            const cells = row ? Array.from(row.querySelectorAll('td')) : [];
            const employeeNo = (row?.dataset?.unsavedEmployeeNo || cells[0]?.innerText || '').trim();
            const employeeName = (row?.dataset?.unsavedEmployeeName || cells[1]?.querySelector('[data-unsaved-employee-name]')?.innerText || cells[1]?.innerText || '').trim();
            const value = target?.type === 'checkbox'
                ? (target.checked ? 'Checked' : 'Unchecked')
                : String(target?.value ?? '').trim();

            this.unsavedChanges[model] = {
                key: model,
                employee: employeeNo || employeeName ? `${employeeNo}${employeeName ? ' - ' + employeeName : ''}` : 'Payroll step',
                field: this.unsavedFieldLabel(model),
                value: value || '-',
            };

            return true;
        },
        unsavedFieldLabel(model) {
            const labels = {
                leavePeriodStart: 'Inclusive Leave Date From',
                leavePeriodEnd: 'Inclusive Leave Date To',
            };

            if (labels[model]) {
                return labels[model];
            }

            const parts = String(model || '').split('.');
            const field = parts[parts.length - 1] || 'field';

            return field
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());
        },
        syncMandatoryDeductions() {
            $wire.set('employeeFilterIds', this.employeeFilterIds, false);
            $wire.set('appliedEmployeeFilterIds', this.employeeFilterIds, false);
            if (this.activeStep === 3) {
                $wire.set('mandatoryDeductionAdjustments', this.mandatoryDeductionAdjustments, false);
            }
        },
        mandatoryAdjustment(employeeId, key) {
            return Number(this.mandatoryDeductionAdjustments?.[employeeId]?.[key] || 0);
        },
        mandatoryResult(employeeId, key, base) {
            return Math.max(0, Number(base || 0) + this.mandatoryAdjustment(employeeId, key));
        },
        mandatoryEmployeeTotal(employeeId, bases) {
            return ['life_retirement', 'phic', 'mandatory_pagibig']
                .reduce((total, key) => total + this.mandatoryResult(employeeId, key, bases?.[key]), 0);
        },
        money(value) {
            return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        setProgramEnabled(programId, enabled) {
            const id = String(programId);
            const config = this.programConfigurations[id] || { mode: 'all', amount_mode: 'program', employee_ids: [] };
            let mode = config.mode || 'all';
            const amountMode = config.amount_mode || 'program';
            if (enabled && amountMode === 'employee' && mode === 'all') {
                mode = 'include';
                config.mode = mode;
            }
            this.programConfigurations[id] = config;
            this.programEnabled[id] = enabled;
            if (!enabled) {
                this.programEmployeeOverrides[id] = {};
                $wire.set(`deductionProgramSelections.${id}.employee_overrides`, {}, false);
            }
            this.programRevision++;
            this.stepDirty = true;
            $wire.set(`deductionProgramSelections.${id}.enabled`, enabled, false);
        },
        selectProgram(programId) {
            this.selectedProgramId = String(programId || '');
            if (this.selectedProgramId && !this.programConfigurations[this.selectedProgramId]) {
                this.programConfigurations[this.selectedProgramId] = { mode: 'all', amount_mode: 'program', employee_ids: [] };
                this.programEnabled[this.selectedProgramId] = false;
                this.programEmployeeOverrides[this.selectedProgramId] = {};
            }
            this.$nextTick(() => {
                window.initPayrollEmployeePickers?.();
                this.syncSelectedProgramEmployees();
            });
        },
        resetProgramSelection() {
            this.selectedProgramId = '';
            const picker = document.getElementById('deduction-program-employee-picker');
            if (picker) {
                Array.from(picker.options).forEach(option => option.selected = false);
                if (window.jQuery) window.jQuery(picker).val(null).trigger('change.select2');
            }
        },
        syncSelectedProgramEmployees() {
            const picker = document.getElementById('deduction-program-employee-picker');
            if (!picker || !this.selectedProgramId) return;
            const values = this.programConfigurations[this.selectedProgramId]?.employee_ids || [];
            Array.from(picker.options).forEach(option => option.selected = values.map(String).includes(String(option.value)));
            if (window.jQuery) window.jQuery(picker).val(values.map(String)).trigger('change.select2');
        },
        updateSelectedProgramEmployees() {
            const picker = document.getElementById('deduction-program-employee-picker');
            if (!picker || !this.selectedProgramId) return;
            this.programConfigurations[this.selectedProgramId].employee_ids = Array.from(picker.selectedOptions).map(option => String(option.value));
            this.programRevision++;
            this.stepDirty = true;
        },
        programApplies(programId, employeeId) {
            this.programRevision;
            const id = String(programId);
            const config = this.programConfigurations[id] || { mode: 'all', amount_mode: 'program', employee_ids: [] };
            const mode = config.mode || 'all';
            const amountMode = config.amount_mode || 'program';
            const effectiveMode = amountMode === 'employee' && mode === 'all' ? 'include' : mode;
            if (effectiveMode === 'all') return true;
            const selected = (config.employee_ids || []).map(String);
            return effectiveMode === 'exclude' ? !selected.includes(String(employeeId)) : selected.includes(String(employeeId));
        },
        setProgramOverride(programId, employeeId, amount) {
            const id = String(programId);
            if (!this.programEmployeeOverrides[id]) this.programEmployeeOverrides[id] = {};
            this.programEmployeeOverrides[id][String(employeeId)] = Math.max(0, Number(amount || 0));
            this.stepDirty = true;
        },
        syncDeductionPrograms() {
            if (![3, 5].includes(this.activeStep)) return;
            Object.entries(this.programEmployeeOverrides).forEach(([programId, overrides]) => {
                $wire.set(`deductionProgramSelections.${programId}.employee_overrides`, overrides, false);
            });
        },
        browserDeductionProgramState() {
            if (![3, 5].includes(this.activeStep)) return {};
            return Object.fromEntries(Object.keys(this.programEnabled).map((programId) => {
                const config = this.programConfigurations[programId] || { mode: 'all', amount_mode: 'program', employee_ids: [] };
                return [programId, {
                    enabled: Boolean(this.programEnabled[programId]),
                    mode: config.mode || 'all',
                    amount_mode: config.amount_mode || 'program',
                    employee_ids: (config.employee_ids || []).map(String),
                }];
            }));
        },
        selectedEmployeeIds() {
            const select = document.getElementById('payroll-employee-filter');
            return select ? Array.from(select.selectedOptions).map((option) => String(option.value)) : [];
        },
        applyEmployeeFilter() {
            this.employeeFilterIds = this.selectedEmployeeIds();
            this.stepDirty = true;
            this.applyRowVisibility();
        },
        clearEmployeeFilter() {
            this.employeeFilterIds = [];
            const select = document.getElementById('payroll-employee-filter');
            if (select) {
                Array.from(select.options).forEach((option) => option.selected = false);
                if (window.jQuery) window.jQuery(select).val(null).trigger('change.select2');
            }
            this.stepDirty = true;
            this.applyRowVisibility();
        },
        employeeVisible(employeeId, searchableText = '') {
            const selected = this.employeeFilterIds.map(String);
            const search = this.tableSearch.trim().toLowerCase();
            return (selected.length === 0 || selected.includes(String(employeeId)))
                && (search === '' || String(searchableText).toLowerCase().includes(search));
        },
        applyRowVisibility() {
            this.$nextTick(() => {
                document.querySelectorAll('[data-payroll-row]').forEach((row) => {
                    const searchable = `${row.dataset.empId || ''} ${row.dataset.employeeName || ''} ${row.dataset.department || ''} ${row.dataset.position || ''}`;
                    row.hidden = !this.employeeVisible(row.dataset.empId, searchable);
                });
                this.recalculateGrossCompensationTotals();
            });
        },
        recalculateGrossCompensationTotals() {
            const table = document.querySelector('[data-gross-compensation-table]');
            if (!table) return;

            const totals = {};
            table.querySelectorAll('tbody tr[data-payroll-row]:not([hidden])').forEach((row) => {
                row.querySelectorAll('[data-gross-total-key]').forEach((cell) => {
                    const key = cell.dataset.grossTotalKey;
                    const value = cell.matches('input')
                        ? cell.value
                        : (cell.dataset.grossTotalValue ?? cell.textContent);
                    totals[key] = (totals[key] || 0) + Number(String(value || 0).replace(/,/g, ''));
                });
            });

            table.querySelectorAll('[data-gross-total-output]').forEach((cell) => {
                cell.textContent = this.money(totals[cell.dataset.grossTotalOutput] || 0);
            });
        },
        sortPayrollRows() {
            this.$nextTick(() => [...new Set(Array.from(document.querySelectorAll('[data-payroll-row]')).map((row) => row.parentElement))].forEach((body) => {
                const rows = Array.from(body.querySelectorAll(':scope > tr[data-payroll-row]'));
                const dataKey = this.tableSort.replace(/_([a-z])/g, (_, character) => character.toUpperCase());
                const direction = this.tableSortDirection === 'desc' ? -1 : 1;
                rows.sort((left, right) => {
                    const a = left.dataset[dataKey] || '';
                    const b = right.dataset[dataKey] || '';
                    const numeric = a !== '' && b !== '' && !Number.isNaN(Number(a)) && !Number.isNaN(Number(b));
                    return direction * (numeric ? Number(a) - Number(b) : a.localeCompare(b, undefined, { sensitivity: 'base' }));
                });
                rows.forEach((row) => body.appendChild(row));
            }));
        },
        toggleSortDirection() {
            this.tableSortDirection = this.tableSortDirection === 'asc' ? 'desc' : 'asc';
            this.sortPayrollRows();
        },
        saveStep() {
            this.syncMandatoryDeductions();
            return $wire.saveStepChanges(this.browserDeductionProgramState()).then(() => {
                this.stepDirty = false;
                this.unsavedChanges = {};
            });
        },
        leaveStep(currentStep, targetStep) {
            if (currentStep === targetStep) {
                return;
            }

            if (this.stepDirty) {
                this.pendingStep = targetStep;
                this.unsavedStepModalOpen = true;
                return;
            }

            $wire.goToStep(targetStep);
        },
        closeUnsavedStepModal() {
            if (this.modalBusy) {
                return;
            }

            this.unsavedStepModalOpen = false;
            this.pendingStep = null;
        },
        discardStepChanges() {
            if (this.pendingStep === null) {
                return;
            }

            this.modalBusy = true;
            return $wire.discardStepChangesAndGoToStep(this.pendingStep).then((changedStep) => {
                if (changedStep === false) {
                    return;
                }

                this.stepDirty = false;
                this.unsavedChanges = {};
                this.unsavedStepModalOpen = false;
                this.pendingStep = null;
            }).finally(() => {
                this.modalBusy = false;
            });
        },
        saveStepAndLeave() {
            if (this.pendingStep === null) {
                return;
            }

            this.modalBusy = true;
            this.syncMandatoryDeductions();
            return $wire.saveStepChangesAndGoToStep(this.pendingStep, this.browserDeductionProgramState()).then((changedStep) => {
                if (changedStep === false) {
                    return;
                }

                this.stepDirty = false;
                this.unsavedChanges = {};
                this.unsavedStepModalOpen = false;
                this.pendingStep = null;
            }).finally(() => {
                this.modalBusy = false;
            });
        },
    }"
>
    <header class="shrink-0 border-b border-[#e4e6ef] bg-white px-4 py-3 sm:px-5 lg:ml-[300px]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold">Payroll Generation</h2>
                    @if (session('draft_success') || $draftNotice)
                        @php
                            $draftBadgeIsSuccess = (bool) session('draft_success');
                            $draftBadgeLabel = $draftBadgeIsSuccess ? 'Draft saved' : 'Draft restored';
                            $draftBadgeTitle = $draftBadgeIsSuccess ? session('draft_success') : $draftNotice;
                        @endphp
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $draftBadgeIsSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-blue-200 bg-blue-50 text-blue-800' }}"
                            title="{{ trim($draftBadgeTitle . ($draftSavedAt ? ' Last saved ' . $draftSavedAt . '.' : '')) }}"
                        >
                            <span class="h-1.5 w-1.5 rounded-full {{ $draftBadgeIsSuccess ? 'bg-emerald-500' : 'bg-blue-500' }}"></span>
                            <span>{{ $draftBadgeLabel }}</span>
                            @if ($draftSavedAt)
                                <span class="font-medium opacity-80">{{ $draftSavedAt }}</span>
                            @endif
                        </span>
                    @endif
                </div>
                <p class="text-sm text-slate-600">
                    {{ $scopeLabel }} · {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $period)->format('F Y') }} · {{ $employeeTypeLabel }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ url('/') }}"
                    class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    x-on:click="if (stepDirty && !window.confirm('You have unsaved payroll changes. Leave this page and discard them?')) $event.preventDefault()"
                    title="Exit payroll generation"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m15 18-6-6 6-6"></path>
                    </svg>
                    <span class="hidden sm:inline">Back to Home</span>
                </a>
                <button type="button" class="erp-theme-toggle shrink-0" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme">
                    <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <a href="{{ route('payroll.generation.configuration', ['division_ids' => implode(',', $selectedDivisionIds ?? []), 'department_ids' => implode(',', $selectedDepartmentIds ?? []), 'division_id' => $divisionId, 'department_id' => $departmentId, 'payroll_type' => \App\Models\Payroll\PayrollType::CODE_GENERAL, 'period' => $period, 'working_days' => $workingDays, 'gsis_days' => $gsisDays, 'leave_type_ids' => $selectedLeaveTypeIds === [] ? 'none' : implode(',', $selectedLeaveTypeIds), 'leave_period_start' => $leavePeriodStart, 'leave_period_end' => $leavePeriodEnd, 'employee_type' => $employeeTypeQueryValue]) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Change Configuration
                </a>
            </div>
        </div>
        @error('draft')
            <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
        @enderror
    </header>

    <div class="min-h-0 flex-1 lg:grid lg:grid-cols-[300px_minmax(0,1fr)]">
    <aside class="payroll-generation-sidebar overflow-x-hidden border-b border-[#e4e6ef] bg-white lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:h-screen lg:w-[300px] lg:overflow-y-auto lg:border-b-0 lg:border-r">
        <div class="space-y-4 p-4">
    <div
        x-cloak
        x-show="unsavedStepModalOpen"
        x-transition.opacity
        class="fixed inset-0 z-[90] bg-slate-900/20 backdrop-blur-sm backdrop-saturate-50"
    ></div>

    <div
        x-cloak
        x-show="unsavedStepModalOpen"
        x-transition.opacity
        class="fixed inset-0 z-[100] flex items-center justify-center px-4 py-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="unsaved-step-title"
        x-on:keydown.escape.window="closeUnsavedStepModal()"
    >
        <div
            x-show="unsavedStepModalOpen"
            x-transition
            x-on:click.outside="closeUnsavedStepModal()"
            class="w-full max-w-3xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl"
        >
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 id="unsaved-step-title" class="text-base font-semibold text-slate-900">Unsaved Changes</h3>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    You have unsaved changes on this payroll step. Save them as a draft before leaving, or discard them and continue.
                </p>
            </div>
            <div class="px-5 py-4">
                <div class="max-h-72 overflow-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Employee</th>
                                <th class="px-3 py-2">Field</th>
                                <th class="px-3 py-2">Unsaved Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="unsavedChangeRows.length === 0">
                                <tr>
                                    <td colspan="3" class="px-3 py-4 text-center text-slate-500">Unsaved payroll step changes are pending.</td>
                                </tr>
                            </template>
                            <template x-for="change in unsavedChangeRows" x-bind:key="change.key">
                                <tr>
                                    <td class="max-w-xs px-3 py-2 font-medium text-slate-900" x-text="change.employee"></td>
                                    <td class="px-3 py-2 text-slate-700" x-text="change.field"></td>
                                    <td class="max-w-xs truncate px-3 py-2 text-slate-700" x-text="change.value"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p x-show="Object.values(unsavedChanges).length > 10" class="mt-2 text-xs text-slate-500">
                    Showing the first 10 changed fields.
                </p>
            </div>
            <div class="flex flex-col-reverse gap-2 px-5 py-4 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    x-on:click="closeUnsavedStepModal()"
                    x-bind:disabled="modalBusy"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    x-on:click="discardStepChanges()"
                    x-bind:disabled="modalBusy"
                    class="rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100 disabled:cursor-wait disabled:opacity-60"
                >
                    Discard Changes
                </button>
                <button
                    type="button"
                    x-on:click="saveStepAndLeave()"
                    x-bind:disabled="modalBusy"
                    class="rounded-md bg-[#5f61e6] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#5254d9] disabled:cursor-wait disabled:opacity-60"
                >
                    Save and Continue
                </button>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-3 py-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Payroll Steps</h3>
                <p class="text-xs text-slate-500">
                    @if (count($employeeFilterIds) > 0)
                        Showing {{ count($employeeFilterIds) }} selected {{ \Illuminate\Support\Str::plural('employee', count($employeeFilterIds)) }}.
                    @else
                        Showing all employees in this payroll scope.
                    @endif
                </p>
            </div>
            <button
                type="button"
                x-on:click="filtersOpen = ! filtersOpen"
                class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                x-bind:aria-expanded="filtersOpen.toString()"
            >
                <span>Filters</span>
                <span x-show="employeeFilterIds.length > 0" x-text="employeeFilterIds.length" class="rounded-full bg-[#5f61e6] px-2 py-0.5 text-xs font-semibold text-white"></span>
                <span class="text-xs text-slate-500" x-text="filtersOpen ? 'Hide' : 'Show'"></span>
            </button>
        </div>

        <div x-cloak x-show="filtersOpen" x-transition class="border-b border-slate-200 bg-slate-50 px-3 py-3">
            <div class="grid min-w-0 gap-2">
                <div class="w-full min-w-0" wire:ignore>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="payroll-employee-filter">
                        Employee Filter
                    </label>
                    <select
                        id="payroll-employee-filter"
                        data-select2-employee-picker
                        data-model="employeeFilterIds"
                        data-defer-request="true"
                        data-placeholder="Select employees"
                        multiple
                        wire:loading.attr="disabled"
                        wire:target="{{ $payrollLoadingTargets }}"
                        class="w-full rounded-md border border-slate-300 text-sm"
                    >
                        @foreach ($employeeFilterOptions as $employee)
                            <option value="{{ $employee['emp_id'] }}" @selected(in_array((string) $employee['emp_id'], $employeeFilterIds, true))>
                                {{ $employee['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        x-on:click="applyEmployeeFilter()"
                        class="w-full rounded-md bg-[#5f61e6] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#5254d9] disabled:cursor-wait disabled:opacity-60"
                    >
                        Filter
                    </button>
                    <button
                        type="button"
                        x-on:click="clearEmployeeFilter()"
                        class="w-full rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">External Employee Registry</div>
                    <p class="mt-1 text-xs text-slate-500">Replaces the persistent payroll-side registry after validation.</p>
                    <div class="mt-2 grid min-w-0 gap-2">
                        <input wire:model="externalRosterFile" type="file" accept=".xlsx,.xls" class="block w-full min-w-0 rounded-md border border-slate-200 bg-white p-2 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                        <button wire:click="previewExternalRoster" type="button" class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium">Validate</button>
                        <button wire:click="exportExternalRosterTemplate" type="button" class="w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium">Template</button>
                        </div>
                        @if ($externalRosterPreview !== [])
                            <button wire:click="confirmExternalRoster" type="button" class="w-full rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Replace Registry</button>
                        @endif
                    </div>
                    @error('externalRosterFile') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    @if (session('external_roster_status')) <div class="mt-1 text-xs text-emerald-700">{{ session('external_roster_status') }}</div> @endif
                    @if ($externalRosterPreview !== [])
                        <div class="mt-2 text-xs text-slate-600">
                            {{ collect($externalRosterPreview)->where('valid', true)->count() }} valid ·
                            {{ collect($externalRosterPreview)->where('valid', false)->count() }} invalid ·
                            {{ collect($externalRosterPreview)->where('name_mismatch', true)->count() }} name warnings
                        </div>
                    @endif
                    @if ($externalOverrides->isNotEmpty())
                        <div class="mt-2 flex max-h-24 flex-wrap gap-1 overflow-auto">
                            @foreach ($externalOverrides as $override)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-[11px] text-amber-800">
                                    {{ $override->emp_id }} · {{ $override->employee_name }}
                                    <button wire:click="removeExternalEmployee({{ $override->id }})" type="button" class="font-bold" aria-label="Remove external employee">×</button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-3 border-t border-slate-200 pt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort rows</label>
                <div class="grid gap-2">
                    <input x-model.debounce.150ms="tableSearch" x-on:input.debounce.150ms="applyRowVisibility()" type="search" placeholder="Search visible employees" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <select x-model="tableSort" x-on:change="sortPayrollRows()" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="employee_name">Employee Name</option>
                        <option value="emp_id">Employee ID</option>
                        <option value="position">Position</option>
                        <option value="basic_salary">Basic Salary</option>
                        <option value="gross">Gross Pay</option>
                        <option value="net_compensation">Net Compensation</option>
                        <option value="net_after_loan_deductions">Final Net Pay</option>
                    </select>
                    <button x-on:click="toggleSortDirection()" type="button" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium">
                        <span x-text="tableSortDirection === 'asc' ? 'Ascending ↑' : 'Descending ↓'"></span>
                    </button>
                </div>
                <div class="mt-2 text-xs text-slate-500"><span x-text="employeeFilterIds.length || @js($unfilteredRowCount)"></span> of {{ $unfilteredRowCount }} employees</div>
            </div>
        </div>

        <div class="grid gap-2 p-3">
            @foreach ($steps as $number => $label)
                @php
                    $stepCanEdit = (bool) ($payrollGenerationAccess['steps'][$number]['can_edit'] ?? false);
                    $stepBadgeLabel = $stepCanEdit ? 'Accessible' : 'Read-only';
                    $stepBadgeClasses = $currentStep === $number
                        ? ($stepCanEdit ? 'bg-white/20 text-white ring-1 ring-white/35' : 'bg-amber-100 text-amber-950')
                        : ($stepCanEdit ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200');
                @endphp
                <button
                    type="button"
                    x-on:click="leaveStep({{ $currentStep }}, {{ $number }})"
                    wire:loading.attr="disabled"
                    wire:target="{{ $payrollLoadingTargets }}"
                    class="flex min-h-20 flex-col justify-between rounded-md border px-3 py-2 text-left text-sm transition {{ $currentStep === $number ? 'border-[#5f61e6] bg-[#5f61e6] font-semibold text-white shadow-sm shadow-[#696cff]/25' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <span class="flex items-center justify-between gap-2 text-xs font-semibold uppercase tracking-wide">
                        <span>Step {{ $number }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] normal-case tracking-normal {{ $stepBadgeClasses }}">{{ $stepBadgeLabel }}</span>
                    </span>
                    <span class="mt-3 block font-medium leading-snug">{{ $label }}</span>
                </button>
            @endforeach
        </div>

    </div>

    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
        <button
            type="button"
            x-on:click="leaveStep({{ $currentStep }}, {{ $currentStep - 1 }})"
            wire:loading.attr="disabled"
            wire:target="{{ $payrollLoadingTargets }}"
            @disabled($currentStep === 1)
            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Previous
        </button>
        <div class="text-center text-xs font-medium text-slate-500">{{ $currentStep }} / {{ count($steps) }}</div>
        <button
            type="button"
            x-on:click="leaveStep({{ $currentStep }}, {{ $currentStep + 1 }})"
            wire:loading.attr="disabled"
            wire:target="{{ $payrollLoadingTargets }}"
            @disabled($currentStep === count($steps))
            class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Next
        </button>
    </div>
        </div>
    </aside>

    <main class="payroll-generation-main h-full min-h-0 min-w-0 overflow-hidden px-3 py-4 sm:px-5 lg:col-start-2">
    <div
        wire:loading.class.remove="hidden"
        wire:target="{{ $payrollLoadingTargets }}"
        class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    >
        <div class="flex items-center gap-2 border-b border-blue-100 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
            Loading payroll rows...
        </div>
        <div class="payroll-table-scroll overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Pay Basis</th>
                        <th class="px-4 py-3">Earnings</th>
                        <th class="px-4 py-3">Deductions</th>
                        <th class="px-4 py-3">Net Pay</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @for ($i = 0; $i < count($steps); $i++)
                        <tr class="animate-pulse">
                            <td class="px-4 py-4">
                                <div class="h-4 w-44 rounded bg-slate-200"></div>
                                <div class="mt-2 h-3 w-24 rounded bg-slate-100"></div>
                            </td>
                            <td class="px-4 py-4"><div class="h-4 w-28 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-36 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-32 rounded bg-slate-200"></div></td>
                            <td class="px-4 py-4"><div class="h-4 w-28 rounded bg-slate-200"></div></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>

    <div
        wire:loading.class="hidden"
        wire:target="{{ $payrollLoadingTargets }}"
        x-on:input="markStepDirty({{ $currentStep }}, $event)"
        x-on:change="markStepDirty({{ $currentStep }}, $event)"
        class="payroll-step-body h-full min-h-0"
    >
    @if ($currentStep === 1)
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="font-semibold">MRA Validation</h3>
                    <p class="text-sm text-slate-600">
                    Source:
                    {{ $previousMraPeriod['start']->format('M d, Y') }} to {{ $previousMraPeriod['end']->format('M d, Y') }}
                    @if ($previousMraReport)
                        · {{ $previousMraReport->status }} by {{ $previousMraReport->generated_by }}
                    @else
                        · no previous month MRA found; current DTR deductions are shown as fallback.
                    @endif
                    </p>
                </div>
                @include('livewire.payroll.partials.step-save-button')
            </div>
            <div class="grid gap-3 border-b border-slate-200 bg-blue-50/50 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_180px_180px_auto] sm:items-end">
                <div>
                    <div class="text-sm font-medium text-slate-800">Inclusive Dates for Leaves</div>
                    <p class="text-xs text-slate-600">Overrides the MRA leave coverage. Previously finalized leave dates are automatically blocked.</p>
                    @if ($leavePeriodAppliedMessage)
                        <p class="mt-1 text-xs font-medium text-blue-700">{{ $leavePeriodAppliedMessage }}</p>
                    @endif
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">From</label>
                    <input wire:model.defer="leavePeriodStart" type="date" @disabled(! $canEditStep1HrFields) class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                    @error('leavePeriodStart') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600">To</label>
                    <input wire:model.defer="leavePeriodEnd" type="date" @disabled(! $canEditStep1HrFields) class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                    @error('leavePeriodEnd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button
                    type="button"
                    wire:click="applyLeavePeriod"
                    wire:loading.attr="disabled"
                    wire:target="applyLeavePeriod"
                    @disabled(! $canEditStep1HrFields)
                    class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="applyLeavePeriod">Apply Date Range</span>
                    <span wire:loading wire:target="applyLeavePeriod">Applying...</span>
                </button>
            </div>
            <div class="payroll-table-scroll overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="2" class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-3">Employee Information</th>
                            <th colspan="2" class="border-b border-slate-200 px-4 py-3 text-center">Pay Basis</th>
                            <th colspan="6" class="border-b border-slate-200 px-4 py-3 text-center">Leave Basis</th>
                            <th colspan="6" class="border-b border-slate-200 px-4 py-3 text-center">Payroll Input</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3 text-right">Salary Grade</th>
                            <th class="px-4 py-3 text-right">Step</th>
                            <th class="px-4 py-3">Leave Period</th>
                            <th class="px-4 py-3 text-right">Subsistence</th>
                            <th class="px-4 py-3 text-right">PERA</th>
                            <th class="px-4 py-3 text-right">Laundry</th>
                            <th class="px-4 py-3 text-right">TEV</th>
                            <th class="px-4 py-3 text-right">Prior MRA Days</th>
                            <th class="px-4 py-3 text-right">HRIS LWOP</th>
                            <th class="px-4 py-3 text-right">Logbook LWOP</th>
                            <th class="px-4 py-3 text-right">Unauthorized Days</th>
                            <th class="px-4 py-3 text-right">Paid Days</th>
                            <th class="px-4 py-3 text-right">GSIS Days</th>
                            <th class="px-4 py-3 text-right">Adjusted Basic Salary</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr
                                class="hover:bg-slate-50"
                                x-data="{
                                    recalculateLeaveDays() {
                                        const calendarDates = new Set();
                                        const workingDates = new Set();

                                        this.$root.querySelectorAll('[data-leave-item]').forEach((item) => {
                                            if (item.querySelector('[data-leave-excluded]')?.checked || item.dataset.countPaidLeave !== '1') return;

                                            const startValue = item.querySelector('[data-leave-start]')?.value;
                                            const endValue = item.querySelector('[data-leave-end]')?.value;
                                            if (!startValue || !endValue || endValue < startValue) return;

                                            const processedDates = new Set(JSON.parse(item.dataset.processedDates || '[]'));
                                            const date = new Date(`${startValue}T00:00:00Z`);
                                            const end = new Date(`${endValue}T00:00:00Z`);
                                            while (date <= end) {
                                                const value = date.toISOString().slice(0, 10);
                                                if (!processedDates.has(value)) {
                                                    calendarDates.add(value);
                                                    const day = date.getUTCDay();
                                                    if (day !== 0 && day !== 6) workingDates.add(value);
                                                }
                                                date.setUTCDate(date.getUTCDate() + 1);
                                            }
                                        });

                                        this.updateLeaveInput(this.$refs.subsistenceDays, calendarDates.size);
                                        this.updateLeaveInput(this.$refs.laundryDays, workingDates.size);
                                    },
                                    updateLeaveInput(input, value) {
                                        if (!input) return;
                                        input.value = value;
                                        input.dispatchEvent(new Event('input', { bubbles: true }));
                                        input.dispatchEvent(new Event('change', { bubbles: true }));
                                    },
                                }"
                                data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}" data-basic-salary="{{ $row['basic_salary'] ?? 0 }}" data-gross="{{ $row['gross'] ?? 0 }}" data-net-compensation="{{ $row['net_compensation'] ?? 0 }}" data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}" data-unsaved-employee-no="{{ $row['emp_id'] }}" data-unsaved-employee-name="{{ $row['employee_name'] }}"
                            >
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                    <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap">
                                        <span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span>
                                        <span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span>
                                    </div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">
                                        {{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}
                                    </div>
                                    <div class="mt-1 text-[11px] text-slate-500">
                                        HRIS credits: VL {{ number_format($row['vacation_leave_credits'], 3) }} · SL {{ number_format($row['sick_leave_credits'], 3) }}
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        CTO availed {{ number_format($row['cto_availed_days'], 2) }} day(s) · Cancelled leaves {{ $row['cancelled_leave_count'] }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model="payBasisOverrides.{{ $row['emp_id'] }}.salary_grade" type="number" min="0" step="1" @disabled(! $canEditStep1HrFields) class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model="payBasisOverrides.{{ $row['emp_id'] }}.step" type="number" min="1" max="8" step="1" @disabled(! $canEditStep1HrFields) class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="min-w-[340px] space-y-2">
                                        @forelse (($row['leave_deduction']['items'] ?? []) as $leaveItem)
                                            @php
                                                $leaveId = (string) $leaveItem['id'];
                                            @endphp
                                            <div
                                                class="rounded-md border bg-white p-2 {{ $leaveItem['already_processed'] ? 'border-amber-300' : 'border-slate-200' }} {{ $leaveItem['excluded'] ? 'opacity-60' : '' }}"
                                                data-leave-item
                                                data-count-paid-leave="{{ (($leaveItem['days_without_pay'] ?? 0) > 0 && ($leaveItem['days_with_pay'] ?? 0) <= 0) ? '0' : '1' }}"
                                                data-processed-dates='@json(collect($leaveItem['processed_dates'] ?? [])->pluck('date')->values())'
                                            >
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div>
                                                        <div class="text-xs font-semibold text-slate-700">{{ $leaveItem['leave_type'] }}</div>
                                                        <div class="text-[11px] text-slate-500">HRIS: {{ $leaveItem['original_period'] }}</div>
                                                        @if ($leaveItem['fully_processed'])
                                                            <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Already included in earlier payroll</span>
                                                        @elseif ($leaveItem['already_processed'])
                                                            <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Partially included earlier</span>
                                                        @endif
                                                    </div>
                                                    <label class="flex items-center gap-1 text-xs text-slate-600">
                                                        <input wire:model.defer="leaveDateOverrides.{{ $leaveId }}.excluded" x-on:change="$nextTick(() => recalculateLeaveDays())" data-leave-excluded type="checkbox" @disabled(! $canEditStep1HrFields) class="rounded border-slate-300 text-red-600 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-60">
                                                        Exclude
                                                    </label>
                                                </div>
                                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                    <input wire:model.defer="leaveDateOverrides.{{ $leaveId }}.start_date" x-on:change="recalculateLeaveDays()" data-leave-start type="date" min="{{ $previousMraPeriod['start']->toDateString() }}" max="{{ $previousMraPeriod['end']->toDateString() }}" aria-label="Inclusive leave start date" @disabled(! $canEditStep1HrFields) class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                                    <input wire:model.defer="leaveDateOverrides.{{ $leaveId }}.end_date" x-on:change="recalculateLeaveDays()" data-leave-end type="date" min="{{ $previousMraPeriod['start']->toDateString() }}" max="{{ $previousMraPeriod['end']->toDateString() }}" aria-label="Inclusive leave end date" @disabled(! $canEditStep1HrFields) class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                                </div>
                                                @if ($leaveItem['already_processed'])
                                                    <div class="mt-2 rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] text-amber-900">
                                                        Excluded from this calculation:
                                                        @foreach ($leaveItem['processed_dates'] as $processed)
                                                            <span class="font-medium">
                                                                {{ \Carbon\CarbonImmutable::parse($processed['date'])->format('M d, Y') }}
                                                                @if ($processed['payroll_period'] ?? null)
                                                                    ({{ $processed['payroll_period'] }})
                                                                @elseif ($processed['payroll_run_id'] ?? null)
                                                                    (Run #{{ $processed['payroll_run_id'] }})
                                                                @endif
                                                            </span>@if (! $loop->last), @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-slate-400">-</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.defer="leaveDeductionOverrides.{{ $row['emp_id'] }}.subsistence_days" x-ref="subsistenceDays" type="number" min="0" max="31" step="0.001" @disabled(! $canEditStep1HrFields) class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.defer="leaveDeductionOverrides.{{ $row['emp_id'] }}.pera_days" type="number" min="0" max="31" step="0.001" @disabled(! $canEditStep1HrFields) class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.defer="leaveDeductionOverrides.{{ $row['emp_id'] }}.laundry_days" x-ref="laundryDays" type="number" min="0" max="31" step="0.001" @disabled(! $canEditStep1HrFields) class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input wire:model.defer="leaveDeductionOverrides.{{ $row['emp_id'] }}.tev_days" type="number" min="0" max="31" step="0.001" @disabled(! $canEditStep1Tev) class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500">
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['mra_adjustment_days'] ?? 0, 3) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['hris_lwop_days'] ?? 0, 3) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model="logbookLwopDayOverrides.{{ $row['emp_id'] }}"
                                        type="number"
                                        min="0"
                                        max="31"
                                        step="0.001"
                                        placeholder="0.000"
                                        @disabled(! $canEditStep1HrFields)
                                        class="w-28 rounded-md border border-slate-300 px-3 py-2 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                    >
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model="deductionDayOverrides.{{ $row['emp_id'] }}"
                                        type="number"
                                        min="0"
                                        max="31"
                                        step="0.001"
                                        placeholder="{{ number_format($row['mra_deduction_days'], 3) }}"
                                        @disabled(! $canEditStep1HrFields)
                                        class="w-28 rounded-md border border-slate-300 px-3 py-2 text-right text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                    >
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['paid_days'] ?? 0, 3) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['employee_gsis_days'] ?? 0, 3) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['basic_salary'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="17" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($currentStep === 2)
        <div
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
            x-data="{
                open: false,
                adjustmentTypes: @js($allAdjustmentTypes->map(fn ($type) => ['id' => (int) $type->id, 'name' => $type->name])->values()),
                modal: { empId: '', employeeName: '', typeId: '', operator: 'ADD', amount: 0, existingIds: [] },
                start(empId, employeeName, existingIds, item = null) {
                    this.modal.empId = empId;
                    this.modal.employeeName = employeeName;
                    this.modal.existingIds = existingIds.map((id) => Number(id));
                    this.modal.typeId = item ? String(item.typeId) : '';
                    this.modal.operator = item ? item.operator : 'ADD';
                    this.modal.amount = item ? item.amount : 0;
                    this.open = true;
                },
                get availableTypes() {
                    return this.adjustmentTypes.filter((type) => !this.modal.existingIds.includes(type.id) || type.id === Number(this.modal.typeId));
                },
                save() {
                    if (!this.modal.typeId) {
                        return;
                    }

                    this.open = false;
                    $wire.saveEmployeeAdjustment(this.modal.empId, Number(this.modal.typeId), this.modal.operator, this.modal.amount);
                },
            }"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="font-semibold">Gross Compensation</h3>
                    <p class="mt-1 text-sm text-slate-600">Compensation and adjustments, following the payroll workbook layout.</p>
                </div>
                @include('livewire.payroll.partials.step-save-button')
            </div>

            @error('adjustments')
                <div class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <fieldset @disabled(! $canEditCurrentStep) class="contents">
            <div class="payroll-table-scroll overflow-x-auto">
                <table data-gross-compensation-table class="divide-y divide-slate-200 text-sm" style="min-width: {{ 1700 + (($compensations->count() + $adjustmentTypes->count()) * 140) }}px;">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="3" class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-3 align-middle">Employee Information</th>
                            <th colspan="{{ 7 + max(1, $compensations->count()) + $adjustmentTypes->count() }}" class="border-b border-slate-300 px-4 py-3 text-center">Gross Compensation</th>
                        </tr>
                        <tr>
                            <th colspan="{{ 1 + max(1, $compensations->count()) }}" class="border-b border-slate-200 px-4 py-3 text-center">Compensation</th>
                            <th colspan="{{ 4 + $adjustmentTypes->count() }}" class="border-b border-l-2 border-slate-300 px-4 py-3 text-center">Adjustment</th>
                            <th rowspan="2" class="border-l-2 border-slate-300 px-3 py-3 align-middle">Remarks</th>
                            <th rowspan="2" class="border-l-2 border-slate-300 px-4 py-3 text-right align-middle">Gross Compensation</th>
                        </tr>
                        <tr>
                            <th class="px-4 py-3 text-right">Basic Salary</th>
                            @forelse ($compensations as $item)
                                <th class="px-4 py-3 text-right">{{ $workbookCompensationLabel($item) }}</th>
                            @empty
                                <th class="px-4 py-3 text-right">None</th>
                            @endforelse
                            <th class="border-l-2 border-slate-300 px-3 py-3 text-right">Basic Salary</th>
                            <th class="px-3 py-3 text-right">Subsistence</th>
                            <th class="px-3 py-3 text-right">Laundry</th>
                            <th class="px-3 py-3 text-right">PERA</th>
                            @foreach ($adjustmentTypes as $type)
                                <th class="px-3 py-3 text-right">{{ $type->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            @php
                                $employeeExtraItems = (array) ($compensationAdjustments[$row['emp_id']]['extra_items'] ?? []);
                                $employeeAdjustmentTypeIds = collect(array_keys($employeeExtraItems))->map(fn ($id) => (int) $id)->all();
                            @endphp
                            <tr
                                class="hover:bg-slate-50"
                                x-data="{
                                    baseGross: @js((float) ($row['gross'] ?? 0)),
                                    extraAdjustment: @js((float) ($row['compensation_adjustments']['extra_total'] ?? 0)),
                                    adjustments: {
                                        basic_salary: @js((float) ($row['compensation_adjustments']['basic_salary'] ?? 0)),
                                        subsistence: @js((float) ($row['compensation_adjustments']['subsistence'] ?? 0)),
                                        laundry: @js((float) ($row['compensation_adjustments']['laundry'] ?? 0)),
                                        pera: @js((float) ($row['compensation_adjustments']['pera'] ?? 0)),
                                    },
                                    grossCompensation() {
                                        return this.baseGross
                                            + this.extraAdjustment
                                            + Object.values(this.adjustments).reduce((total, value) => total + Number(value || 0), 0);
                                    },
                                }"
                                data-payroll-row
                                data-emp-id="{{ $row['emp_id'] }}"
                                data-employee-name="{{ $row['employee_name'] }}"
                                data-department="{{ $row['department'] ?? '' }}"
                                data-position="{{ $row['position'] ?? '' }}"
                                data-basic-salary="{{ $row['basic_salary'] ?? 0 }}"
                                data-gross="{{ $row['gross'] ?? 0 }}"
                                data-net-compensation="{{ $row['net_compensation'] ?? 0 }}"
                                data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}"
                                data-unsaved-employee-no="{{ $row['emp_id'] }}"
                                data-unsaved-employee-name="{{ $row['employee_name'] }}"
                            >
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5 align-top">
                                    <div class="flex min-w-0 items-start justify-between gap-2">
                                        <div class="min-w-0" data-unsaved-employee-name>
                                            <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap">
                                                <span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span>
                                                <span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span>
                                            </div>
                                            <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                        </div>
                                        <button type="button" x-on:click="start(@js($row['emp_id']), @js($row['employee_name']), @js($employeeAdjustmentTypeIds))" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            +
                                        </button>
                                    </div>
                                </td>
                                <td data-gross-total-key="basic_salary" data-gross-total-value="{{ $row['basic_salary'] }}" class="px-4 py-3 text-right align-top">{{ number_format($row['basic_salary'], 2) }}</td>
                                @forelse ($compensations as $item)
                                    <td data-gross-total-key="compensation_{{ $item->id }}" data-gross-total-value="{{ $row['compensations'][$item->id]['amount'] ?? 0 }}" class="px-4 py-3 text-right align-top">{{ number_format($row['compensations'][$item->id]['amount'] ?? 0, 2) }}</td>
                                @empty
                                    <td class="px-4 py-3 text-right align-top">-</td>
                                @endforelse
                                @foreach (['basic_salary', 'subsistence', 'laundry', 'pera'] as $field)
                                    <td class="px-3 py-2 text-right align-top {{ $loop->first ? 'border-l-2 border-slate-300' : '' }}">
                                        <input
                                            wire:model.defer="compensationAdjustments.{{ $row['emp_id'] }}.{{ $field }}"
                                            x-model.number="adjustments.{{ $field }}"
                                            x-on:input="$nextTick(() => recalculateGrossCompensationTotals())"
                                            data-gross-total-key="adjustment_{{ $field }}"
                                            type="number"
                                            step="0.01"
                                            placeholder="Signed ± adjustment"
                                            class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"
                                        >
                                    </td>
                                @endforeach
                                @foreach ($adjustmentTypes as $type)
                                    @php
                                        $item = $row['compensation_adjustments']['extra_items'][(string) $type->id] ?? null;
                                    @endphp
                                    <td data-gross-total-key="adjustment_type_{{ $type->id }}" data-gross-total-value="{{ $item['signed_amount'] ?? 0 }}" class="px-3 py-2 align-top">
                                        @if ($item)
                                            <div class="flex min-w-[130px] items-center justify-end gap-2">
                                                <button type="button" x-on:click="start(@js($row['emp_id']), @js($row['employee_name']), @js($employeeAdjustmentTypeIds), { typeId: {{ $type->id }}, operator: @js($item['operator'] ?? 'ADD'), amount: @js($item['amount'] ?? 0) })" class="text-right text-xs font-semibold {{ ($item['operator'] ?? 'ADD') === 'LESS' ? 'text-red-700' : 'text-emerald-700' }} hover:underline">
                                                    {{ ($item['operator'] ?? 'ADD') === 'LESS' ? '-' : '+' }}{{ number_format($item['amount'] ?? 0, 2) }}
                                                </button>
                                                <button type="button" wire:click="removeEmployeeAdjustmentType('{{ $row['emp_id'] }}', {{ $type->id }})" class="rounded border border-red-200 px-1.5 py-0.5 text-xs font-semibold text-red-600 hover:bg-red-50">
                                                    x
                                                </button>
                                            </div>
                                        @else
                                            <span class="block min-w-[80px] text-center text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="border-l-2 border-slate-300 px-3 py-2 align-top">
                                    <input
                                        wire:model="compensationAdjustments.{{ $row['emp_id'] }}.remarks"
                                        type="text"
                                        class="w-64 rounded-md border px-2 py-1.5 text-sm {{ $row['compensation_adjustments']['remarks_missing'] ? 'border-red-400 bg-red-50' : 'border-slate-300' }}"
                                    >
                                </td>
                                <td data-gross-total-key="gross_compensation" x-bind:data-gross-total-value="grossCompensation()" class="border-l-2 border-slate-300 px-4 py-3 text-right align-top font-semibold" x-text="money(grossCompensation())">{{ number_format($row['net_compensation'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 10 + max(1, $compensations->count()) + $adjustmentTypes->count() }}" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td class="payroll-sticky-employee-totals border-r-2 border-slate-300 px-4 py-3">Totals</td>
                                <td data-gross-total-output="basic_salary" class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                                @forelse ($compensations as $item)
                                    <td data-gross-total-output="compensation_{{ $item->id }}" class="px-4 py-3 text-right">{{ number_format($totals['compensations'][$item->id] ?? 0, 2) }}</td>
                                @empty
                                    <td class="px-4 py-3 text-right">-</td>
                                @endforelse
                                <td data-gross-total-output="adjustment_basic_salary" class="border-l-2 border-slate-300 px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['basic_salary'], 2) }}</td>
                                <td data-gross-total-output="adjustment_subsistence" class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['subsistence'], 2) }}</td>
                                <td data-gross-total-output="adjustment_laundry" class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['laundry'], 2) }}</td>
                                <td data-gross-total-output="adjustment_pera" class="px-3 py-3 text-right">{{ number_format($totals['compensation_adjustments']['pera'], 2) }}</td>
                                @foreach ($adjustmentTypes as $type)
                                    <td data-gross-total-output="adjustment_type_{{ $type->id }}" class="px-3 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['compensation_adjustments']['extra_items'][(string) $type->id]['signed_amount'] ?? 0), 2) }}</td>
                                @endforeach
                                <td class="border-l-2 border-slate-300 px-3 py-3 text-right">
                                    <div>{{ number_format($totals['compensation_adjustments']['total'], 2) }}</div>
                                    @if ($adjustmentTypes->isNotEmpty())
                                        <div class="text-xs font-normal text-slate-500">Other {{ number_format($totals['compensation_adjustments']['extra_total'], 2) }}</div>
                                    @endif
                                </td>
                                <td data-gross-total-output="gross_compensation" class="border-l-2 border-slate-300 px-4 py-3 text-right">{{ number_format($totals['net_compensation'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

                <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="height: 100dvh;">
                    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-slate-900" x-text="modal.typeId ? 'Edit Adjustment' : 'Add Adjustment'"></h3>
                                <p class="mt-1 text-sm text-slate-600" x-text="modal.employeeName"></p>
                            </div>
                            <button type="button" x-on:click="open = false" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close adjustment modal">
                                &times;
                            </button>
                        </div>

                        <div class="space-y-4 px-5 py-5">
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Adjustment Type</label>
                                <select x-model="modal.typeId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" x-bind:disabled="Boolean(modal.typeId)">
                                    <option value="">Select type</option>
                                    <template x-for="type in availableTypes" x-bind:key="type.id">
                                        <option x-bind:value="type.id" x-text="type.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Operator</label>
                                    <select x-model="modal.operator" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="ADD">Add</option>
                                        <option value="LESS">Less</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Amount</label>
                                    <input x-model="modal.amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                            <button type="button" x-on:click="open = false" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="button" x-on:click="save()" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>
    @elseif ($currentStep === 3)
        @php
            $mandatoryPrograms = $deductionPrograms->where('section', 'mandatory')->values();
            $statutoryMandatoryColumns = collect([
                ['key' => 'life_retirement', 'source' => 'statutory_deductions', 'label' => 'GSIS (PS)'],
                ['key' => 'government_life_retirement', 'source' => 'statutory_government_shares', 'label' => 'GSIS (GS)'],
                ['key' => 'ec', 'source' => 'statutory_government_shares', 'label' => 'EC'],
                ['key' => 'phic', 'source' => 'statutory_deductions', 'label' => 'PHIC (PS)'],
                ['key' => 'government_phic', 'source' => 'statutory_government_shares', 'label' => 'PHIC (GS)'],
                ['key' => 'mandatory_pagibig', 'source' => 'statutory_deductions', 'label' => 'HDMF (PS) 1'],
                ['key' => 'government_pagibig', 'source' => 'statutory_government_shares', 'label' => 'HDMF (GS)'],
            ]);
            $mandatoryDisplayColumns = $statutoryMandatoryColumns->flatMap(function ($column) use ($mandatoryPrograms) {
                return collect([$column])->concat($mandatoryPrograms
                    ->where('insert_after_column', $column['key'])
                    ->map(fn ($program) => ['program' => $program, 'label' => $program->name]));
            })->concat($mandatoryPrograms->whereNull('insert_after_column')->map(fn ($program) => ['program' => $program, 'label' => $program->name]));
        @endphp
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Mandatory Deductions</h3>
                <p class="text-xs text-slate-500">Enter a positive adjustment to add a deduction or a negative adjustment to refund/reduce it.</p>
                @include('livewire.payroll.partials.step-save-button')
            </div>
            <div class="border-b border-slate-200 bg-slate-50/60 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-800">Mandatory Deduction Programs</h4>
                        <p class="text-xs text-slate-500">Apply employee-specific recurring deductions such as HDMF (PS) 2 MS and EA Deduction.</p>
                    </div>
                    <div class="flex gap-2">
                        <button x-on:click="programSelectorDrawerOpen = true; $nextTick(() => window.initPayrollEmployeePickers?.())" type="button" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Select Programs</button>
                        <button type="button" x-on:click="erpOverlay.open($wire, 'program-manager-mandatory')" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Manage Programs</button>
                    </div>
                </div>

                @if ($mandatoryPrograms->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($mandatoryPrograms as $program)
                            <span x-show="programEnabled['{{ $program->id }}']" x-cloak class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ $program->name }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="mt-2 text-xs text-slate-500">No active programs are classified under Mandatory Deductions.</div>
                @endif
            </div>
                <template x-teleport="body">
                    <div x-show="programSelectorDrawerOpen" x-cloak x-on:keydown.escape.window="programSelectorDrawerOpen = false" class="fixed inset-0 z-[1000]" role="dialog" aria-modal="true" aria-label="Select mandatory deduction programs">
                        <button x-on:click="programSelectorDrawerOpen = false" type="button" class="absolute inset-0 bg-slate-950/45" aria-label="Close program selector"></button>
                        <aside class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl">
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                <div>
                                    <h3 class="font-semibold text-slate-900">Select Mandatory Deduction Programs</h3>
                                    <p class="text-xs text-slate-500">Choose a program and configure its employees for this payroll.</p>
                                </div>
                                <button x-on:click="programSelectorDrawerOpen = false" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                            </div>
                            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                                <div class="space-y-4">
                                    <label class="block text-xs font-semibold uppercase text-slate-500">
                                        Program
                                        <select x-model="selectedProgramId" x-on:change="selectProgram($event.target.value)" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case">
                                            <option value="">Choose program</option>
                                            @foreach ($mandatoryPrograms as $program)
                                                <option value="{{ $program->id }}">{{ $program->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <template x-if="selectedProgramId">
                                        <div class="space-y-4">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <label class="text-xs font-semibold uppercase text-slate-500">
                                                    Applies To
                                                    <select x-model="programConfigurations[selectedProgramId].mode" x-on:change="programRevision++; stepDirty = true; $nextTick(() => { window.initPayrollEmployeePickers?.(); syncSelectedProgramEmployees(); })" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case">
                                                        <option value="all">All employees</option>
                                                        <option value="include">Specific employees</option>
                                                        <option value="exclude">All except selected</option>
                                                    </select>
                                                </label>
                                                <label class="text-xs font-semibold uppercase text-slate-500">
                                                    Amount Source
                                                    <select x-model="programConfigurations[selectedProgramId].amount_mode" x-on:change="programRevision++; stepDirty = true; $nextTick(() => { window.initPayrollEmployeePickers?.(); syncSelectedProgramEmployees(); })" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case">
                                                        <option value="program">Program default</option>
                                                        <option value="employee">Employee-specific</option>
                                                    </select>
                                                </label>
                                            </div>
                                            <div x-show="programConfigurations[selectedProgramId].mode !== 'all' || programConfigurations[selectedProgramId].amount_mode === 'employee'" x-cloak>
                                                <label class="text-xs font-semibold uppercase text-slate-500">Employees</label>
                                                <select id="deduction-program-employee-picker" data-select2-searchable data-placeholder="Search employees" x-on:change="updateSelectedProgramEmployees()" multiple class="mt-1 w-full">
                                                    @foreach ($rows as $row)
                                                        <option value="{{ $row['emp_id'] }}">{{ $row['emp_id'] }} - {{ $row['employee_name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                                                <button x-show="programEnabled[selectedProgramId]" x-cloak x-on:click="setProgramEnabled(selectedProgramId, false); resetProgramSelection()" type="button" class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Remove</button>
                                                <button x-show="!programEnabled[selectedProgramId]" x-cloak x-on:click="updateSelectedProgramEmployees(); setProgramEnabled(selectedProgramId, true)" type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply Program</button>
                                                <button x-show="programEnabled[selectedProgramId]" x-cloak x-on:click="updateSelectedProgramEmployees(); programRevision++; stepDirty = true" type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update Program</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </aside>
                    </div>
                </template>
            <x-setup-form-drawer name="program-manager-mandatory" title="Manage Deduction Programs" size="wide">
                <p class="mb-4 text-xs text-slate-500">Changes appear in payroll after this drawer is closed.</p>
                <livewire:payroll.deduction-programs lazy :key="'payroll-mandatory-programs'" />
            </x-setup-form-drawer>
            <fieldset @disabled(! $canEditCurrentStep) class="contents">
            <div class="payroll-table-scroll overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="2" class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-3">Employee Information</th>
                            <th colspan="{{ $mandatoryDisplayColumns->count() }}" class="border-b border-r-2 border-slate-400 px-4 py-2 text-center">Gross Mandatory Deductions</th>
                            <th colspan="{{ $mandatoryDisplayColumns->count() }}" class="border-b border-r-2 border-slate-400 px-4 py-2 text-center">Adjustment</th>
                            <th rowspan="2" class="border-b border-slate-300 px-4 py-3 text-right">Net Pay</th>
                            <th rowspan="2" class="sticky right-0 z-30 min-w-40 border-b border-l-2 border-slate-400 bg-slate-50 px-4 py-3 text-right">Total Mandatory Deductions</th>
                        </tr>
                        <tr>
                            @foreach ($mandatoryDisplayColumns as $column)
                                <th class="border-b border-slate-300 px-4 py-2 text-right">{{ $column['label'] }}</th>
                            @endforeach
                            @foreach ($mandatoryDisplayColumns as $column)
                                <th class="border-b border-slate-300 px-4 py-2 text-right">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            @php
                                $rowMandatoryProgramBases = $mandatoryPrograms
                                    ->where('impact_type', 'employee_deduction')
                                    ->mapWithKeys(function ($program) use ($deductionProgramSelections, $row) {
                                        $selection = $deductionProgramSelections[(string) $program->id] ?? [];
                                        $employeeAmount = data_get($selection, 'employee_amounts.'.$row['emp_id']);
                                        $configuredValue = (($selection['amount_mode'] ?? 'program') === 'employee' && is_numeric($employeeAmount)) ? (float) $employeeAmount : (float) $program->value;
                                        $amount = $program->is_percentage ? round((float) $row['basic_salary'] * ($configuredValue > 1 ? $configuredValue / 100 : $configuredValue), 2) : round($configuredValue, 2);
                                        return [(string) $program->id => $amount];
                                    });
                                $rowMandatoryProgramOverrides = $mandatoryPrograms->mapWithKeys(fn ($program) => [
                                    (string) $program->id => data_get($deductionProgramSelections, $program->id.'.employee_overrides.'.$row['emp_id'], ''),
                                ]);
                            @endphp
                            <tr
                                class="hover:bg-slate-50"
                                data-payroll-row
                                data-emp-id="{{ $row['emp_id'] }}"
                                data-employee-name="{{ $row['employee_name'] }}"
                                data-department="{{ $row['department'] ?? '' }}"
                                data-position="{{ $row['position'] ?? '' }}"
                                data-basic-salary="{{ $row['basic_salary'] ?? 0 }}"
                                data-gross="{{ $row['gross'] ?? 0 }}"
                                data-net-compensation="{{ $row['net_compensation'] ?? 0 }}"
                                data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}"
                                data-unsaved-employee-no="{{ $row['emp_id'] }}"
                                data-unsaved-employee-name="{{ $row['employee_name'] }}"
                                x-data="{
                                    employeeId: @js($row['emp_id']),
                                    employeeBases: @js($row['base_statutory_deductions']),
                                    netCompensation: @js($row['net_compensation']),
                                    programBases: @js($rowMandatoryProgramBases),
                                    programOverrides: @js($rowMandatoryProgramOverrides),
                                    displayedMandatoryTotal() {
                                        const statutory = mandatoryEmployeeTotal(this.employeeId, this.employeeBases);
                                        const programs = Object.entries(this.programBases).reduce((total, [id, base]) => {
                                            if (!programEnabled[id] || !programApplies(id, this.employeeId)) return total;
                                            const override = this.programOverrides[id];
                                            return total + ((override !== '' && override !== null) ? Number(override || 0) : Number(base || 0));
                                        }, 0);
                                        return statutory + programs;
                                    },
                                }"
                            >
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                    <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap">
                                        <span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span>
                                        <span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span>
                                    </div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">
                                        {{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}
                                    </div>
                                </td>
                                {{-- Workbook base/gross mandatory deduction block. --}}
                                @foreach ($mandatoryDisplayColumns as $deductionColumn)
                                    @php
                                        $program = $deductionColumn['program'] ?? null;
                                        $deductionKey = $deductionColumn['key'] ?? null;
                                        $source = $deductionColumn['source'] ?? null;
                                        $baseSource = $source === 'statutory_deductions' ? 'base_statutory_deductions' : 'base_statutory_government_shares';
                                    @endphp
                                    <td class="px-4 py-3 text-right">
                                      @if ($program)
                                        @php
                                            $selection = $deductionProgramSelections[(string) $program->id] ?? [];
                                            $employeeAmount = data_get($selection, 'employee_amounts.'.$row['emp_id']);
                                            $configuredValue = (($selection['amount_mode'] ?? 'program') === 'employee' && is_numeric($employeeAmount)) ? (float) $employeeAmount : (float) $program->value;
                                            $baseProgramAmount = $program->is_percentage ? round((float) $row['basic_salary'] * ($configuredValue > 1 ? $configuredValue / 100 : $configuredValue), 2) : round($configuredValue, 2);
                                        @endphp
                                        <span class="font-medium" x-text="money(programEnabled[@js((string) $program->id)] && programApplies(@js((string) $program->id), employeeId) ? programBases[@js((string) $program->id)] : 0)"></span>
                                      @else
                                        <span class="font-medium">{{ number_format($row[$baseSource][$deductionKey] ?? 0, 2) }}</span>
                                      @endif
                                    </td>
                                @endforeach
                                {{-- Workbook adjustment block, repeated in the same field order. --}}
                                @foreach ($mandatoryDisplayColumns as $deductionColumn)
                                    @php
                                        $program = $deductionColumn['program'] ?? null;
                                        $deductionKey = $deductionColumn['key'] ?? null;
                                    @endphp
                                    <td class="px-4 py-3 text-right {{ $loop->first ? 'border-l-2 border-slate-400' : '' }}">
                                      @if ($program)
                                        <input x-show="programEnabled[@js((string) $program->id)] && programApplies(@js((string) $program->id), employeeId)" x-cloak x-model.number="programOverrides[@js((string) $program->id)]" wire:model.defer="deductionProgramSelections.{{ $program->id }}.employee_overrides.{{ $row['emp_id'] }}" data-model="deductionProgramSelections.{{ $program->id }}.employee_overrides.{{ $row['emp_id'] }}" type="number" min="0" step="0.01" placeholder="0.00" class="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-right text-xs">
                                        <span x-show="!programEnabled[@js((string) $program->id)] || !programApplies(@js((string) $program->id), employeeId)" x-cloak class="text-xs text-slate-400">—</span>
                                      @else
                                        <input
                                            x-model.number="mandatoryDeductionAdjustments[employeeId][@js($deductionKey)]"
                                            data-model="mandatoryDeductionAdjustments.{{ $row['emp_id'] }}.{{ $deductionKey }}"
                                            type="number"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-right text-xs"
                                        >
                                      @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right font-semibold" x-text="money(netCompensation - displayedMandatoryTotal())"></td>
                                <td class="sticky right-0 z-20 min-w-40 border-l-2 border-slate-400 bg-white px-4 py-3 text-right font-semibold" x-text="money(displayedMandatoryTotal())"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + ($mandatoryDisplayColumns->count() * 2) }}" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </fieldset>
        </div>
    @elseif ($currentStep === 5)
        @php $deductionPrograms = $deductionPrograms->where('section', 'other')->values(); @endphp
        <div class="grid h-full min-h-0 gap-3 xl:grid-cols-[minmax(420px,0.8fr)_minmax(560px,1.2fr)] xl:grid-rows-[auto_minmax(420px,1fr)]">
            @if (false)
            <div class="hidden">
                <h3 class="font-semibold">Import Other Deduction Membership</h3>
                <p class="mt-0.5 text-xs text-slate-600">Choose one program. Confirming a valid workbook replaces its persistent employee roster.</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <label class="text-xs font-semibold uppercase text-slate-500">
                        Program
                        <select wire:model="programRosterProgramId" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-normal normal-case">
                            <option value="">Choose program</option>
                            @foreach ($deductionPrograms as $program)
                                <option value="{{ $program->id }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold uppercase text-slate-500">
                        Membership file
                        <input wire:model="programRosterFile" type="file" accept=".xlsx,.xls" class="mt-1 block w-full min-w-0 rounded-md border border-slate-300 bg-white text-xs text-slate-600 file:mr-2 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-100">
                    </label>
                    <button wire:click="previewProgramRoster" type="button" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium">Validate Excel</button>
                    <button wire:click="exportProgramRosterTemplate" type="button" @disabled(! $programRosterProgramId) class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50">Download Template</button>
                    @if ($programRosterPreview !== [])
                        <button wire:click="confirmProgramRoster" type="button" class="rounded bg-emerald-600 px-3 py-2 text-xs font-semibold text-white sm:col-span-2">Replace Roster</button>
                    @endif
                </div>
                @error('programRosterProgramId') <div class="mt-2 text-xs text-red-600">{{ $message }}</div> @enderror
                @error('programRosterFile') <div class="mt-2 text-xs text-red-600">{{ $message }}</div> @enderror
                @if (session('program_roster_status')) <div class="mt-2 text-sm text-emerald-700">{{ session('program_roster_status') }}</div> @endif
                @if ($programRosterPreview !== [])
                    <div class="mt-3 max-h-64 overflow-auto rounded border border-slate-200">
                        <table class="min-w-full text-xs">
                            <thead class="sticky top-0 bg-slate-100 text-left"><tr><th class="p-2">Row</th><th class="p-2">Employee ID</th><th class="p-2">Employee</th><th class="p-2">Validation</th></tr></thead>
                            <tbody>
                                @foreach ($programRosterPreview as $item)
                                    <tr class="border-t border-slate-100">
                                        <td class="p-2">{{ $item['row'] }}</td><td class="p-2">{{ $item['emp_id'] ?: '-' }}</td><td class="p-2">{{ $item['employee_name'] ?: '-' }}</td>
                                        <td class="p-2 {{ $item['valid'] ? 'text-emerald-700' : 'text-red-700' }}">
                                            {{ $item['valid'] ? ($item['name_mismatch'] ? 'Valid · name differs from HRIS' : 'Valid') : implode(' ', $item['errors']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @endif

            @php
                $activeDeductionPrograms = $deductionPrograms;
                $programPreviewWidth = 920;
            @endphp

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm xl:col-span-2">
                <div>
                    <h3 class="font-semibold">Other Deductions Setup</h3>
                    <p class="text-xs text-slate-500">Apply programs or manage their configuration and recurring membership.</p>
                </div>
                <div class="flex gap-2">
                    <button x-on:click="programSelectorDrawerOpen = true; $nextTick(() => window.initPayrollEmployeePickers?.())" type="button" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Select Programs</button>
                    <button type="button" x-on:click="erpOverlay.open($wire, 'program-manager-other')" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Manage Deductions</button>
                </div>
                <div class="flex w-full flex-wrap gap-1.5">
                    @foreach ($deductionPrograms as $program)
                        <span x-show="programEnabled['{{ $program->id }}']" x-cloak class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ $program->name }}</span>
                    @endforeach
                </div>
            </div>

            @if (false) {{-- Replaced by the browser-rendered selection drawer. --}}
            <div class="hidden">
                <div class="border-b border-slate-200 px-3 py-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold">Other Deductions Setup</h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($deductionPrograms as $program)
                                <span x-show="programEnabled['{{ $program->id }}']" x-cloak class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    {{ $program->name }}
                                    <button type="button" x-on:click="setProgramEnabled('{{ $program->id }}', false); resetProgramSelection()" class="rounded-full px-1 text-blue-500 hover:bg-blue-100 hover:text-red-600" aria-label="Remove {{ $program->name }}">&times;</button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                    <label class="block text-xs font-semibold uppercase text-slate-500">
                        Select Program
                        <select x-model="selectedProgramId" x-on:change="selectProgram($event.target.value)" class="mt-1 w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-normal normal-case">
                            <option value="">Choose program</option>
                            @foreach ($deductionPrograms as $program)
                                <option value="{{ $program->id }}">{{ $program->name }} — {{ $program->is_percentage ? 'Percentage' : 'Fixed' }} {{ number_format((float) $program->value, 4) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <template x-if="selectedProgramId">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-xs font-semibold uppercase text-slate-500">Applies To
                                <select x-model="programConfigurations[selectedProgramId].mode" x-on:change="programRevision++; stepDirty = true; $nextTick(() => syncSelectedProgramEmployees())" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs">
                                    <option value="all">All employees</option><option value="include">Specific employees only</option><option value="exclude">All except specific employees</option>
                                </select>
                            </label>
                            <label class="text-xs font-semibold uppercase text-slate-500">Amount Source
                                <select x-model="programConfigurations[selectedProgramId].amount_mode" x-on:change="programRevision++; stepDirty = true; $nextTick(() => syncSelectedProgramEmployees())" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs">
                                    <option value="program">Use program value</option><option value="employee">Employee-specific</option>
                                </select>
                            </label>
                        </div>
                    </template>
                    <div x-show="selectedProgramId && (programConfigurations[selectedProgramId].mode !== 'all' || programConfigurations[selectedProgramId].amount_mode === 'employee')" x-cloak>
                        <label class="text-xs font-semibold uppercase text-slate-500">Employees</label>
                        <select id="deduction-program-employee-picker-hidden" data-placeholder="Search employees" x-on:change="updateSelectedProgramEmployees()" multiple class="mt-1 w-full">
                            @foreach ($rows as $row)
                                <option value="{{ $row['emp_id'] }}">{{ $row['emp_id'] }} - {{ $row['employee_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="selectedProgramId" x-cloak class="flex justify-end gap-2 border-t border-slate-200 pt-2">
                        <button x-show="programEnabled[selectedProgramId]" x-on:click="setProgramEnabled(selectedProgramId, false); resetProgramSelection()" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700">Remove</button>
                        <button x-show="!programEnabled[selectedProgramId]" x-on:click="updateSelectedProgramEmployees(); setProgramEnabled(selectedProgramId, true); resetProgramSelection()" type="button" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Apply Program</button>
                        <button x-show="programEnabled[selectedProgramId]" x-on:click="updateSelectedProgramEmployees(); programRevision++; stepDirty = true; resetProgramSelection()" type="button" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Update Applied Program</button>
                    </div>

                    <div class="hidden">
                            @forelse ($programSetupRows as $program)
                                @php
                                    $selection = $deductionProgramSelections[(string) $program->id] ?? ['enabled' => false, 'mode' => 'all', 'employee_ids' => [], 'amount_mode' => 'program'];
                                    $isEnabled = filter_var($selection['enabled'] ?? false, FILTER_VALIDATE_BOOL);
                                    $selectedEmployeeIds = collect($selection['employee_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
                                @endphp
                                <article
                                    x-data="{ setupMode: @js($selection['mode'] ?? 'all'), amountMode: @js($selection['amount_mode'] ?? 'program') }"
                                    x-show="programSearch.trim() === '' || @js(strtolower($program->name)).includes(programSearch.trim().toLowerCase())"
                                    wire:key="deduction-program-row-{{ $program->id }}"
                                    class="rounded-lg border border-slate-200 bg-slate-50/70 p-2.5"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $program->name }}</div>
                                            <div class="text-xs text-slate-500">{{ $program->is_percentage ? 'Percentage of basic salary' : 'Fixed amount' }} · Default {{ number_format((float) $program->value, 4) }}</div>
                                        </div>
                                        <span x-bind:class="programEnabled['{{ $program->id }}'] ? 'bg-blue-50 text-blue-700' : 'bg-slate-200 text-slate-600'" class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold">
                                            <span x-text="programEnabled['{{ $program->id }}'] ? 'Applied' : 'Not applied'"></span>
                                        </span>
                                    </div>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <label class="text-[11px] font-semibold uppercase text-slate-500">
                                        Applies to
                                        <select id="deduction-program-mode-{{ $program->id }}" wire:model.defer="deductionProgramSelections.{{ $program->id }}.mode" x-model="setupMode" x-on:change="programRevision++" class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                            <option value="all">All employees</option>
                                            <option value="include">Specific employees only</option>
                                            <option value="exclude">All except specific employees</option>
                                        </select>
                                    </label>
                                    <label class="text-[11px] font-semibold uppercase text-slate-500">
                                        Amount source
                                        <select id="deduction-program-amount-mode-{{ $program->id }}" wire:model.defer="deductionProgramSelections.{{ $program->id }}.amount_mode" x-model="amountMode" x-on:change="programRevision++" class="w-full rounded-md border border-slate-300 px-2 py-1 text-xs">
                                            <option value="program">Use program value</option>
                                            <option value="employee">Employee-specific</option>
                                        </select>
                                    </label>
                                    </div>
                                    <div class="mt-2">
                                            <div x-show="setupMode !== 'all' || amountMode === 'employee'" x-cloak wire:ignore wire:key="program-employee-picker-{{ $program->id }}">
                                                <select
                                                    id="deduction-program-employees-{{ $program->id }}"
                                                    data-select2-employee-picker
                                                    data-model="deductionProgramSelections.{{ $program->id }}.employee_ids"
                                                    data-defer-request="true"
                                                    data-placeholder="Choose employees for this program"
                                                    x-on:change="programRevision++"
                                                    multiple
                                                    class="w-full"
                                                >
                                                    @foreach ($rows as $row)
                                                        <option value="{{ $row['emp_id'] }}" @selected(in_array((string) $row['emp_id'], $selectedEmployeeIds, true))>
                                                            {{ $row['emp_id'] }} - {{ $row['employee_name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                    </div>
                                    <div class="mt-2 flex justify-end gap-2 border-t border-slate-200 pt-2">
                                                <button x-show="programEnabled['{{ $program->id }}']" x-on:click="setProgramEnabled('{{ $program->id }}', false)" type="button" class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                    Remove
                                                </button>
                                                <button x-show="programEnabled['{{ $program->id }}']" x-on:click="programRevision++; stepDirty = true" type="button" class="rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                                    Update Table
                                                </button>
                                                <button x-show="!programEnabled['{{ $program->id }}']" x-on:click="setProgramEnabled('{{ $program->id }}', true)" type="button" class="rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                                    Apply
                                                </button>
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                                    No active deduction programs. Create one from Deduction Programs management.
                                </div>
                            @endforelse
                    </div>
                </div>
            </div>
            @endif

            <template x-teleport="body">
                <div x-show="programSelectorDrawerOpen" x-cloak x-on:keydown.escape.window="programSelectorDrawerOpen = false" class="fixed inset-0 z-[1000]" role="dialog" aria-modal="true" aria-label="Select other deduction programs">
                    <button x-on:click="programSelectorDrawerOpen = false" type="button" class="absolute inset-0 bg-slate-950/45" aria-label="Close program selector"></button>
                    <aside class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                            <div><h3 class="font-semibold text-slate-900">Select Other Deduction Programs</h3><p class="text-xs text-slate-500">Choose a program and configure its employees for this payroll.</p></div>
                            <button x-on:click="programSelectorDrawerOpen = false" type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto p-5">
                            <div class="space-y-4">
                                <label class="block text-xs font-semibold uppercase text-slate-500">Program
                                    <select x-model="selectedProgramId" x-on:change="selectProgram($event.target.value)" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case">
                                        <option value="">Choose program</option>
                                        @foreach ($deductionPrograms as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach
                                    </select>
                                </label>
                                <template x-if="selectedProgramId">
                                    <div class="space-y-4">
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <label class="text-xs font-semibold uppercase text-slate-500">Applies To
                                                <select x-model="programConfigurations[selectedProgramId].mode" x-on:change="programRevision++; stepDirty = true; $nextTick(() => { window.initPayrollEmployeePickers?.(); syncSelectedProgramEmployees(); })" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case"><option value="all">All employees</option><option value="include">Specific employees</option><option value="exclude">All except selected</option></select>
                                            </label>
                                            <label class="text-xs font-semibold uppercase text-slate-500">Amount Source
                                                <select x-model="programConfigurations[selectedProgramId].amount_mode" x-on:change="programRevision++; stepDirty = true; $nextTick(() => { window.initPayrollEmployeePickers?.(); syncSelectedProgramEmployees(); })" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal normal-case"><option value="program">Program default</option><option value="employee">Employee-specific</option></select>
                                            </label>
                                        </div>
                                        <div x-show="programConfigurations[selectedProgramId].mode !== 'all' || programConfigurations[selectedProgramId].amount_mode === 'employee'" x-cloak>
                                            <label class="text-xs font-semibold uppercase text-slate-500">Employees</label>
                                            <select id="deduction-program-employee-picker" data-select2-searchable data-placeholder="Search employees" x-on:change="updateSelectedProgramEmployees()" multiple class="mt-1 w-full">
                                                @foreach ($rows as $row)<option value="{{ $row['emp_id'] }}">{{ $row['emp_id'] }} - {{ $row['employee_name'] }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                                            <button x-show="programEnabled[selectedProgramId]" x-cloak x-on:click="setProgramEnabled(selectedProgramId, false); resetProgramSelection()" type="button" class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Remove</button>
                                            <button x-show="!programEnabled[selectedProgramId]" x-cloak x-on:click="updateSelectedProgramEmployees(); setProgramEnabled(selectedProgramId, true)" type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply Program</button>
                                            <button x-show="programEnabled[selectedProgramId]" x-cloak x-on:click="updateSelectedProgramEmployees(); programRevision++; stepDirty = true" type="button" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update Program</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </aside>
                </div>
            </template>

            <x-setup-form-drawer name="program-manager-other" title="Manage Other Deductions" size="wide">
                <div class="space-y-5">
                    <p class="text-xs text-slate-500">Manage programs and import recurring employee membership.</p>
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h4 class="font-semibold">Import Other Deduction Membership</h4>
                        <p class="mt-0.5 text-xs text-slate-600">A confirmed workbook replaces the selected program's persistent employee roster.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="text-xs font-semibold uppercase text-slate-500">Program<select wire:model="programRosterProgramId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case"><option value="">Choose program</option>@foreach ($deductionPrograms as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select></label>
                            <label class="text-xs font-semibold uppercase text-slate-500">Membership file<input wire:model="programRosterFile" type="file" accept=".xlsx,.xls" class="mt-1 block w-full rounded-md border border-slate-300 bg-white text-sm"></label>
                            <button wire:click="previewProgramRoster" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium">Validate Excel</button>
                            <button wire:click="exportProgramRosterTemplate" type="button" @disabled(! $programRosterProgramId) class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium disabled:opacity-50">Download Template</button>
                            @if ($programRosterPreview !== [])<button wire:click="confirmProgramRoster" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white sm:col-span-2">Replace Roster</button>@endif
                        </div>
                        @error('programRosterProgramId') <div class="mt-2 text-xs text-red-600">{{ $message }}</div> @enderror
                        @error('programRosterFile') <div class="mt-2 text-xs text-red-600">{{ $message }}</div> @enderror
                        @if (session('program_roster_status')) <div class="mt-2 text-sm text-emerald-700">{{ session('program_roster_status') }}</div> @endif
                        @if ($programRosterPreview !== [])
                            <div class="mt-3 max-h-64 overflow-auto rounded border border-slate-200"><table class="min-w-full text-xs"><thead class="sticky top-0 bg-slate-100 text-left"><tr><th class="p-2">Row</th><th class="p-2">Employee ID</th><th class="p-2">Employee</th><th class="p-2">Validation</th></tr></thead><tbody>@foreach ($programRosterPreview as $item)<tr class="border-t border-slate-100"><td class="p-2">{{ $item['row'] }}</td><td class="p-2">{{ $item['emp_id'] ?: '-' }}</td><td class="p-2">{{ $item['employee_name'] ?: '-' }}</td><td class="p-2 {{ $item['valid'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $item['valid'] ? ($item['name_mismatch'] ? 'Valid · name differs from HRIS' : 'Valid') : implode(' ', $item['errors']) }}</td></tr>@endforeach</tbody></table></div>
                        @endif
                    </section>
                    <livewire:payroll.deduction-programs lazy :key="'payroll-other-programs'" />
                </div>
            </x-setup-form-drawer>

            <fieldset @disabled(! $canEditCurrentStep) class="contents">
            <div class="grid min-h-0 gap-3 xl:col-span-2 xl:col-start-1 xl:row-start-2">
                <div class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <div>
                            <h3 class="font-semibold">Other Deductions → Others</h3>
                            <p class="text-sm text-slate-600">Turn recurring deductions on for this payroll run and choose who they apply to.</p>
                        </div>
                        @include('livewire.payroll.partials.step-save-button')
                    </div>
                    <div class="payroll-table-scroll min-h-0 flex-1 overflow-auto">
                        <table class="border-separate border-spacing-0 text-sm" style="min-width: {{ $programPreviewWidth }}px;">
                            <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                                <tr>
                                    <th class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-2">Employee Information</th>
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Net Before Programs</th>
                                    @foreach ($activeDeductionPrograms as $program)
                                        <template x-if="programEnabled['{{ $program->id }}']">
                                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">{{ $program->name }}</th>
                                        </template>
                                    @endforeach
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Remarks</th>
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Total Other Deductions</th>
                                    <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Net After Other Deductions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $programItems = collect($row['program_deductions']['items']);
                                        $browserProgramAmounts = $activeDeductionPrograms->mapWithKeys(function ($program) use ($programItems, $deductionProgramSelections, $row) {
                                            $item = $programItems->firstWhere('id', $program->id);
                                            $selection = $deductionProgramSelections[(string) $program->id] ?? [];
                                            $override = $selection['employee_overrides'][$row['emp_id']] ?? null;
                                            $employeeValue = $selection['employee_amounts'][$row['emp_id']] ?? null;
                                            $configuredValue = (($selection['amount_mode'] ?? 'program') === 'employee' && $employeeValue !== null && $employeeValue !== '')
                                                ? (float) $employeeValue
                                                : (float) $program->value;
                                            $defaultAmount = $program->is_percentage
                                                ? round((float) $row['basic_salary'] * ($configuredValue > 1 ? $configuredValue / 100 : $configuredValue), 2)
                                                : round($configuredValue, 2);

                                            return [(string) $program->id => (float) (($override !== null && $override !== '') ? $override : ($item['amount'] ?? $defaultAmount))];
                                        });
                                    @endphp
                                    <tr
                                        class="hover:bg-slate-50"
                                        x-data="{
                                            netBeforePrograms: @js((float) $row['net_after_loans_before_other']),
                                            loanTotal: @js((float) ($row['loan_deductions']['total'] ?? 0)),
                                            programAmounts: @js($browserProgramAmounts),
                                            programTotal() {
                                                return Object.entries(this.programAmounts).reduce((total, [programId, amount]) => {
                                                    return total + (programEnabled[programId] && programApplies(programId, @js((string) $row['emp_id'])) ? Number(amount || 0) : 0);
                                                }, 0);
                                            },
                                            totalOtherDeductions() { return this.loanTotal + this.programTotal(); },
                                            money(value) { return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                                        }"
                                        data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}" data-basic-salary="{{ $row['basic_salary'] ?? 0 }}" data-gross="{{ $row['gross'] ?? 0 }}" data-net-compensation="{{ $row['net_compensation'] ?? 0 }}" data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}" data-unsaved-employee-no="{{ $row['emp_id'] }}" data-unsaved-employee-name="{{ $row['employee_name'] }}"
                                    >
                                        <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-b border-r-2 border-slate-200 px-3 py-2.5">
                                            <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                            <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                        </td>
                                        <td class="border-b border-r border-slate-200 px-3 py-2 text-right">{{ number_format($row['net_after_loans_before_other'], 2) }}</td>
                                        @foreach ($activeDeductionPrograms as $program)
                                            <template x-if="programEnabled['{{ $program->id }}']">
                                            <td class="border-b border-r border-slate-200 px-3 py-2 text-right">
                                                    <input
                                                        id="program-override-{{ $program->id }}-{{ $row['emp_id'] }}"
                                                        wire:model.defer="deductionProgramSelections.{{ $program->id }}.employee_overrides.{{ $row['emp_id'] }}"
                                                        type="hidden"
                                                    >
                                                    <input
                                                        x-show="programApplies('{{ $program->id }}', @js((string) $row['emp_id']))"
                                                        x-model.number="programAmounts['{{ $program->id }}']"
                                                        x-on:input="
                                                            setProgramOverride('{{ $program->id }}', @js((string) $row['emp_id']), $event.target.value);
                                                            const hidden = document.getElementById(@js('program-override-'.$program->id.'-'.$row['emp_id']));
                                                            hidden.value = $event.target.value;
                                                            hidden.dispatchEvent(new Event('input', { bubbles: true }));
                                                            hidden.dispatchEvent(new Event('change', { bubbles: true }));
                                                        "
                                                        data-model="deductionProgramSelections.{{ $program->id }}.employee_overrides.{{ $row['emp_id'] }}"
                                                        data-program-override-input
                                                        data-program-id="{{ $program->id }}"
                                                        data-employee-id="{{ $row['emp_id'] }}"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        aria-label="{{ $program->name }} deduction for {{ $row['employee_name'] }}"
                                                        class="w-28 rounded-md border border-slate-300 px-2 py-1 text-right text-xs"
                                                    >
                                                    <span x-show="!programApplies('{{ $program->id }}', @js((string) $row['emp_id']))" class="text-xs text-slate-400">-</span>
                                            </td>
                                            </template>
                                        @endforeach
                                        <td class="border-b border-r border-slate-200 px-3 py-2"><input wire:model.defer="otherDeductionRemarks.{{ $row['emp_id'] }}" type="text" class="w-36 rounded-md border border-slate-300 px-2 py-1 text-xs" aria-label="Other deduction remarks for {{ $row['employee_name'] }}"></td>
                                        <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold {{ ($row['total_other_deductions'] ?? 0) > 0 ? 'text-blue-700' : 'text-slate-500' }}">
                                            <span x-text="money(totalOtherDeductions())"></span>
                                        </td>
                                        <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold" x-text="money(netBeforePrograms - programTotal())"></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 7 + $activeDeductionPrograms->count() }}" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </fieldset>
        </div>
    @elseif ($currentStep === 4)
        @php
            $isAdditionalPremiumStep = $currentStep === 5;
            $stepDeductionTypes = $isAdditionalPremiumStep ? $additionalPremiumTypes : $loanTypes;
            $stepTitle = $isAdditionalPremiumStep ? 'Additional Premium Deductions' : 'Loan Deductions';
            $stepDescription = $isAdditionalPremiumStep
                ? 'Additional premium deductions for selected employees can be imported or entered manually.'
                : 'Loan deductions for selected employees can be imported or entered manually.';
            $stepAddLabel = $isAdditionalPremiumStep ? 'Add Employee Premium' : 'Add Employee Loan';
            $stepImportLabel = $isAdditionalPremiumStep ? 'Import Premium Excel' : 'Import Loan Excel';
            $stepManageLabel = $isAdditionalPremiumStep ? 'Additional Premiums' : 'Recent Imports';
            $stepTypeLabel = $isAdditionalPremiumStep ? 'Premium Type' : 'Loan Type';
            $stepTypeLabelLc = $isAdditionalPremiumStep ? 'premium type' : 'loan type';
            $stepDeductionLabel = $isAdditionalPremiumStep ? 'premium deduction' : 'loan deduction';
            $stepBatchTitle = $isAdditionalPremiumStep ? 'Batch Premiums' : 'Batch Loans';
            $stepNetBeforeLabel = $isAdditionalPremiumStep ? 'Net After Programs' : 'Net After Premiums';
            $stepTotalLabel = $isAdditionalPremiumStep ? 'Additional Premium' : 'Loan Deductions';
            $stepFinalLabel = $isAdditionalPremiumStep ? 'Net After Premiums' : 'Final Net Pay';
            $stepDeductionKey = $isAdditionalPremiumStep ? 'additional_premiums' : 'loan_deductions';
            $stepNetBeforeKey = 'net_before_other_deductions';
            $stepNetAfterKey = 'net_after_loans_before_other';
            $loanEmployees = $rows->map(fn ($row) => [
                'emp_id' => $row['emp_id'],
                'name' => $row['employee_name'],
            ])->values();
            $loanTypeOptions = $stepDeductionTypes->map(fn ($type) => [
                'id' => (string) $type->id,
                'label' => ($type->entity?->name ?? $type->entity?->code).' - '.$type->name,
            ])->values();
            $recentLoanSuggestions = $this->recentLoanSuggestionsForModal($rows, $stepDeductionTypes);
        @endphp
        <div
            class="space-y-4"
            x-on:loan-deduction-saved.window="closeLoanModal()"
            x-on:loan-deduction-batch-saved.window="closeLoanModal()"
            x-data="{
                activeTab: 'deductions',
                loanModalOpen: false,
                savingLoan: false,
                loanBatch: [],
                batchError: '',
                loanEmployees: @js($loanEmployees),
                loanTypeOptions: @js($loanTypeOptions),
                recentLoanSuggestions: @js($recentLoanSuggestions),
                labels: {
                    type: @js($stepTypeLabel),
                    typeLc: @js($stepTypeLabelLc),
                    deduction: @js($stepDeductionLabel),
                    editTitle: @js($isAdditionalPremiumStep ? 'Edit Premium Deduction' : 'Edit Loan Deduction'),
                    batchTitle: @js($isAdditionalPremiumStep ? 'Batch Add Employee Premiums' : 'Batch Add Employee Loans'),
                    emptyBatch: @js($isAdditionalPremiumStep ? 'No additional premiums staged yet.' : 'No loan deductions staged yet.'),
                    save: @js($isAdditionalPremiumStep ? 'Save Premium Deduction' : 'Save Loan Deduction'),
                },
                editingLoanItemId: null,
                loanForm: {
                    emp_id: '',
                    loan_type_id: '',
                    loan_account_no: '',
                    monthly_amortization: '',
                    amount_due: '',
                    outstanding_balance: '',
                    principal_due: '',
                    interest_due: '',
                    penalty_due: '',
                    remarks: '',
                },
                blankLoanForm(empId = '') {
                    return {
                        emp_id: String(empId || ''),
                        loan_type_id: '',
                        loan_account_no: '',
                        monthly_amortization: '',
                        amount_due: '',
                        outstanding_balance: '',
                        principal_due: '',
                        interest_due: '',
                        penalty_due: '',
                        remarks: '',
                    };
                },
                openLoanModal(empId = '', loan = null) {
                    this.editingLoanItemId = loan ? loan.id : null;
                    this.batchError = '';
                    this.loanBatch = [];
                    this.loanForm = loan
                        ? {
                            emp_id: String(loan.emp_id || empId || ''),
                            loan_type_id: String(loan.loan_type_id || ''),
                            loan_account_no: String(loan.loan_account_no || ''),
                            monthly_amortization: String(loan.monthly_amortization || ''),
                            amount_due: String(loan.amount_due || ''),
                            outstanding_balance: String(loan.outstanding_balance || ''),
                            principal_due: String(loan.principal_due || ''),
                            interest_due: String(loan.interest_due || ''),
                            penalty_due: String(loan.penalty_due || ''),
                            remarks: String(loan.remarks || ''),
                        }
                        : this.blankLoanForm(empId);
                    this.loanModalOpen = true;
                    this.applyRecentLoanSuggestion();
                    this.syncLoanSelects();
                },
                closeLoanModal() {
                    this.loanModalOpen = false;
                    this.savingLoan = false;
                    this.batchError = '';
                    this.loanBatch = [];
                },
                clearLoanReferenceAndAmount() {
                    this.loanForm.loan_account_no = '';
                    this.loanForm.amount_due = '';
                },
                resetLoanForm(keepEmployee = true) {
                    const empId = keepEmployee ? this.loanForm.emp_id : '';
                    this.loanForm = this.blankLoanForm(empId);
                    this.syncLoanSelects();
                },
                get selectedRecentLoanSuggestion() {
                    return this.recentLoanSuggestions[`${this.loanForm.emp_id}|${this.loanForm.loan_type_id}`] || null;
                },
                applyRecentLoanSuggestion() {
                    if (this.editingLoanItemId) {
                        return;
                    }

                    const suggestion = this.selectedRecentLoanSuggestion;
                    if (!suggestion) {
                        return;
                    }

                    ['loan_account_no', 'monthly_amortization', 'amount_due', 'outstanding_balance', 'principal_due', 'interest_due', 'penalty_due'].forEach((field) => {
                        if (this.loanForm[field] === '' && suggestion[field] !== null && suggestion[field] !== undefined) {
                            this.loanForm[field] = String(suggestion[field]);
                        }
                    });
                },
                amountChangedFromRecent() {
                    const suggestion = this.selectedRecentLoanSuggestion;

                    return suggestion
                        && this.loanForm.loan_account_no === String(suggestion.loan_account_no || '')
                        && this.loanForm.amount_due !== ''
                        && Number(this.loanForm.amount_due) !== Number(suggestion.amount_due || 0);
                },
                loanEmployeeName(empId) {
                    return this.loanEmployees.find((employee) => employee.emp_id === empId)?.name || empId || '-';
                },
                loanTypeLabel(loanTypeId) {
                    return this.loanTypeOptions.find((loanType) => loanType.id === String(loanTypeId))?.label || '-';
                },
                addLoanToBatch() {
                    this.batchError = '';
                    if (!this.loanForm.emp_id || !this.loanForm.loan_type_id || this.loanForm.amount_due === '') {
                        this.batchError = `Choose an employee, choose a ${this.labels.typeLc}, and enter the amount due.`;
                        return;
                    }

                    this.loanBatch.push({
                        ...this.loanForm,
                        client_id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    });
                    this.resetLoanForm(true);
                },
                editBatchLoan(index) {
                    const item = this.loanBatch[index];
                    if (!item) {
                        return;
                    }

                    this.loanForm = { ...item };
                    this.loanBatch.splice(index, 1);
                    this.syncLoanSelects();
                },
                removeBatchLoan(index) {
                    this.loanBatch.splice(index, 1);
                },
                syncLoanSelects() {
                    this.$nextTick(() => {
                        if (window.jQuery && this.$refs.loanEmployee) {
                            window.jQuery(this.$refs.loanEmployee).val(this.loanForm.emp_id).trigger('change.select2');
                        }
                        if (window.jQuery && this.$refs.loanType) {
                            window.jQuery(this.$refs.loanType).val(this.loanForm.loan_type_id).trigger('change.select2');
                        }
                    });
                },
                saveLoan() {
                    this.savingLoan = true;
                    $wire.saveLoanDeductionFromModal(this.editingLoanItemId, this.loanForm)
                        .then(() => { this.savingLoan = false; })
                        .catch(() => { this.savingLoan = false; });
                },
                saveLoanBatch() {
                    this.batchError = '';
                    if (this.loanBatch.length === 0) {
                        this.batchError = `Add at least one ${this.labels.deduction} to the batch.`;
                        return;
                    }

                    this.savingLoan = true;
                    $wire.saveLoanDeductionsBatch(this.loanBatch)
                        .then(() => { this.savingLoan = false; })
                        .catch(() => { this.savingLoan = false; });
                },
            }"
        >
            @unless ($isAdditionalPremiumStep)
                <div class="flex gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                    <button type="button" x-on:click="activeTab = 'deductions'" x-bind:class="activeTab === 'deductions' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-md px-4 py-2 text-sm font-semibold">Loan Deductions</button>
                    <button type="button" x-on:click="activeTab = 'refunds'" x-bind:class="activeTab === 'refunds' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-md px-4 py-2 text-sm font-semibold">Loan Refunds</button>
                </div>
            @endunless
            @if (session('loan_import_status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('loan_import_status') }}
                </div>
            @endif

            <template x-if="activeTab === 'deductions'">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div>
                    <h3 class="font-semibold">{{ $stepTitle }}</h3>
                    <p class="text-sm text-slate-600">{{ $stepDescription }} {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }}.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-on:click="openLoanModal()" @disabled(! $canEditCurrentStep) class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ $stepAddLabel }}
                    </button>
                    <a href="{{ route('payroll.loan-imports.template', $isAdditionalPremiumStep ? ['mode' => 'additional_premiums'] : []) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Export Template
                    </a>
                    <button type="button" x-on:click="erpOverlay.open($wire, 'payroll-loan-import', { loanImportPreview: [] })" @disabled(! $canEditCurrentStep) class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ $stepImportLabel }}
                    </button>
                    <a href="{{ $isAdditionalPremiumStep ? route('payroll.additional-premiums') : route('payroll.loan-imports') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        {{ $stepManageLabel }}
                    </a>
                </div>
            </div>
            </template>

            @error('loanDeductionForm')
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div x-cloak x-show="loanModalOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm" style="display: none; height: 100dvh;">
                    <div x-on:click.outside="closeLoanModal()" class="flex max-h-[92vh] w-full max-w-7xl flex-col rounded-lg border border-slate-200 bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-slate-900" x-text="editingLoanItemId ? labels.editTitle : labels.batchTitle"></h3>
                                <p class="mt-1 text-sm text-slate-600">Included in {{ $stepTitle }} for {{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $period)->format('F Y') }}.</p>
                            </div>
                            <button type="button" x-on:click="closeLoanModal()" class="rounded-md px-2 py-1 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close loan deduction modal">
                                &times;
                            </button>
                        </div>

                        <div class="grid min-h-0 gap-5 overflow-y-auto px-5 py-5 xl:grid-cols-[minmax(420px,0.85fr)_minmax(520px,1.15fr)]">
                            <div class="grid content-start gap-4 md:grid-cols-2">
                            <div class="md:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700" x-show="!editingLoanItemId">
                                Fill the form, add it to the batch, then save all staged deductions once.
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Employee</label>
                                <select x-ref="loanEmployee" x-model="loanForm.emp_id" x-on:change="$nextTick(() => applyRecentLoanSuggestion())" data-select2-searchable data-placeholder="Search employee" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select employee</option>
                                    <template x-for="employee in loanEmployees" :key="employee.emp_id">
                                        <option :value="employee.emp_id" x-text="`${employee.name} - ${employee.emp_id}`"></option>
                                    </template>
                                </select>
                                @error('loanDeductionForm.emp_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">{{ $stepTypeLabel }}</label>
                                <select x-ref="loanType" x-model="loanForm.loan_type_id" x-on:change="$nextTick(() => applyRecentLoanSuggestion())" data-select2-searchable data-placeholder="Search loan type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select {{ $stepTypeLabelLc }}</option>
                                    <template x-for="loanType in loanTypeOptions" :key="loanType.id">
                                        <option :value="loanType.id" x-text="loanType.label"></option>
                                    </template>
                                </select>
                                @error('loanDeductionForm.loan_type_id')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-show="selectedRecentLoanSuggestion" class="md:col-span-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                                <span>Auto-filled from </span><span x-text="selectedRecentLoanSuggestion?.due_month"></span><span> for the same employee and {{ $stepTypeLabelLc }}.</span>
                                <div x-show="amountChangedFromRecent()" class="mt-1 font-semibold text-amber-800">
                                    Same reference, but the amount differs from the previous <span x-text="Number(selectedRecentLoanSuggestion?.amount_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>.
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Reference/Account No. <span class="font-normal normal-case text-slate-400">Optional</span></label>
                                <div class="mt-1 flex gap-2">
                                    <input x-model="loanForm.loan_account_no" type="text" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <button type="button" x-on:click="clearLoanReferenceAndAmount()" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                                </div>
                                @error('loanDeductionForm.loan_account_no')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Monthly Amortization <span class="font-normal normal-case text-slate-400">Optional</span></label>
                                <input x-model="loanForm.monthly_amortization" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                @error('loanDeductionForm.monthly_amortization')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Amount Due</label>
                                <div class="mt-1 flex gap-2">
                                    <input x-model="loanForm.amount_due" type="number" min="0" step="0.01" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                    <button type="button" x-on:click="clearLoanReferenceAndAmount()" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                                </div>
                                @error('loanDeductionForm.amount_due')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Outstanding Balance</label>
                                <input x-model="loanForm.outstanding_balance" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Principal Due <span class="font-normal normal-case text-slate-400">Optional</span></label>
                                <input x-model="loanForm.principal_due" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                                @error('loanDeductionForm.principal_due')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Interest Due</label>
                                <input x-model="loanForm.interest_due" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                            </div>

                            <div>
                                <label class="text-xs font-semibold uppercase text-slate-500">Penalty Due</label>
                                <input x-model="loanForm.penalty_due" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm">
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold uppercase text-slate-500">Remarks</label>
                                <textarea x-model="loanForm.remarks" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                            </div>
                            <div class="md:col-span-2 flex justify-end gap-2">
                                <button type="button" x-on:click="resetLoanForm(true)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                    Clear Form
                                </button>
                                <button type="button" x-show="!editingLoanItemId" x-on:click="addLoanToBatch()" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                    Add to Batch
                                </button>
                            </div>
                            <div x-show="batchError" class="md:col-span-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="batchError"></div>
                            </div>

                            <div class="min-h-[360px] overflow-hidden rounded-lg border border-slate-200">
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                                    <h4 class="font-semibold text-slate-900">{{ $stepBatchTitle }}</h4>
                                    <span class="text-sm text-slate-600"><span x-text="loanBatch.length"></span> staged</span>
                                </div>
                                <div class="payroll-table-scroll max-h-[520px] overflow-auto">
                                    <table class="min-w-[820px] divide-y divide-slate-200 text-sm">
                                        <thead class="sticky top-0 bg-white text-left text-xs uppercase text-slate-500">
                                            <tr>
                                                <th class="px-3 py-2">Employee</th>
                                                <th class="px-3 py-2">{{ $stepTypeLabel }}</th>
                                                <th class="px-3 py-2 text-right">Amount Due</th>
                                                <th class="px-3 py-2 text-right">Principal</th>
                                                <th class="px-3 py-2 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="(loan, index) in loanBatch" :key="loan.client_id">
                                                <tr>
                                                    <td class="px-3 py-2">
                                                        <div class="font-medium text-slate-900" x-text="loanEmployeeName(loan.emp_id)"></div>
                                                        <div class="text-xs text-slate-500" x-text="loan.emp_id"></div>
                                                    </td>
                                                    <td class="px-3 py-2" x-text="loanTypeLabel(loan.loan_type_id)"></td>
                                                    <td class="px-3 py-2 text-right font-semibold" x-text="Number(loan.amount_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                                    <td class="px-3 py-2 text-right" x-text="loan.principal_due === '' ? '-' : Number(loan.principal_due || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                                    <td class="px-3 py-2 text-right">
                                                        <button type="button" x-on:click="editBatchLoan(index)" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold hover:bg-slate-50">Edit</button>
                                                        <button type="button" x-on:click="removeBatchLoan(index)" class="rounded border border-red-200 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Remove</button>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr x-show="loanBatch.length === 0">
                                                <td colspan="5" class="px-3 py-10 text-center text-slate-500" x-text="labels.emptyBatch"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                            <button type="button" x-on:click="closeLoanModal()" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="button" x-show="editingLoanItemId" x-on:click="saveLoan()" x-bind:disabled="savingLoan" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                                <span x-show="!savingLoan" x-text="labels.save"></span>
                                <span x-show="savingLoan">Saving...</span>
                            </button>
                            <button type="button" x-show="!editingLoanItemId" x-on:click="saveLoanBatch()" x-bind:disabled="savingLoan || loanBatch.length === 0" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <span x-show="!savingLoan">Save Batch</span>
                                <span x-show="savingLoan">Saving...</span>
                            </button>
                        </div>
                    </div>
                </div>

            <x-setup-form-drawer name="payroll-loan-import" :title="$stepImportLabel" size="wide">
                <p class="mb-4 text-sm text-slate-600">Preview and validate the completed deduction template before saving it to payroll.</p>
                <div class="space-y-4">
                    <div
                        class="grid gap-3 lg:grid-cols-[1fr_auto]"
                        x-data="{ uploadingLoanFile: false, loanUploadProgress: 0, loanUploadError: '' }"
                        x-on:livewire-upload-start="uploadingLoanFile = true; loanUploadProgress = 0; loanUploadError = ''"
                        x-on:livewire-upload-finish="uploadingLoanFile = false; loanUploadProgress = 100"
                        x-on:livewire-upload-error="uploadingLoanFile = false; loanUploadError = 'Upload failed. The workbook may exceed the server upload limit or the connection was interrupted.'"
                        x-on:livewire-upload-cancel="uploadingLoanFile = false; loanUploadError = 'Upload cancelled.'"
                        x-on:livewire-upload-progress="loanUploadProgress = $event.detail.progress"
                    >
                        <div>
                            <label class="text-sm font-medium">{{ $isAdditionalPremiumStep ? 'Premium Excel file' : 'Loan Excel file' }}</label>
                            <input wire:model="loanFile" type="file" accept=".xlsx,.xls,.xlsm,.csv" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('loanFile')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <div x-cloak x-show="uploadingLoanFile" x-transition class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2">
                                <div class="flex items-center justify-between gap-3 text-xs font-medium text-blue-800">
                                    <span x-text="loanUploadProgress >= 100 ? 'Finalizing upload' : 'Uploading workbook'"></span>
                                    <span x-text="`${loanUploadProgress}%`"></span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-blue-100">
                                    <div class="h-full rounded-full bg-blue-600 transition-all duration-150" x-bind:style="`width: ${loanUploadProgress}%`"></div>
                                </div>
                            </div>
                            <div x-cloak x-show="loanUploadError" x-transition class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="loanUploadError"></div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" wire:click="previewLoanImport" wire:loading.attr="disabled" wire:target="previewLoanImport,loanFile" @disabled(! $canEditCurrentStep) class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 lg:w-auto">
                                Preview Rows
                            </button>
                        </div>
                    </div>

                    <div wire:loading.flex wire:target="previewLoanImport,saveLoanImport,loanFile" class="items-center gap-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
                        <span>Reading and validating deduction rows...</span>
                    </div>

                    @if (! empty($loanImportPreview))
                        @if (($loanImportPreview['invalid_rows'] ?? 0) > 0)
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                Fix the invalid rows in the workbook and preview again before saving.
                            </div>
                        @endif

                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <div class="payroll-table-scroll max-h-[420px] overflow-auto">
                                <table class="min-w-[1280px] border-separate border-spacing-0 text-sm">
                                    <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                                        <tr>
                                            <th class="sticky left-0 z-20 border-b border-r border-slate-300 bg-slate-100 px-3 py-2">Row</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2">Status</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2">Due Month</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2">Employee ID</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2">Employee Name</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2">Reference/Account No.</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2 text-right">Amount Due</th>
                                            <th class="border-b border-r border-slate-300 px-3 py-2">Validation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (($loanImportPreview['items'] ?? []) as $item)
                                            <tr class="{{ ($item['validation_status'] ?? '') === 'valid' ? 'bg-white hover:bg-emerald-50/50' : 'bg-amber-50 hover:bg-amber-100/60' }}">
                                                <td class="sticky left-0 border-b border-r border-slate-200 bg-inherit px-3 py-2 font-mono text-xs">{{ $item['row_number'] }}</td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2">
                                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ ($item['validation_status'] ?? '') === 'valid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                        {{ ucfirst($item['validation_status'] ?? 'invalid') }}
                                                    </span>
                                                </td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['due_month'] ?? '-' }}</td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['employee_id'] ?: ($item['matched_emp_id'] ?? '') }}</td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2 font-medium">{{ $item['employee_name'] ?? '-' }}</td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2">{{ $item['loan_account_no'] ?? '-' }}</td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">{{ number_format((float) ($item['amount_due'] ?? 0), 2) }}</td>
                                                <td class="border-b border-r border-slate-200 px-3 py-2 text-xs text-slate-600">
                                                    {{ ! empty($item['validation_errors']) ? implode(' ', $item['validation_errors']) : 'Ready to save.' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                            Upload an Excel file, then preview rows before saving.
                        </div>
                    @endif

                    <div class="flex justify-end gap-2">
                        <button type="button" x-on:click="erpOverlay.close('payroll-loan-import')" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="button" wire:click="saveLoanImport" wire:loading.attr="disabled" wire:target="saveLoanImport" @disabled(! $canEditCurrentStep || empty($loanImportPreview) || (($loanImportPreview['invalid_rows'] ?? 0) > 0)) class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                            Save Import
                        </button>
                    </div>
                </div>
            </x-setup-form-drawer>

            <template x-if="activeTab === 'deductions'">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="payroll-table-scroll max-h-[640px] overflow-auto">
                    <table class="min-w-[1360px] border-separate border-spacing-0 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                            <tr>
                                <th class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-2">Employee Information</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">{{ $stepNetBeforeLabel }}</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">{{ $stepTotalLabel }}</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right">{{ $stepFinalLabel }}</th>
                                <th class="border-b border-r border-slate-300 px-3 py-2">Deduction Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50" data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}" data-basic-salary="{{ $row['basic_salary'] ?? 0 }}" data-gross="{{ $row['gross'] ?? 0 }}" data-net-compensation="{{ $row['net_compensation'] ?? 0 }}" data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}" data-unsaved-employee-no="{{ $row['emp_id'] }}" data-unsaved-employee-name="{{ $row['employee_name'] }}">
                                    <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-b border-r-2 border-slate-200 px-3 py-2.5">
                                        <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                        <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right">{{ number_format($row[$stepNetBeforeKey] ?? 0, 2) }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold {{ ($row[$stepDeductionKey]['total'] ?? 0) > 0 ? 'text-blue-700' : 'text-slate-500' }}">
                                        {{ number_format($row[$stepDeductionKey]['total'] ?? 0, 2) }}
                                    </td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2 text-right font-semibold">{{ number_format($row[$stepNetAfterKey] ?? 0, 2) }}</td>
                                    <td class="border-b border-r border-slate-200 px-3 py-2">
                                        @forelse ($row[$stepDeductionKey]['items'] as $loan)
                                            <div class="mb-1 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs">
                                                <span class="font-semibold">{{ $loan['entity'] }}</span>
                                                <span class="text-slate-500">· {{ $loan['loan_account_no'] }}</span>
                                                <span class="float-right ml-2 font-semibold">{{ number_format($loan['amount_due'], 2) }}</span>
                                                <button type="button" x-on:click="openLoanModal(@js($row['emp_id']), @js($loan))" @disabled(! $canEditCurrentStep) class="ml-2 rounded border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60">
                                                    Edit
                                                </button>
                                            </div>
                                        @empty
                                            <button type="button" x-on:click="openLoanModal(@js($row['emp_id']))" @disabled(! $canEditCurrentStep) class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                                                {{ $isAdditionalPremiumStep ? 'Add additional premium' : 'Add loan deduction' }}
                                            </button>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            </template>
            @unless ($isAdditionalPremiumStep)
                <template x-if="activeTab === 'refunds'">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h3 class="font-semibold">Loan Refunds</h3>
                        <p class="text-sm text-slate-600">Enter positive amounts to add previously deducted loan payments back to employee net pay.</p>
                    </div>
                    <div class="payroll-table-scroll max-h-[640px] overflow-auto">
                        <table class="min-w-[1100px] border-separate border-spacing-0 text-sm">
                            <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase text-slate-600">
                                <tr><th class="payroll-sticky-employee-summary-header px-3 py-2">Employee Information</th><th class="px-3 py-2">Loan Kind</th><th class="px-3 py-2 text-right">Refund Amount</th><th class="px-3 py-2">Remarks</th><th class="px-3 py-2 text-right">Net After Refund</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rows as $row)
                                    <tr x-data="{ refundAmount: @entangle('loanRefunds.'.$row['emp_id'].'.amount'), baseNet: @js($row['net_after_loan_deductions'] - ($row['loan_refunds']['total'] ?? 0)) }" data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}">
                                        <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                            <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                            <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <select wire:model.defer="loanRefunds.{{ $row['emp_id'] }}.loan_type" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                                <option value="">Select loan type</option>
                                                @foreach ($loanTypeOptions as $loanType)
                                                    <option value="{{ $loanType['label'] }}">{{ $loanType['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2"><input x-model.number="refundAmount" data-model="loanRefunds.{{ $row['emp_id'] }}.amount" type="number" min="0" step="0.01" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                        <td class="px-3 py-2"><input wire:model.defer="loanRefunds.{{ $row['emp_id'] }}.remarks" type="text" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"></td>
                                        <td class="px-3 py-2 text-right font-semibold" x-text="money(baseNet + Number(refundAmount || 0))"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                </template>
            @endunless
        </div>
    @elseif ($currentStep === 6)
        <div
            x-data="{
                taxTab: 'basic',
                bulkTaxAdjustment: '',
                applyTaxAdjustmentToAll(root) {
                    root.querySelectorAll('[data-tax-adjustment]').forEach((input) => {
                        input.value = this.bulkTaxAdjustment;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                },
            }"
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="font-semibold">Tax Calculation</h3>
                    <p class="text-sm text-slate-600">Review annualized taxable income, tax due, withholding tax, and net pay.</p>
                </div>
                @include('livewire.payroll.partials.step-save-button')
            </div>
            @include('livewire.payroll.partials.tax-input-import', [
                'fileModel' => 'taxAnnualizationFile',
                'preview' => $taxInputImportPreview,
                'importMessage' => $taxAnnualizationImportMessage,
                'validateAction' => 'importTaxAnnualizationLookup',
                'templateAction' => 'exportTaxInputTemplate',
                'confirmAction' => 'confirmTaxInputImport',
            ])
            <div class="flex flex-wrap gap-2 border-b border-slate-200 px-4 py-3">
                <button type="button" x-on:click="taxTab = 'basic'" x-bind:class="taxTab === 'basic' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-md px-4 py-2 text-sm font-semibold">Tax on Basic</button>
                <button type="button" x-on:click="taxTab = 'hazard'" x-bind:class="taxTab === 'hazard' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-md px-4 py-2 text-sm font-semibold">Tax on Hazard</button>
                <button type="button" x-on:click="taxTab = 'annualization'" x-bind:class="taxTab === 'annualization' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-md px-4 py-2 text-sm font-semibold">Tax Annualization</button>
            </div>
            <div x-show="taxTab === 'basic'" class="payroll-table-scroll overflow-x-auto">
                <table class="min-w-[1320px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="3" class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-3 align-middle">Employee Information</th>
                            <th colspan="7" class="border-b border-slate-300 px-4 py-2 text-center">Tax on Basic</th>
                        </tr>
                        <tr>
                            <th colspan="3" class="border-b border-r-2 border-slate-300 px-4 py-2 text-center">Base Income</th>
                            <th colspan="3" class="border-b border-r-2 border-slate-300 px-4 py-2 text-center">Tax</th>
                            <th rowspan="2" class="border-b border-slate-300 px-3 py-2 align-middle">
                                <div class="flex min-w-48 flex-col items-end gap-1.5">
                                    <span>Tax Adj</span>
                                    @if ($canEditCurrentStep)
                                        <div class="flex items-center gap-1.5 normal-case">
                                            <input x-model="bulkTaxAdjustment" type="number" step="0.01" placeholder="0.00" aria-label="Tax adjustment to apply to all employees" class="w-24 rounded-md border border-slate-300 bg-white px-2 py-1.5 text-right text-xs font-normal text-slate-700">
                                            <button type="button" x-on:click="applyTaxAdjustmentToAll($root)" x-bind:disabled="bulkTaxAdjustment === ''" class="whitespace-nowrap rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-semibold normal-case text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Apply to All</button>
                                        </div>
                                    @endif
                                </div>
                            </th>
                        </tr>
                        <tr>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">TI</th>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">CL</th>
                            <th class="border-b border-r-2 border-slate-300 px-4 py-2 text-right">Excess</th>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">CL</th>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">Excess</th>
                            <th class="border-b border-r-2 border-slate-300 px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php $taxOnBasic = $row['tax']['tax_on_basic_breakdown'] ?? []; @endphp
                            <tr data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}">
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                    <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnBasic['taxable_income'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnBasic['class_limit'] ?? 0, 2) }}</td>
                                <td class="border-r-2 border-slate-200 px-4 py-3 text-right">{{ number_format($taxOnBasic['excess'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnBasic['base_tax'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnBasic['excess_tax'] ?? 0, 2) }}</td>
                                <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($taxOnBasic['total'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right"><input data-tax-adjustment wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.withholding_tax_adjustment" type="number" step="0.01" placeholder="{{ number_format($row['tax']['withholding_tax_adjustment'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div x-show="taxTab === 'hazard'" x-cloak class="payroll-table-scroll overflow-x-auto">
                <table class="min-w-[1320px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th rowspan="3" class="payroll-sticky-employee-summary-header border-b border-r-2 border-slate-300 px-3 py-3 align-middle">Employee Information</th>
                            <th colspan="8" class="border-b border-slate-300 px-4 py-2 text-center">Tax on Hazard</th>
                        </tr>
                        <tr>
                            <th colspan="3" class="border-b border-r-2 border-slate-300 px-4 py-2 text-center">Base Income</th>
                            <th colspan="3" class="border-b border-r-2 border-slate-300 px-4 py-2 text-center">Tax</th>
                            <th rowspan="2" class="border-b border-slate-300 px-4 py-3 text-right align-middle">Tax on Basic</th>
                            <th rowspan="2" class="border-b border-slate-300 px-4 py-3 text-right align-middle">Tax on Hazard</th>
                        </tr>
                        <tr>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">TI</th>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">CL</th>
                            <th class="border-b border-r-2 border-slate-300 px-4 py-2 text-right">Excess</th>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">CL</th>
                            <th class="border-b border-slate-300 px-4 py-2 text-right">Excess</th>
                            <th class="border-b border-r-2 border-slate-300 px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            @php $taxOnHazard = $row['tax']['tax_on_hazard_breakdown'] ?? []; @endphp
                            <tr data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}">
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                    <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnHazard['taxable_income'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnHazard['class_limit'] ?? 0, 2) }}</td>
                                <td class="border-r-2 border-slate-200 px-4 py-3 text-right">{{ number_format($taxOnHazard['excess'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnHazard['base_tax'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnHazard['excess_tax'] ?? 0, 2) }}</td>
                                <td class="border-r-2 border-slate-200 px-4 py-3 text-right font-semibold">{{ number_format($taxOnHazard['total'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($taxOnHazard['tax_on_basic'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($taxOnHazard['tax_on_hazard'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="hidden payroll-table-scroll overflow-x-auto">
                <table class="min-w-[2720px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="payroll-sticky-employee-summary-header border-r-2 border-slate-300 px-3 py-3">Employee Information</th>
                            <th class="px-4 py-3">Entry Date</th>
                            <th class="px-4 py-3 text-right">SG</th>
                            <th class="px-4 py-3 text-right">Salary</th>
                            <th class="px-4 py-3 text-right">SUBSISTENCE</th>
                            <th class="px-4 py-3 text-right">HAZARD</th>
                            <th class="px-4 py-3 text-right">GROSS COMPENSATION</th>
                            <th class="px-4 py-3 text-right">TOTAL MANDATORY DEDCUTIONS</th>
                            <th class="px-4 py-3 text-right">TOTAL OTHER DEDUCTIONS</th>
                            <th class="px-4 py-3 text-right">REFUNDS</th>
                            <th class="px-4 py-3 text-right">NET PAY BEFORE OTHER DEDUCTIONS</th>
                            <th class="px-4 py-3 text-right">ADJUSTMENT</th>
                            <th class="px-4 py-3 text-right">TOTAL MONTHS</th>
                            <th class="px-4 py-3 text-right">MONTH DEDUCTION (LWOP & UNAUTH)</th>
                            <th class="px-4 py-3 text-right">NET, MONTHS</th>
                            <th class="px-4 py-3 text-right">TOTAL GROSS INCOME</th>
                            <th class="px-4 py-3 text-right">TOTAL DEDUCTIONS</th>
                            <th class="px-4 py-3 text-right">TAXABLE INCOME (YEAR)</th>
                            <th class="px-4 py-3 text-right">TOTAL TAX DUE</th>
                            <th class="px-4 py-3 text-right">TAX</th>
                            <th class="px-4 py-3 text-right">AUTOMATIC TAX ADDITION</th>
                            <th class="px-4 py-3 text-right">WITHHOLDING TAX (GROSS)</th>
                            <th class="px-4 py-3 text-right">TAX ADJUSTMENT</th>
                            <th class="px-4 py-3 text-right">NET PAY</th>
                            <th class="px-4 py-3 text-right">15th</th>
                            <th class="px-4 py-3 text-right">30th</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50" data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}" data-basic-salary="{{ $row['basic_salary'] ?? 0 }}" data-gross="{{ $row['gross'] ?? 0 }}" data-net-compensation="{{ $row['net_compensation'] ?? 0 }}" data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}" data-unsaved-employee-no="{{ $row['emp_id'] }}" data-unsaved-employee-name="{{ $row['employee_name'] }}">
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                    <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $row['tax']['entry_date'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ $row['tax']['salary_grade'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['salary'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['subsistence'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['hazard'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['net_compensation'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['monthly_mandatory_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format(($row['program_deductions']['total'] ?? 0) + ($row['additional_premiums']['total'] ?? 0) + ($row['loan_deductions']['total'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['loan_refunds']['total'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['monthly_net_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['tax_adjustment'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_months'] ?? 12, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annualization_leave_without_pay_months'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['months'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_gross_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_mandatory_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_taxable_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['regular_monthly_tax_due'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format(($row['tax']['gross_withholding_tax_adjustment'] ?? 0) + ($row['tax']['supplemental_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tax']['withholding_tax_gross'] ?? $row['tax']['monthly_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['withholding_tax_adjustment'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_loan_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['fifteenth'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['thirtieth'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="28" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="3" class="border-r-2 border-slate-300 px-4 py-3">Totals</td>
                                <td colspan="2"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['basic_salary'], 2) }}</td>
                                <td></td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_compensation'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['total_mandatory_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format(($totals['program_deductions'] ?? 0) + ($totals['additional_premiums'] ?? 0) + ($totals['loan_deductions'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['loan_refunds']['total'] ?? 0), 2) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_gross_income'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_mandatory_deductions'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_taxable_income'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['annual_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['regular_monthly_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => ($row['tax']['gross_withholding_tax_adjustment'] ?? 0) + ($row['tax']['supplemental_tax_due'] ?? 0)), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['withholding_tax_gross'] ?? $row['tax']['monthly_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($rows->sum(fn ($row) => $row['tax']['withholding_tax_adjustment'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_after_loan_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['fifteenth'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['thirtieth'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <div x-show="taxTab === 'annualization'" x-cloak class="payroll-table-scroll overflow-x-auto">
                <table class="min-w-[5760px] divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2" colspan="2"></th>
                            <th class="px-4 py-2 text-center" colspan="30">ANNUALIZATION</th>
                            <th class="px-4 py-2 text-center" colspan="6">WITHHOLDING TAX OUTPUTS</th>
                        </tr>
                        <tr>
                            <th class="payroll-sticky-employee-summary-header border-r-2 border-slate-300 px-3 py-3">Employee Information</th>
                            <th class="px-4 py-3 text-right">SG</th>
                            <th class="px-4 py-3">APPOINTMENT DATE</th>
                            <th class="px-4 py-3">EXPECTED RETIRE/RESIGN DATE</th>
                            <th class="px-4 py-3 text-right">FUTURE MONTHS</th>
                            <th class="px-4 py-3 text-right">MONTH DEDUCTION (LWOP & UNAUTH)</th>
                            <th class="px-4 py-3 text-right">MONTH DEDUCTION (FOR HAZ & SUBS))</th>
                            <th class="px-4 py-3 text-right">BASIC (PREV)</th>
                            <th class="px-4 py-3 text-right">BASIC (CURR)</th>
                            <th class="px-4 py-3 text-right">BASIC (FUT)</th>
                            <th class="px-4 py-3 text-right">TOTAL BASIC</th>
                            <th class="px-4 py-3 text-right">HAZARD (PREV)</th>
                            <th class="px-4 py-3 text-right">HAZARD (CURR)</th>
                            <th class="px-4 py-3 text-right">HAZARD (FUT)</th>
                            <th class="px-4 py-3 text-right">TOTAL HAZARD</th>
                            <th class="px-4 py-3 text-right">SUBS (PREV)</th>
                            <th class="px-4 py-3 text-right">SUBS (CURR)</th>
                            <th class="px-4 py-3 text-right">SUBS (FUT)</th>
                            <th class="px-4 py-3 text-right">TOTAL SUBS</th>
                            <th class="px-4 py-3 text-right">MAN DED (PREV)</th>
                            <th class="px-4 py-3 text-right">MAN DED (CURR)</th>
                            <th class="px-4 py-3 text-right">MAN DED (FUT)</th>
                            <th class="px-4 py-3 text-right">TOTAL MAN DED</th>
                            <th class="px-4 py-3 text-right">TAXABLE INCOME</th>
                            <th class="px-4 py-3 text-right">TAXABLE INCOME (YEAR)</th>
                            <th class="px-4 py-3 text-right">TOTAL TAX DUE</th>
                            <th class="px-4 py-3 text-right">TAX WITHHELD (PREV)</th>
                            <th class="px-4 py-3 text-right">TAX WITHHELD (CURR)</th>
                            <th class="px-4 py-3 text-right">TAX WITHHELD (FUT)</th>
                            <th class="px-4 py-3 text-right">TOTAL TAX WITHHELD</th>
                            <th class="px-4 py-3 text-right">(UNDER)/OVER WITHHELD</th>
                            <th class="px-4 py-3 text-right">MONTHLY TAX DUE</th>
                            <th class="px-4 py-3 text-right">AUTOMATIC TAX ADDITION</th>
                            <th class="px-4 py-3 text-right">WITHHOLDING TAX (GROSS)</th>
                            <th class="px-4 py-3 text-right">TAX ADJUSTMENT</th>
                            <th class="px-4 py-3 text-right">NET PAY</th>
                            <th class="px-4 py-3 text-right">15TH</th>
                            <th class="px-4 py-3 text-right">30TH</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-slate-50" data-payroll-row data-emp-id="{{ $row['emp_id'] }}" data-employee-name="{{ $row['employee_name'] }}" data-department="{{ $row['department'] ?? '' }}" data-position="{{ $row['position'] ?? '' }}" data-basic-salary="{{ $row['basic_salary'] ?? 0 }}" data-gross="{{ $row['gross'] ?? 0 }}" data-net-compensation="{{ $row['net_compensation'] ?? 0 }}" data-net-after-loan-deductions="{{ $row['net_after_loan_deductions'] ?? 0 }}">
                                <td class="payroll-sticky-employee-summary-cell overflow-hidden whitespace-nowrap border-r-2 border-slate-200 px-3 py-2.5">
                                    <div class="flex min-w-0 items-baseline gap-2 whitespace-nowrap"><span class="shrink-0 font-semibold text-slate-900">{{ $row['emp_id'] }}</span><span class="min-w-0 truncate font-medium text-slate-800" title="{{ $row['employee_name'] }}">{{ $row['employee_name'] }}</span></div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500" title="{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}">{{ $row['position'] ?? '-' }} · {{ $row['department'] ?: ($row['division'] ?? 'No department/office') }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $row['tax']['salary_grade'] ?? '-' }} / {{ $row['step'] ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $row['tax']['entry_date'] ?? '-' }}</td>
                                <td class="px-4 py-3">-</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_months'] ?? 0, 0) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annualization_leave_without_pay_months'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['hazard_subsistence_deduction_months'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right"><input wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.previous_basic" type="number" min="0" step="0.01" placeholder="{{ number_format($row['tax']['previous_basic'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['current_basic'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_basic'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_basic'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right"><input wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.previous_hazard" type="number" min="0" step="0.01" placeholder="{{ number_format($row['tax']['previous_hazard'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['current_hazard'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_hazard'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_hazard'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right"><input wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.previous_subsistence" type="number" min="0" step="0.01" placeholder="{{ number_format($row['tax']['previous_subsistence'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['current_subsistence'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_subsistence'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_subsistence'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right"><input wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.previous_mandatory_deductions" type="number" min="0" step="0.01" placeholder="{{ number_format($row['tax']['previous_mandatory_deductions'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['current_mandatory_deductions'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_mandatory_deductions'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_mandatory_deductions'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_monthly_taxable_income'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_taxable_income'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['annual_tax_due'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right"><input wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.previous_tax_withheld" type="number" min="0" step="0.01" placeholder="{{ number_format($row['tax']['previous_tax_withheld'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-32 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['current_tax_withheld'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['future_tax_withheld'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['total_tax_withheld'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($row['tax']['under_over_withheld'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tax']['monthly_annualized_tax_due'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format(($row['tax']['gross_withholding_tax_adjustment'] ?? 0) + ($row['tax']['supplemental_tax_due'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['tax']['withholding_tax_gross'] ?? $row['tax']['monthly_tax_due'], 2) }}</td>
                                <td class="px-4 py-3 text-right"><input data-tax-adjustment wire:model.defer="taxAnnualizationOverrides.{{ $row['emp_id'] }}.withholding_tax_adjustment" type="number" step="0.01" placeholder="{{ number_format($row['tax']['withholding_tax_adjustment'] ?? 0, 2, '.', '') }}" @disabled(! $canEditCurrentStep) class="w-28 rounded-md border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['net_after_loan_deductions'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['fifteenth'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($row['thirtieth'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="40" class="px-4 py-8 text-center text-slate-500">No active HRIS employees found for the selected department.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($currentStep === 7)
        @php
            $activeReviewDeductionPrograms = $deductionPrograms->filter(fn ($program) => filter_var($deductionProgramSelections[(string) $program->id]['enabled'] ?? false, FILTER_VALIDATE_BOOL));
        @endphp

        <div class="payroll-review-layout flex h-full min-h-0 flex-col gap-4">

        {{-- FINALIZE HEADER --}}
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div>
                <h3 class="font-semibold">Review</h3>
                <p class="text-sm text-slate-600">
                    Final payroll summary before saving the payroll run.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="exportRegularPayrollTemplate"
                    wire:loading.attr="disabled"
                    wire:target="exportRegularPayrollTemplate"
                    @disabled($rows->isEmpty() || ! $canEditCurrentStep)
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="exportRegularPayrollTemplate">Export Regular Payroll</span>
                    <span wire:loading wire:target="exportRegularPayrollTemplate">Preparing Excel...</span>
                </button>

                <button
                    type="button"
                    wire:click="finalizePayroll"
                    wire:loading.attr="disabled"
                    wire:target="finalizePayroll"
                    @disabled($rows->isEmpty() || ! $canEditCurrentStep)
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="finalizePayroll">Finalize Payroll Run</span>
                    <span wire:loading wire:target="finalizePayroll">Saving Payroll Run...</span>
                </button>
            </div>
        </div>

        @error('finalize')
            <div class="shrink-0 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror

        @if (session('success'))
            <div class="shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                <div class="font-semibold">{{ session('success') }}</div>
                @if ($finalizedRunId)
                    <p class="mt-1">
                        Run #{{ $finalizedRunId }} saved for {{ $finalizedSummary['department'] ?? 'the selected department' }} covering {{ $finalizedSummary['period'] ?? $period }}.
                    </p>
                @endif
            </div>
        @endif

        {{-- REVIEW TABLE --}}
        <section
            x-data="{ configurationOpen: false }"
            class="shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-white"
            aria-labelledby="payroll-configuration-title"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                x-on:click="configurationOpen = ! configurationOpen"
                x-bind:aria-expanded="configurationOpen.toString()"
                aria-controls="payroll-configuration-content"
            >
                <span class="min-w-0">
                    <span id="payroll-configuration-title" class="block font-semibold text-slate-900">Payroll configuration</span>
                    <span class="mt-0.5 block text-xs text-slate-500">Review the period, scope, employee coverage, and leave settings.</span>
                </span>
                <span class="inline-flex shrink-0 items-center gap-2 text-xs font-semibold text-indigo-700">
                    <span x-text="configurationOpen ? 'Hide' : 'Show'"></span>
                    <svg
                        class="h-4 w-4 transition-transform duration-200"
                        x-bind:class="configurationOpen ? 'rotate-180' : ''"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6"></path>
                    </svg>
                </span>
            </button>

            <div
                id="payroll-configuration-content"
                x-cloak
                x-show="configurationOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="border-t border-slate-200 px-4 pb-4 pt-3"
            >
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                    @foreach ($reviewConfiguration as $item)
                        <div @class([
                            'min-w-0',
                            'sm:col-span-2 xl:col-span-3 2xl:col-span-5' => $item['wide'] ?? false,
                        ])>
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                            <dd class="mt-0.5 text-sm font-medium leading-5 text-slate-900">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold">Review</h3>
            </div>

            @include('livewire.payroll.partials.payroll-review-table', [
                'rows' => $rows,
                'compensations' => $compensations,
                'adjustmentTypes' => $adjustmentTypes,
                'totals' => $totals,
                'loanColumnGroups' => $loanColumnGroups,
                'deductionPrograms' => $activeReviewDeductionPrograms,
            ])

        </div>
        </div>
    @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            This payroll step is not available. Use the step navigation to continue.
        </div>
    @endif
    </div>

    </main>
    </div>
</section>
