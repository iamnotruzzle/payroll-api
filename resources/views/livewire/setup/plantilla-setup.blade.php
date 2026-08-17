<div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Plantilla Registry</h1>
            <p class="text-sm text-slate-600">Manage authorized items and effective-dated incumbents.</p>
        </div>
        <button
            type="button"
            x-on:click="erpOverlay.open($wire, 'plantilla-item', { itemId: null, itemNumber: '', positionId: null, departmentId: null, salaryGrade: '', fundType: '', authorizationYear: '', status: 'vacant', effectiveFrom: @js(now()->toDateString()), effectiveTo: '', remarks: '' })"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white"
        >Add Item</button>
    </div>
    @if(session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <section class="rounded-lg border bg-white p-5 shadow-sm">
        <input wire:model.live.debounce.300ms="search" placeholder="Search item number" class="w-full rounded-md border px-3 py-2">
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2">Item</th>
                        <th>Position / Office</th>
                        <th>SG</th>
                        <th>Status / Incumbent</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($items as $item)
                        <tr>
                            <td class="py-3 font-semibold">{{ $item->item_number }}</td>
                            <td>
                                {{ $item->position?->position_title }}
                                <div class="text-xs text-slate-500">{{ $item->department?->department }}</div>
                            </td>
                            <td>{{ $item->salary_grade }}</td>
                            <td>
                                {{ ucfirst($item->status) }}
                                <div class="text-xs text-slate-500">{{ $item->currentAssignment?->employee?->full_name ?? 'No incumbent' }}</div>
                            </td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    x-on:click="erpOverlay.open($wire, 'plantilla-item', { itemId: {{ $item->id }}, itemNumber: @js($item->item_number), positionId: @js($item->position_id), departmentId: @js($item->department_id), salaryGrade: @js((string) $item->salary_grade), fundType: @js((string) $item->fund_type), authorizationYear: @js((string) $item->authorization_year), status: @js($item->status), effectiveFrom: @js(optional($item->effective_from)?->toDateString()), effectiveTo: @js(optional($item->effective_to)?->toDateString() ?? ''), remarks: @js((string) $item->remarks) }, true)"
                                    class="mr-3 text-xs font-semibold text-indigo-600"
                                >Edit</button>
                                <button
                                    type="button"
                                    x-on:click="erpOverlay.open($wire, 'plantilla-assignment', { assignmentItemId: {{ $item->id }} })"
                                    class="text-xs font-semibold text-emerald-700"
                                >Assign Incumbent</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    </section>
    <x-setup-form-drawer name="plantilla-item" title="Add Plantilla Item" edit-title="Edit Plantilla Item">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium">Item number<input wire:model="itemNumber" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="text-sm font-medium">Position
                <select wire:model="positionId" class="mt-1 w-full rounded-md border px-3 py-2">
                    <option value="">Choose position</option>
                    @foreach($positions as $position)
                        <option value="{{ $position->position_id }}">{{ $position->position_title }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium sm:col-span-2">Department
                <select wire:model="departmentId" class="mt-1 w-full rounded-md border px-3 py-2">
                    <option value="">Choose department</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->division?->division }} → {{ $department->department }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Salary grade<input wire:model="salaryGrade" type="number" min="1" max="33" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="text-sm font-medium">Status
                <select wire:model="status" class="mt-1 w-full rounded-md border px-3 py-2">
                    @foreach(['vacant','occupied','frozen','abolished'] as $value)
                        <option>{{ $value }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Fund type<input wire:model="fundType" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="text-sm font-medium">Authorization year<input wire:model="authorizationYear" type="number" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="text-sm font-medium">Effective from<input wire:model="effectiveFrom" type="date" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="text-sm font-medium">Effective to<input wire:model="effectiveTo" type="date" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="text-sm font-medium sm:col-span-2">Remarks<textarea wire:model="remarks" rows="4" class="mt-1 w-full rounded-md border px-3 py-2"></textarea></label>
            @if($errors->any())
                <div class="sm:col-span-2 text-sm text-rose-600">{{ $errors->first() }}</div>
            @endif
            <div class="flex justify-end gap-3 border-t pt-4 sm:col-span-2">
                <button type="button" x-on:click="erpOverlay.close('plantilla-item')" class="rounded-md border px-4 py-2">Cancel</button>
                <button class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Save Item</button>
            </div>
        </form>
    </x-setup-form-drawer>
    <x-setup-form-drawer name="plantilla-assignment" title="Assign Incumbent" description="The previous item and incumbent assignments are closed automatically.">
        <form wire:submit="assign" class="space-y-4">
            <label class="block text-sm font-medium">Plantilla item
                <select wire:model="assignmentItemId" class="mt-1 w-full rounded-md border px-3 py-2">
                    @foreach($itemOptions as $item)
                        <option value="{{ $item->id }}">{{ $item->item_number }} — {{ $item->position?->position_title }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Employee
                <select wire:model="employeeId" class="mt-1 w-full rounded-md border px-3 py-2">
                    <option value="">Choose employee</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->emp_id }}">{{ $employee->emp_id }} — {{ $employee->lastname }}, {{ $employee->firstname }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Assignment date<input wire:model="assignmentDate" type="date" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="block text-sm font-medium">Nature
                <select wire:model="nature" class="mt-1 w-full rounded-md border px-3 py-2">
                    @foreach($natures as $value=>$label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium">Remarks<textarea wire:model="assignmentRemarks" rows="4" class="mt-1 w-full rounded-md border px-3 py-2"></textarea></label>
            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" x-on:click="erpOverlay.close('plantilla-assignment')" class="rounded-md border px-4 py-2">Cancel</button>
                <button wire:confirm="Record this effective-dated assignment?" class="rounded-md bg-emerald-700 px-4 py-2 font-semibold text-white">Record Assignment</button>
            </div>
        </form>
    </x-setup-form-drawer>
</div>
