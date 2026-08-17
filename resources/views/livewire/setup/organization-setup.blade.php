<div class="space-y-5">
    <header>
        <h1 class="text-xl font-semibold">Organization</h1>
        <p class="text-sm text-slate-500">Manage divisions and departments without changing finalized payroll snapshots.</p>
    </header>
    @if(session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-lg border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Divisions</h2>
                <button type="button" x-on:click="erpOverlay.open($wire, 'organization-division', { divisionId: null, divisionName: '', divisionSpecialTitle: '', divisionActive: true })" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Add Division</button>
            </div>
            <div class="mt-4 max-h-[650px] divide-y overflow-auto">
                @foreach($divisions as $division)
                    @php($active=($metadata->get('division|'.$division->division_id)?->is_active)??true)
                    <div class="flex justify-between gap-3 py-3">
                        <div>
                            <strong class="text-sm">{{ $division->division }}</strong>
                            <div class="text-xs text-slate-500">{{ $division->departments_count }} departments · {{ $active?'Active':'Archived' }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-on:click="erpOverlay.open($wire, 'organization-division', { divisionId: {{ $division->division_id }}, divisionName: @js($division->division), divisionSpecialTitle: @js((string) $division->special_title), divisionActive: @js((bool) $active) }, true)" class="text-xs font-semibold text-indigo-600">Edit</button>
                            <button wire:click="toggleReference('division',{{ $division->division_id }})" wire:confirm="{{ $active?'Archive':'Restore' }} this division? Finalized payrolls will remain unchanged." class="text-xs font-semibold">{{ $active?'Archive':'Restore' }}</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="rounded-lg border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Departments</h2>
                <button type="button" x-on:click="erpOverlay.open($wire, 'organization-department', { departmentId: null, departmentName: '', departmentDivisionId: null, departmentActive: true })" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Add Department</button>
            </div>
            <input wire:model.live.debounce.300ms="search" placeholder="Search departments" class="mt-4 w-full rounded-md border px-3 py-2">
            <div class="mt-2 max-h-[590px] divide-y overflow-auto">
                @foreach($departments as $department)
                    @php($active=($metadata->get('department|'.$department->department_id)?->is_active)??true)
                    <div class="flex justify-between gap-3 py-3">
                        <div>
                            <strong class="text-sm">{{ $department->department }}</strong>
                            <div class="text-xs text-slate-500">{{ $department->division?->division }} · {{ $active?'Active':'Archived' }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-on:click="erpOverlay.open($wire, 'organization-department', { departmentId: {{ $department->department_id }}, departmentName: @js($department->department), departmentDivisionId: @js($department->division_id), departmentActive: @js((bool) $active) }, true)" class="text-xs font-semibold text-indigo-600">Edit</button>
                            <button wire:click="toggleReference('department',{{ $department->department_id }})" wire:confirm="{{ $active?'Archive':'Restore' }} this department? Finalized payrolls will remain unchanged." class="text-xs font-semibold">{{ $active?'Archive':'Restore' }}</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    <x-setup-form-drawer name="organization-division" title="Add Division" edit-title="Edit Division">
        <form wire:submit="saveDivision" class="space-y-4">
            <label class="block text-sm font-medium">Division name<input wire:model="divisionName" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            @error('divisionName')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
            <label class="block text-sm font-medium">Special title<input wire:model="divisionSpecialTitle" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="flex gap-2 text-sm"><input wire:model="divisionActive" type="checkbox"> Available for new assignments</label>
            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" x-on:click="erpOverlay.close('organization-division')" class="rounded-md border px-4 py-2">Cancel</button>
                <button class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Save Division</button>
            </div>
        </form>
    </x-setup-form-drawer>
    <x-setup-form-drawer name="organization-department" title="Add Department" edit-title="Edit Department">
        <form wire:submit="saveDepartment" class="space-y-4">
            <label class="block text-sm font-medium">Department name<input wire:model="departmentName" class="mt-1 w-full rounded-md border px-3 py-2"></label>
            <label class="block text-sm font-medium">Division
                <select wire:model="departmentDivisionId" class="mt-1 w-full rounded-md border px-3 py-2">
                    <option value="">Choose division</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->division_id }}">{{ $division->division }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex gap-2 text-sm"><input wire:model="departmentActive" type="checkbox"> Available for new assignments</label>
            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" x-on:click="erpOverlay.close('organization-department')" class="rounded-md border px-4 py-2">Cancel</button>
                <button class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Save Department</button>
            </div>
        </form>
    </x-setup-form-drawer>
</div>
