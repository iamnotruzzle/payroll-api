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
                        <select
                            data-select2-searchable
                            data-model="employeeTypeFilter"
                            data-defer-request="true"
                            data-placeholder="Select employee types"
                            multiple
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 pr-10 text-sm"
                        >
                            @foreach ($employeeTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(in_array($value, $employeeTypeFilter, true))>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('employeeTypeFilter') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('employeeTypeFilter.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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

                <div class="grid gap-3 md:grid-cols-3" x-data="{ payrollMonth: @entangle('period'), gsisDays: @entangle('gsisDays') }" x-effect="if (payrollMonth) { const [year, month] = payrollMonth.split('-').map(Number); gsisDays = new Date(year, month, 0).getDate(); }">
                    <div>
                        <label class="text-sm font-medium">Payroll Month</label>
                        <input x-model="payrollMonth" type="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('period') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">No of Day Paid</label>
                        <input wire:model="workingDays" type="number" min="1" max="31" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('workingDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">GSIS No. of Days</label>
                        <input x-model="gsisDays" type="number" readonly class="mt-1 w-full cursor-not-allowed rounded-md border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-600">
                        <p class="mt-1 text-xs text-slate-500">Automatically based on the selected payroll month.</p>
                        @error('gsisDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-md border border-slate-200">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-3 py-3">
                        <div>
                            <div class="text-sm font-medium">Holidays &amp; Work Suspensions</div>
                            <p class="mt-1 text-xs text-slate-500">Calendar entries for {{ $periodLabel }} used by DTR and MRA processing.</p>
                        </div>
                        @if ($canManageHolidays)
                            <button
                                type="button"
                                x-on:click="erpOverlay.open($wire, 'payroll-holiday', { holidayEditingId: null, holidayDate: @js($period.'-01'), holidayName: '', holidayType: 'REGULAR', holidayScope: 'FULL_DAY', holidayIsPaid: true, holidayIsActive: true })"
                                class="rounded-md border border-blue-300 bg-white px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50"
                            >
                                Add calendar entry
                            </button>
                        @endif
                    </div>
                    @if (session('holiday_status'))
                        <div class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">{{ session('holiday_status') }}</div>
                    @endif
                    <div class="divide-y divide-slate-100">
                        @forelse ($holidays as $holiday)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5">
                                <div class="flex min-w-0 items-start gap-3">
                                    <time class="w-12 shrink-0 text-center">
                                        <span class="block text-xs font-semibold uppercase text-slate-500">{{ $holiday->holiday_date->format('D') }}</span>
                                        <span class="block text-sm font-semibold text-slate-800">{{ $holiday->holiday_date->format('M d') }}</span>
                                    </time>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-slate-800">{{ $holiday->name }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            {{ $holiday->holiday_type === 'WORK_SUSPENSION' ? 'Work suspension' : ucfirst(strtolower($holiday->holiday_type)).' holiday' }}
                                            · {{ str($holiday->holiday_scope)->replace('_', ' ')->lower()->title() }}
                                            · {{ $holiday->is_paid ? 'Paid' : 'Unpaid' }}
                                            @unless ($holiday->is_active) · Inactive @endunless
                                        </div>
                                    </div>
                                </div>
                                @if ($canManageHolidays)
                                    <button
                                        type="button"
                                        x-on:click="erpOverlay.open($wire, 'payroll-holiday', { holidayEditingId: {{ $holiday->id }}, holidayDate: @js($holiday->holiday_date->format('Y-m-d')), holidayName: @js($holiday->name), holidayType: @js($holiday->holiday_type), holidayScope: @js($holiday->holiday_scope), holidayIsPaid: @js((bool) $holiday->is_paid), holidayIsActive: @js((bool) $holiday->is_active) }, true)"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Edit
                                    </button>
                                @endif
                            </div>
                        @empty
                            <div class="px-3 py-5 text-center text-xs text-slate-500">No holidays or work suspensions recorded for this payroll month.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 p-3">
                    <div class="text-sm font-medium">Inclusive Dates for Leaves</div>
                    <p class="mt-1 text-xs text-slate-500">Only HRIS leave dates within this range will be processed. Dates finalized in an earlier payroll run remain blocked.</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium text-slate-600">From</label>
                            <input wire:model="leavePeriodStart" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('leavePeriodStart') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs font-medium text-slate-600">To</label>
                            <input wire:model="leavePeriodEnd" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('leavePeriodEnd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
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

    @if ($canManageHolidays)
        <x-setup-form-modal name="payroll-holiday" title="New Calendar Entry" edit-title="Edit Calendar Entry" size="sm">
            <form wire:submit="saveHoliday" class="space-y-4">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Date</span>
                    <input wire:model="holidayDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('holidayDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Name</span>
                    <input wire:model="holidayName" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. National Heroes Day">
                    @error('holidayName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Entry type</span>
                        <select wire:model="holidayType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="REGULAR">Regular holiday</option>
                            <option value="SPECIAL">Special holiday</option>
                            <option value="WORK_SUSPENSION">Work suspension</option>
                        </select>
                        @error('holidayType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Coverage</span>
                        <select wire:model="holidayScope" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="FULL_DAY">Full day</option>
                            <option value="FIRST_HALF">First half</option>
                            <option value="SECOND_HALF">Second half</option>
                        </select>
                        @error('holidayScope') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
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
                    <button x-on:click="erpOverlay.close('payroll-holiday')" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveHoliday" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveHoliday" x-text="editing ? 'Update entry' : 'Save entry'"></span>
                        <span wire:loading wire:target="saveHoliday">Saving...</span>
                    </button>
                </div>
            </form>
        </x-setup-form-modal>
    @endif
</section>
