<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Employee Schedule Settings</h2>
            <p class="text-sm text-slate-600">
                Manage scheduling behavior for {{ $department?->department ?? 'your department' }}.
            </p>
        </div>
        <div class="flex justify-end px-4 mt-auto">
            <button wire:click="saveAll" wire:loading.attr="disabled" @if(empty($dirty)) disabled @endif
                class="btn rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 cursor-pointer disabled:opacity-50">
                <span wire:loading.remove wire:target="saveAll">Save All</span>
                <span wire:loading wire:target="saveAll">Saving...</span>
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="max-w-xs">
            <label class="text-sm font-medium">Employee Type</label>
            <select wire:model.live="employeeTypeFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @foreach ($employeeTypeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Employee ID</th>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Position</th>
                        <th class="px-4 py-3">Salary Grade / Step</th>
                        <th class="px-4 py-3">Default Shift</th>
                        <th class="px-4 py-3">Rotates / Shifts</th>
                        <th class="px-4 py-3">Regular Mon-Fri</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-slate-100">
                            <td class="px-4 py-3 text-slate-600">{{ $employee->emp_id }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $this->employeeName($employee) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $employee->position?->position_title ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $this->salaryGradeStep($employee) }}</td>
                            <td class="px-4 py-3">
                                <select wire:change="markDirty('{{ $employee->emp_id }}')" wire:model="settings.{{ $employee->emp_id }}.default_shift_code_id" class="w-full min-w-48 rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled($settings[$employee->emp_id]['uses_regular_weekday_schedule'] ?? true)>
                                    <option value="">Auto / default</option>
                                    @foreach ($shiftCodes as $shiftCode)
                                        <option value="{{ $shiftCode->id }}">{{ $shiftCode->code }} - {{ $shiftCode->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2">
                                    <input wire:change="markDirty('{{ $employee->emp_id }}')" wire:model.live="settings.{{ $employee->emp_id }}.can_rotate_shift" type="checkbox"
                                            @disabled($settings[$employee->emp_id]['uses_regular_weekday_schedule'] ?? true)>
                                    <span>Enable</span>
                                </label>
                            </td>
                            <td class="px-4 py-3">
                                <label class="inline-flex items-center gap-2">
                                    <input wire:change="markDirty('{{ $employee->emp_id }}')" wire:model.live="settings.{{ $employee->emp_id }}.uses_regular_weekday_schedule" type="checkbox">
                                    <span>8AM-5PM</span>
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">No active employees found for this department.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
