<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Import Employee Masterlist</h2>
            <p class="mt-1 text-sm text-slate-600">Preview the workbook, resolve reference values, and approve changes before HRIS is updated.</p>
        </div>
        <a href="{{ route('employees.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Back to Employees</a>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 lg:grid-cols-[minmax(280px,1fr)_180px]">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Masterlist workbook</span>
                <input wire:model="file" type="file" accept=".xlsx,.xlsm,.xls" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <span class="mt-1 block text-xs text-slate-500">Maximum 20 MB. The workbook must contain a sheet named Masterlist.</span>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Effective date</span>
                <input wire:model="effectiveDate" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <label class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm"><input wire:model="identity" type="checkbox" class="mt-0.5"> <span><strong>Identity</strong><small class="block text-slate-500">Names, DOB, sex, appointment date</small></span></label>
            <label class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm"><input wire:model="employment" type="checkbox" class="mt-0.5"> <span><strong>Employment</strong><small class="block text-slate-500">Position, department, SG, step, item</small></span></label>
            <label class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm"><input wire:model="governmentIds" type="checkbox" class="mt-0.5"> <span><strong>Government IDs</strong><small class="block text-slate-500">Explicit opt-in; blanks never clear values</small></span></label>
            <label class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm"><input wire:model="payrollProfile" type="checkbox" class="mt-0.5"> <span><strong>Payroll profile</strong><small class="block text-slate-500">RC, MP2, LBP, batch and fund</small></span></label>
            <label class="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm"><input wire:model="createNew" type="checkbox" class="mt-0.5"> <span><strong>Create new employees</strong><small class="block text-slate-500">No user accounts are provisioned</small></span></label>
        </div>

        <div class="mt-4 flex justify-end">
            <button wire:click="preview" wire:loading.attr="disabled" class="rounded-md bg-[#696cff] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                <span wire:loading.remove wire:target="preview">Build Preview</span><span wire:loading wire:target="preview">Reading workbook…</span>
            </button>
        </div>
    </section>

    @if ($import)
        <section class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ([['Rows',$import->total_rows,'border-slate-200','text-slate-700'],['New',$import->new_rows,'border-blue-200','text-blue-700'],['Changed',$import->changed_rows,'border-indigo-200','text-indigo-700'],['Unchanged',$import->unchanged_rows,'border-slate-200','text-slate-700'],['Warnings',$import->warning_rows,'border-amber-200','text-amber-700'],['Errors',$import->error_rows,'border-red-200','text-red-700']] as [$label,$value,$borderClass,$textClass])
                <div class="rounded-lg border {{ $borderClass }} bg-white p-4 shadow-sm"><div class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</div><div class="mt-1 text-2xl font-bold {{ $textClass }}">{{ number_format($value) }}</div></div>
            @endforeach
        </section>

        @if ($unresolvedPositions->isNotEmpty() || $unresolvedDepartments->isNotEmpty())
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                <h3 class="font-semibold text-amber-950">Resolve workbook references</h3>
                <p class="mt-1 text-sm text-amber-800">Mappings affect only this staged import and do not create new position or department records.</p>
                <div class="mt-4 grid gap-5 lg:grid-cols-2">
                    @if ($unresolvedPositions->isNotEmpty())
                        <div class="space-y-2">
                            <div class="text-xs font-semibold uppercase text-amber-900">Unmatched positions</div>
                            <div class="flex flex-wrap gap-2">@foreach ($unresolvedPositions as $label)<button wire:click="choosePositionSource(@js($label))" class="rounded-full border px-3 py-1 text-xs {{ $positionSource === $label ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-amber-300 bg-white' }}">{{ $label }}</button>@endforeach</div>
                            <div class="flex gap-2"><select wire:model="positionTarget" class="min-w-0 flex-1 rounded-md border border-amber-300 px-3 py-2 text-sm"><option value="">Choose matching HRIS position</option>@foreach ($positions as $position)<option value="{{ $position->position_id }}">{{ $position->position_title }}</option>@endforeach</select><button wire:click="mapPosition" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Map</button></div>
                        </div>
                    @endif
                    @if ($unresolvedDepartments->isNotEmpty())
                        <div class="space-y-2">
                            <div class="text-xs font-semibold uppercase text-amber-900">Unmatched departments</div>
                            <div class="flex flex-wrap gap-2">@foreach ($unresolvedDepartments as $item)<button wire:click="chooseDepartmentSource(@js($item['division']), @js($item['department']))" class="rounded-full border px-3 py-1 text-xs {{ $departmentDivisionSource === $item['division'] && $departmentSource === $item['department'] ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-amber-300 bg-white' }}">{{ $item['division'] }} → {{ $item['department'] }}</button>@endforeach</div>
                            <div class="flex gap-2"><select wire:model="departmentTarget" class="min-w-0 flex-1 rounded-md border border-amber-300 px-3 py-2 text-sm"><option value="">Choose matching HRIS department</option>@foreach ($departments as $department)<option value="{{ $department->department_id }}">{{ $department->division?->division }} → {{ $department->department }}</option>@endforeach</select><button wire:click="mapDepartment" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Map</button></div>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach (['changes'=>'Changes','new'=>'New','update'=>'Updates','unchanged'=>'Unchanged','warnings'=>'Warnings','errors'=>'Errors','all'=>'All'] as $value=>$label)
                        <button wire:click="$set('filter','{{ $value }}')" class="rounded-md px-3 py-1.5 text-xs font-semibold {{ $filter === $value ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="flex gap-2"><input wire:model.live.debounce.300ms="search" placeholder="Employee ID" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><button wire:click="selectActionable(true)" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold">Select actionable</button><button wire:click="selectActionable(false)" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold">Clear</button></div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">Use</th><th class="px-3 py-3">Row</th><th class="px-3 py-3">Employee</th><th class="px-3 py-3">Action</th><th class="px-3 py-3">Changes</th><th class="px-3 py-3">Validation</th><th class="px-3 py-3">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr wire:key="masterlist-row-{{ $row->id }}" class="align-top">
                                <td class="px-3 py-3"><input type="checkbox" @checked($row->selected) @disabled($row->action === 'unchanged' || $row->status !== 'pending') wire:click="toggleRow({{ $row->id }})"></td>
                                <td class="px-3 py-3 text-slate-500">{{ $row->source_row }}</td>
                                <td class="whitespace-nowrap px-3 py-3"><strong>{{ $row->emp_id }}</strong><div class="text-xs text-slate-500">{{ $row->source_payload['lastname'] }}, {{ $row->source_payload['firstname'] }}</div></td>
                                <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold">{{ ucfirst($row->action) }}</span></td>
                                <td class="min-w-[360px] px-3 py-3"><div class="space-y-1">@forelse ($row->changes ?? [] as $change)<div class="text-xs"><strong>{{ str_replace('_',' ',ucfirst($change['field'])) }}:</strong> <span class="text-slate-500">{{ $change['group'] === 'government_ids' && $change['current'] ? '••••'.substr((string)$change['current'],-4) : ($change['current'] ?? '—') }}</span> → <span class="font-semibold">{{ $change['group'] === 'government_ids' && $change['incoming'] ? '••••'.substr((string)$change['incoming'],-4) : ($change['incoming'] ?? '—') }}</span></div>@empty<span class="text-xs text-slate-400">No changes</span>@endforelse</div></td>
                                <td class="min-w-[260px] px-3 py-3">@foreach ($row->errors ?? [] as $message)<div class="text-xs font-semibold text-red-700">{{ $message }}</div>@endforeach @foreach ($row->warnings ?? [] as $message)<div class="text-xs text-amber-700">{{ $message }}</div>@endforeach</td>
                                <td class="px-3 py-3"><span class="text-xs font-semibold">{{ ucfirst($row->status) }}</span>@if($row->failure_message)<div class="mt-1 max-w-xs text-xs text-red-600">{{ $row->failure_message }}</div>@endif</td>
                            </tr>
                        @empty<tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No rows match this filter.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">{{ $rows->links() }}</div>
        </section>

        <section class="rounded-lg border border-red-200 bg-red-50 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div><h3 class="font-semibold text-red-950">Apply selected changes</h3><p class="mt-1 text-sm text-red-800">Rows with errors are skipped. Employees changed since this preview are rejected. Missing and blank workbook values never deactivate or clear employees.</p></div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end"><label><span class="mb-1 block text-xs font-semibold uppercase text-red-800">Type IMPORT {{ $import->id }}</span><input wire:model="confirmation" class="rounded-md border border-red-300 px-3 py-2 text-sm" autocomplete="off"></label><button wire:click="apply" wire:confirm="Apply the selected Masterlist changes to HRIS?" wire:loading.attr="disabled" class="rounded-md bg-red-700 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">Apply Import</button></div>
            </div>
        </section>
    @endif
</div>
