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
                <button type="button" x-on:click="erpOverlay.open($wire, 'ipcr-target', { editingId: null, mfoText: '', mfoId: null, functionTypeId: 2, target: '', accomplishment: '', accomplishmentDate: '' })" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Add target</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold uppercase text-slate-500">Weighted summary</h3>
        <div class="mt-2 flex flex-wrap gap-4 text-sm">
            <div><span class="text-slate-500">Average</span> <span class="font-semibold">{{ $summary['average'] ?? '—' }}</span></div>
            <div><span class="text-slate-500">Grade</span> <span class="font-semibold">{{ $summary['grade'] ?? '—' }}</span></div>
            <div><span class="text-slate-500">Strategic</span> {{ $summary['by_function']['strategic'] ?? '—' }}</div>
            <div><span class="text-slate-500">Core</span> {{ $summary['by_function']['core'] ?? '—' }}</div>
            <div><span class="text-slate-500">Support</span> {{ $summary['by_function']['support'] ?? '—' }}</div>
        </div>
        <p class="mt-2 text-xs text-slate-500">Weights: Strategic 40% · Core 50% · Support 10% (or Core 80% · Support 20% when no strategic). Calibrations override ratings when present.</p>
    </section>

    <x-setup-form-drawer name="ipcr-target" title="Add target" edit-title="Edit target" size="lg">
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
                <button type="button" x-on:click="erpOverlay.close('ipcr-target')" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save target</button>
            </div>
        </form>
    </x-setup-form-drawer>

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
                            @if ($row->calibrations->isNotEmpty())
                                <div class="mt-1 text-xs text-indigo-700">
                                    Cal:
                                    @foreach ($row->calibrations as $cal)
                                        {{ strtoupper(substr((string) $cal->calibration_type, 0, 1)) }}{{ $cal->score }}{{ ! $loop->last ? '/' : '' }}
                                    @endforeach
                                </div>
                            @endif
                            @if ($row->opcr)
                                <div class="mt-1 text-xs text-slate-500">OPCR budget {{ number_format((float) $row->opcr->budget, 2) }} · {{ $row->opcr->accountables->count() }} accountable(s)</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($canManage)
                                <button type="button" x-on:click="erpOverlay.open($wire, 'ipcr-target', { editingId: {{ $row->id }}, mfoId: @js($row->mfo_set_id ? ($row->mfoSet?->mfo_id) : null), mfoText: @js((string) ($row->mfoSet?->mfo?->mfo ?? '')), functionTypeId: {{ (int) ($row->mfoSet?->mfo?->function_type_id ?? 2) }}, target: @js((string) $row->target), accomplishment: @js((string) ($row->accomplishment ?? '')), accomplishmentDate: @js(optional($row->accomplishment_date)?->toDateString() ?: '') }, true)" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
                            @endif
                            @if ($canRate)
                                <button type="button" x-on:click="erpOverlay.open($wire, 'ipcr-rating', { ratingIpcrId: {{ $row->id }}, quality: @js((string) ($row->ratings->firstWhere('rating_by', (string) (auth()->user()?->emp_id ?? ''))?->quality ?? '3')), effectiveness: @js((string) ($row->ratings->firstWhere('rating_by', (string) (auth()->user()?->emp_id ?? ''))?->effectiveness ?? '3')), timeliness: @js((string) ($row->ratings->firstWhere('rating_by', (string) (auth()->user()?->emp_id ?? ''))?->timeliness ?? '3')), ratingRemarks: @js((string) ($row->ratings->firstWhere('rating_by', (string) (auth()->user()?->emp_id ?? ''))?->remarks ?? '')) })" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Rate</button>
                            @endif
                            @if ($canCalibrate)
                                <button type="button" x-on:click="erpOverlay.open($wire, 'ipcr-calibrate', { calibrateIpcrId: {{ $row->id }}, calQuality: @js((string) ($row->calibrations->firstWhere('calibration_type', 'quality')?->score ?? '3')), calEffectiveness: @js((string) ($row->calibrations->firstWhere('calibration_type', 'effectiveness')?->score ?? '3')), calTimeliness: @js((string) ($row->calibrations->firstWhere('calibration_type', 'timeliness')?->score ?? '3')), calNotes: @js((string) ($row->calibrations->first()?->calibration ?? '')) })" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Calibrate</button>
                            @endif
                            @if ($canManage || $canCalibrate)
                                <button type="button" x-on:click="erpOverlay.open($wire, 'ipcr-opcr', { opcrIpcrId: {{ $row->id }}, opcrBudget: @js($row->opcr?->budget !== null ? (string) $row->opcr->budget : ''), opcrAccountables: @js($row->opcr?->accountables ? $row->opcr->accountables->pluck('emp_id')->implode(', ') : '') })" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">OPCR</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No targets for this period yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <x-setup-form-modal name="ipcr-rating" title="Save rating" size="sm">
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
                <button type="button" x-on:click="erpOverlay.close('ipcr-rating')" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save</button>
            </div>
        </form>
    </x-setup-form-modal>

    <x-setup-form-modal name="ipcr-calibrate" title="Calibrate scores" size="sm">
        <form wire:submit="saveCalibration" class="space-y-3">
            <div class="grid grid-cols-3 gap-3">
                <label class="text-sm">Quality
                    <select wire:model="calQuality" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                        @foreach (range(1,5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">Effectiveness
                    <select wire:model="calEffectiveness" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                        @foreach (range(1,5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">Timeliness
                    <select wire:model="calTimeliness" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                        @foreach (range(1,5) as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="block text-sm">Calibration notes
                <textarea wire:model="calNotes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="erpOverlay.close('ipcr-calibrate')" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save</button>
            </div>
        </form>
    </x-setup-form-modal>

    <x-setup-form-modal name="ipcr-opcr" title="OPCR link" size="sm">
        <form wire:submit="saveOpcr" class="space-y-3">
            <label class="block text-sm">Budget
                <input wire:model="opcrBudget" type="number" step="0.01" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block text-sm">Accountable employee IDs
                <input wire:model="opcrAccountables" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="000123, 000456">
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="erpOverlay.close('ipcr-opcr')" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save</button>
            </div>
        </form>
    </x-setup-form-modal>
</div>
