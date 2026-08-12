<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Schedule</h3>
            <p class="text-sm text-slate-600">Assignments from {{ $from }} to {{ $to }}.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['schedule.view', 'schedule.manage'])
                <a href="{{ route('schedule.dashboard') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Schedule calendar
                </a>
                <a href="{{ route('schedule.employees') }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Employee settings
                </a>
            @endcanany
        </div>
    </div>

    @if ($canManage)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h4 class="text-sm font-semibold text-slate-800">Default settings</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-xs font-medium text-slate-600">Default shift</label>
                    <select wire:model="default_shift_code_id" @disabled($uses_regular_weekday_schedule)
                            class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm disabled:bg-slate-50">
                        <option value="">—</option>
                        @foreach ($shiftCodes as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->code }}{{ $shift->name ? ' — '.$shift->name : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.live="uses_regular_weekday_schedule" class="rounded border-slate-300">
                    Regular weekday schedule
                </label>
                <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.live="can_rotate_shift" class="rounded border-slate-300">
                    Can rotate shift
                </label>
                <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                    Active
                </label>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="saveSettings"
                        class="rounded-md bg-[#696cff] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#5f61e6]">
                    Save settings
                </button>
            </div>
        </section>
    @elseif ($setting)
        <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Default settings</p>
            <p class="mt-1 text-slate-800">
                Shift: <span class="font-medium">{{ $setting->defaultShiftCode?->code ?: ($setting->defaultShiftCode?->name ?: '—') }}</span>
                · Active: <span class="font-medium">{{ $setting->is_active ? 'Yes' : 'No' }}</span>
                · Rotate: <span class="font-medium">{{ $setting->can_rotate_shift ? 'Yes' : 'No' }}</span>
            </p>
        </div>
    @else
        <div class="rounded-md border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            No schedule settings configured for this employee.
        </div>
    @endif

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 class="text-sm font-semibold text-slate-800">Assignments <span class="font-normal text-slate-500">({{ $assignments->count() }})</span></h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Date</th>
                        <th class="px-4 py-2 font-semibold">Shift</th>
                        <th class="px-4 py-2 font-semibold">Source</th>
                        <th class="px-4 py-2 font-semibold">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignments as $row)
                        <tr wire:key="hub-sched-{{ $row->id }}">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ optional($row->schedule_date)->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->shiftCode?->code ?: ($row->shiftCode?->name ?: '—') }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $row->source ?: '—' }}</td>
                            <td class="max-w-xs truncate px-4 py-2.5 text-slate-600" title="{{ $row->notes }}">{{ $row->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No schedule assignments in this window.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
