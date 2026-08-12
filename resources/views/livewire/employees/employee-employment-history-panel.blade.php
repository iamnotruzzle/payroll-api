<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Employment history</h3>
            <p class="text-sm text-slate-600">Plantilla item, position, and promotion trail. PDS Work Experience stays separate for prior employers.</p>
        </div>
        @if ($canManage && ! $showForm)
            <button type="button" wire:click="startCreate"
                    class="rounded-md bg-[#696cff] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#5f61e6]">
                Record change
            </button>
        @endif
    </div>

    @if ($showForm && $canManage)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-semibold text-slate-800">
                {{ $editingId ? 'Edit assignment' : 'New assignment (closes current)' }}
            </h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-sm font-medium">Effective from</label>
                    <input type="date" wire:model="effective_from" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('effective_from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @if ($editingId)
                    <div>
                        <label class="text-sm font-medium">Effective to</label>
                        <input type="date" wire:model="effective_to" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Leave blank for current assignment.</p>
                        @error('effective_to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label class="text-sm font-medium">Nature</label>
                    <select wire:model="nature" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        @foreach ($natures as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('nature') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Plantilla item no.</label>
                    <input type="text" wire:model="item_number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. OSEC-DOH-…">
                    @error('item_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Position</label>
                    <select wire:model="position_id" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="">—</option>
                        @foreach ($positions as $pos)
                            <option value="{{ $pos->position_id }}">{{ $pos->position_title }}{{ $pos->salary_grade ? ' (SG '.$pos->salary_grade.')' : '' }}</option>
                        @endforeach
                    </select>
                    @error('position_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Department</label>
                    <select wire:model="department_id" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="">—</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->department_id }}">{{ $dept->department }}</option>
                        @endforeach
                    </select>
                    @error('department_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Employment status</label>
                    <select wire:model="empstat_id" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="">—</option>
                        @foreach ($employmentStatuses as $stat)
                            <option value="{{ $stat->empstat_id }}">{{ $stat->status }}</option>
                        @endforeach
                    </select>
                    @error('empstat_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Step</label>
                    <input type="number" min="1" wire:model="step" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('step') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Salary grade (snapshot)</label>
                    <input type="number" min="1" wire:model="salary_grade" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Auto from position if blank">
                    @error('salary_grade') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="text-sm font-medium">Remarks</label>
                    <input type="text" wire:model="remarks" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="button" wire:click="save" class="rounded-md bg-[#696cff] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#5f61e6]">Save</button>
                <button type="button" wire:click="cancelForm" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Cancel</button>
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Assignments <span class="font-normal text-slate-500">({{ $rows->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Effective</th>
                        <th class="px-4 py-2 font-semibold">Nature</th>
                        <th class="px-4 py-2 font-semibold">Plantilla</th>
                        <th class="px-4 py-2 font-semibold">Position / SG-Step</th>
                        <th class="px-4 py-2 font-semibold">Department</th>
                        <th class="px-4 py-2 font-semibold">Status</th>
                        @if ($canManage)
                            <th class="px-4 py-2 font-semibold text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr wire:key="emp-hist-{{ $row->id }}">
                            <td class="px-4 py-2.5 text-slate-800">
                                <p class="font-medium">
                                    {{ optional($row->effective_from)->format('Y-m-d') ?: '—' }}
                                    <span class="text-slate-400">→</span>
                                    {{ $row->effective_to ? $row->effective_to->format('Y-m-d') : 'present' }}
                                </p>
                                @if ($row->isCurrent())
                                    <span class="mt-1 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Current</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">{{ \App\Services\Hris\EmploymentHistoryService::natureLabel((string) $row->nature) }}</td>
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ $row->item_number ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">
                                <p>{{ $row->position?->position_title ?: '—' }}</p>
                                <p class="text-xs text-slate-500">
                                    SG {{ $row->salary_grade ?? ($row->position?->salary_grade ?? '—') }}
                                    · Step {{ $row->step ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->department?->department ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->employmentStatus?->status ?: '—' }}</td>
                            @if ($canManage)
                                <td class="px-4 py-2.5 text-right whitespace-nowrap space-x-1">
                                    <button type="button" wire:click="startEdit({{ $row->id }})"
                                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium hover:bg-slate-50">Edit</button>
                                    @unless ($row->isCurrent())
                                        <button type="button" wire:click="deleteRow({{ $row->id }})" wire:confirm="Delete this historical assignment?"
                                                class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Delete</button>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 7 : 6 }}" class="px-4 py-8 text-center text-sm text-slate-500">No employment history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
