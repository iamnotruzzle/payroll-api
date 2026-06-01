<div class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">DTR Correction Approvers</h2>
        <p class="text-sm text-slate-600">Set the default approver for employee time-in and time-out correction requests in {{ $department?->department ?? 'your department' }}.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(260px,360px)_auto] lg:items-end">
            <div>
                <h3 class="font-semibold text-slate-900">Bulk Update</h3>
                <p class="text-sm text-slate-600">Select employees from the table, choose one approver, then apply the same approver to all selected employees.</p>
                @error('selectedEmployees') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Approver for Selected Employees</span>
                <select wire:model="bulkApproverEmpId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select approver</option>
                    @foreach ($approverOptions as $approver)
                        <option value="{{ $approver->emp_id }}">{{ $approver->full_name }} ({{ $approver->emp_id }})</option>
                    @endforeach
                </select>
                @error('bulkApproverEmpId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex flex-wrap justify-end gap-2">
                <button wire:click="selectAll" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                    Select All
                </button>
                <button wire:click="clearSelection" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                    Clear
                </button>
                <button wire:click="saveBulkApprover" type="button" class="rounded-md bg-[#696cff] px-3 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
                    Apply
                </button>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="w-12 px-4 py-3 text-left">Select</th>
                        <th class="px-4 py-3 text-left">Employee</th>
                        <th class="px-4 py-3 text-left">Position</th>
                        <th class="px-4 py-3 text-left">Default Approver</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="px-4 py-3 align-middle">
                                <input wire:model="selectedEmployees.{{ $employee->emp_id }}" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $employee->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $employee->emp_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $employee->position?->position_title ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <select wire:model="approverSelections.{{ $employee->emp_id }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select approver</option>
                                    @foreach ($approverOptions as $approver)
                                        @if ($approver->emp_id !== $employee->emp_id)
                                            <option value="{{ $approver->emp_id }}">{{ $approver->full_name }} ({{ $approver->emp_id }})</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error("approverSelections.{$employee->emp_id}") <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="saveApprover('{{ $employee->emp_id }}')" type="button" class="rounded-md bg-[#696cff] px-3 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
                                    Save
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No active employees found for this department.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
