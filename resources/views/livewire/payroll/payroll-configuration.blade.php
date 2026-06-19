<section class="max-w-6xl space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
        <h2 class="text-xl font-semibold">Payroll Configuration</h2>
            <p class="text-sm text-slate-600">Set the payroll scope before opening the generation workflow.</p>
        </div>
    </div>

    <form wire:submit="proceed" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="grid divide-y divide-slate-100 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.85fr)] lg:divide-x lg:divide-y-0">
            <div class="space-y-4 p-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-slate-500">Payroll Scope</h3>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Payroll Type</label>
                        <select wire:model="payrollType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm">
                            @foreach ($payrollTypes as $type)
                                <option value="{{ $type->code }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('payrollType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Employee Type</label>
                        <select wire:model="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm">
                            @foreach ($employeeTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('employeeTypeFilter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <div>
                        <label class="text-sm font-medium">Division</label>
                        <select
                            data-select2-searchable
                            data-model="selectedDivisionIds"
                            data-defer-request="true"
                            data-placeholder="Search and select divisions"
                            multiple
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm"
                        >
                            @foreach ($divisions as $division)
                                <option value="{{ $division->division_id }}" @selected(in_array((int) $division->division_id, $selectedDivisionIds, true))>{{ $division->division }}</option>
                            @endforeach
                        </select>
                        @error('selectedDivisionIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('selectedDivisionIds.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Department <span class="font-normal text-slate-500">(Optional)</span></label>
                        <select
                            data-select2-searchable
                            data-model="selectedDepartmentIds"
                            data-defer-request="true"
                            data-placeholder="Search and select departments"
                            multiple
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm"
                        >
                            @foreach ($departments as $department)
                                <option value="{{ $department->department_id }}" @selected(in_array((int) $department->department_id, $selectedDepartmentIds, true))>{{ $department->department }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Leave empty to include all departments in the selected division(s).</p>
                        @error('selectedDepartmentIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('selectedDepartmentIds.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-slate-500">Run Details</h3>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="text-sm font-medium">Payroll Month</label>
                        <input wire:model="period" type="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('period') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">No of Day Paid</label>
                        <input wire:model="workingDays" type="number" min="1" max="31" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('workingDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">GSIS No. of Days</label>
                        <input wire:model="gsisDays" type="number" min="0" max="31" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('gsisDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 p-3">
                    <div class="text-sm font-medium">Inclusive Leave Types</div>
                    <p class="mt-1 text-xs text-slate-500">Checked leave types are included in this payroll run.</p>
                    <div class="mt-2 grid gap-x-3 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($leaveTypes as $leaveType)
                            <label class="flex min-h-7 items-center gap-2 text-xs text-slate-700">
                                <input
                                    wire:model="selectedLeaveTypeIds"
                                    type="checkbox"
                                    value="{{ $leaveType->leave_type_id }}"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="leading-tight">{{ $leaveType->leave_name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedLeaveTypeIds') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('selectedLeaveTypeIds.*') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

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

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3">
            @if ($showExistingGenerationNotice)
                <button type="button" wire:click="dismissExistingGenerationNotice" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    Review Configuration
                </button>
                <button type="button" wire:click="continueToPayrollGeneration" wire:loading.attr="disabled" wire:target="continueToPayrollGeneration" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="continueToPayrollGeneration">Proceed Anyway</span>
                    <span wire:loading wire:target="continueToPayrollGeneration">Opening...</span>
                </button>
            @else
            <button type="submit" wire:loading.attr="disabled" wire:target="proceed" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="proceed">Proceed to Payroll Generation</span>
                <span wire:loading wire:target="proceed">Preparing...</span>
            </button>
            @endif
        </div>
    </form>
</section>
