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
                        <select wire:model="payrollType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($payrollTypes as $type)
                                <option value="{{ $type->code }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('payrollType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Employee Type</label>
                        <select wire:model="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
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
                        <select wire:model.live="divisionId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Choose division</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->division_id }}">{{ $division->division }}</option>
                            @endforeach
                        </select>
                        @error('divisionId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Department <span class="font-normal text-slate-500">(Optional)</span></label>
                        <select wire:model="departmentId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">All departments in division</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->department_id }}">{{ $department->department }}</option>
                            @endforeach
                        </select>
                        @error('departmentId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-4">
                <div>
                    <h3 class="text-sm font-semibold uppercase text-slate-500">Run Details</h3>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                        <label class="text-sm font-medium">Payroll Month</label>
                        <input wire:model="period" type="month" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('period') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium">Working Days</label>
                        <input wire:model="workingDays" type="number" min="1" max="31" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('workingDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-4 py-3">
            <button type="submit" wire:loading.attr="disabled" wire:target="proceed" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="proceed">Proceed to Payroll Generation</span>
                <span wire:loading wire:target="proceed">Preparing...</span>
            </button>
        </div>
    </form>
</section>
