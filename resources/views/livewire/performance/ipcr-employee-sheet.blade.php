<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">IPCR · {{ $employee->full_name }}</h2>
            <p class="text-sm text-slate-600">{{ $period->label }} · {{ $employee->emp_id }} · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('performance.periods') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Periods</a>
            <a href="{{ route('performance.print', [$empId, $periodId]) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
            @if ($canManage)
                <button wire:click="openCreate" type="button" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Add target</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($showForm)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <form wire:submit="save" class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium">Reuse existing MFO (optional)</span>
                        <select wire:model="mfoId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Create new MFO text</option>
                            @foreach ($existingMfos as $mfo)
                                <option value="{{ $mfo->id }}">#{{ $mfo->id }} · {{ \Illuminate\Support\Str::limit($mfo->mfo, 80) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium">Function type</span>
                        <select wire:model="functionTypeId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($mfoTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->function_type }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="font-medium">MFO / success indicator</span>
                    <textarea wire:model="mfoText" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('mfoText') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium">Target</span>
                    <textarea wire:model="target" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('target') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium">Accomplishment</span>
                        <textarea wire:model="accomplishment" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium">Accomplishment date</span>
                        <input wire:model="accomplishmentDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeForm" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save target</button>
                </div>
            </form>
        </section>
    @endif

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Function</th>
                    <th class="px-4 py-3 text-left">MFO / Target</th>
                    <th class="px-4 py-3 text-left">Accomplishment</th>
                    <th class="px-4 py-3 text-left">Ratings</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($targets as $row)
                    <tr wire:key="ipcr-{{ $row->id }}">
                        <td class="px-4 py-3 text-slate-700">{{ $row->mfoSet?->mfo?->functionType?->function_type ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($row->mfoSet?->mfo?->mfo, 120) }}</p>
                            <p class="font-medium text-slate-800">{{ $row->target }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $row->accomplishment ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            @forelse ($row->ratings as $rating)
                                <div class="text-xs">
                                    Q{{ $rating->quality }}/E{{ $rating->effectiveness }}/T{{ $rating->timeliness }}
                                    avg {{ $rating->average ?? '—' }}
                                </div>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($canManage)
                                <button wire:click="edit({{ $row->id }})" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
                            @endif
                            @if ($canRate)
                                <button wire:click="openRating({{ $row->id }})" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Rate</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No targets for this period yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if ($ratingIpcrId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" wire:click="closeRating">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl" wire:click.stop>
                <h3 class="mb-3 text-lg font-semibold">Save rating</h3>
                <form wire:submit="saveRating" class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <label class="text-sm">Quality
                            <select wire:model="quality" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                                @foreach (range(1,5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm">Effectiveness
                            <select wire:model="effectiveness" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                                @foreach (range(1,5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm">Timeliness
                            <select wire:model="timeliness" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                                @foreach (range(1,5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <label class="block text-sm">Remarks
                        <input wire:model="ratingRemarks" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeRating" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                        <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
