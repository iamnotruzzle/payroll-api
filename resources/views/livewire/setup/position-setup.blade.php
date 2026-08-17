<div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Positions</h1>
            <p class="text-sm text-slate-500">Manage position references used by employee and plantilla records.</p>
        </div>
        <button
            type="button"
            x-on:click="erpOverlay.open($wire, 'position', { positionId: null, positionTitle: '', positionSalaryGrade: '', positionRemarks: '', positionActive: true })"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white"
        >Add Position</button>
    </div>
    @if(session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <section class="rounded-lg border bg-white p-5 shadow-sm">
        <input wire:model.live.debounce.300ms="search" placeholder="Search positions" class="w-full rounded-md border px-3 py-2">
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-2">Position</th>
                        <th>SG</th>
                        <th>Employees</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($positions as $position)
                        @php($active=($metadata->get($position->position_id)?->is_active)??true)
                        <tr>
                            <td class="py-3 font-medium">{{ $position->position_title }}</td>
                            <td>{{ $position->salary_grade }}</td>
                            <td>{{ $position->employees_count }}</td>
                            <td>{{ $active?'Active':'Archived' }}</td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    x-on:click="erpOverlay.open($wire, 'position', { positionId: {{ $position->position_id }}, positionTitle: @js($position->position_title), positionSalaryGrade: @js((string) $position->salary_grade), positionRemarks: @js((string) $position->remarks), positionActive: @js((bool) $active) }, true)"
                                    class="mr-3 text-xs font-semibold text-indigo-600"
                                >Edit</button>
                                <button wire:click="toggle({{ $position->position_id }})" wire:confirm="{{ $active?'Archive':'Restore' }} this position?" class="text-xs font-semibold">{{ $active?'Archive':'Restore' }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $positions->links() }}</div>
    </section>
    <x-setup-form-drawer name="position" title="Add Position" edit-title="Edit Position">
        <form wire:submit="save" class="space-y-4">
            <label class="block text-sm font-medium">
                Position title
                <input wire:model="positionTitle" maxlength="50" class="mt-1 w-full rounded-md border px-3 py-2">
            </label>

            <label class="block text-sm font-medium">
                Salary grade
                <select wire:model="positionSalaryGrade" required class="mt-1 w-full rounded-md border bg-white px-3 py-2">
                    <option value="">Select salary grade</option>
                    @foreach (range(1, 33) as $grade)
                        <option value="{{ $grade }}">SG {{ $grade }}</option>
                    @endforeach
                </select>
                @error('positionSalaryGrade')
                    <span class="mt-1 block text-xs font-normal text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block text-sm font-medium">
                Remarks
                <textarea wire:model="positionRemarks" rows="4" maxlength="50" class="mt-1 w-full rounded-md border px-3 py-2"></textarea>
            </label>

            <label class="flex gap-2 text-sm">
                <input wire:model="positionActive" type="checkbox">
                Available for new assignments
            </label>

            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" x-on:click="erpOverlay.close('position')" class="rounded-md border px-4 py-2">Cancel</button>
                <button class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Save Position</button>
            </div>
        </form>
    </x-setup-form-drawer>
</div>
