<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My Leave</h2>
            <p class="text-sm text-slate-600">File and track your own leave requests.</p>
        </div>
        @if ($canFile)
            <button type="button" x-on:click="erpOverlay.open($wire, 'my-leave', { leaveType: null, dateMode: 'weekdays', startDate: @js(now()->toDateString()), endDate: @js(now()->toDateString()), selectedDatesCsv: '', daysWpay: '1', daysWopay: '0', autoSplitCredits: true, applicantNote: '' })" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
                Apply leave
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <p class="font-semibold text-slate-800">{{ $employee->full_name }}</p>
        <p class="text-sm text-slate-600">{{ $employee->emp_id }} · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}</p>
        <p class="mt-2 text-sm">
            VL <strong>{{ number_format((float) $employee->vacation_leave_credits, 3) }}</strong>
            <span class="mx-2 text-slate-300">|</span>
            SL <strong>{{ number_format((float) $employee->sick_leave_credits, 3) }}</strong>
        </p>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($leaves as $leave)
                    @php $pending = $leaveService->isPending($leave); @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $leave->leave_type_name }}</p>
                            <p class="text-xs text-slate-500">{{ number_format((float) $leave->days_wpay, 2) }} WP / {{ number_format((float) $leave->days_wopay, 2) }} WOP</p>
                        </td>
                        <td class="px-4 py-3">{{ optional($leave->start_date)->format('Y-m-d') }} → {{ optional($leave->end_date)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $leave->status_name ?: \App\Support\Hris\LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('leave.requests.print', $leave->leave_id) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
                            @if ($pending && $canFile)
                                <button wire:click="cancelRequest({{ $leave->leave_id }})" wire:confirm="Cancel this leave request?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">You have no leave requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <x-setup-form-modal name="my-leave" title="Apply leave" size="lg">
        <form wire:submit="submit" class="grid gap-4 sm:grid-cols-2">
            <label class="sm:col-span-2">
                <span class="text-xs font-semibold uppercase text-slate-500">Leave type</span>
                <select wire:model="leaveType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select type</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->leave_type_id }}">{{ $type->leave_name }}</option>
                    @endforeach
                </select>
                @error('leaveType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="sm:col-span-2">
                <span class="text-xs font-semibold uppercase text-slate-500">Date mode</span>
                <select wire:model.live="dateMode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="weekdays">Fill range as weekdays (Mon–Fri)</option>
                    <option value="calendar">Fill range as calendar days</option>
                    <option value="pick">Pick specific dates</option>
                </select>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Start</span>
                <input wire:model.live="startDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('startDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">End</span>
                <input wire:model.live="endDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('endDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="sm:col-span-2" x-show="$wire.dateMode === 'pick'" x-cloak>
                <span class="text-xs font-semibold uppercase text-slate-500">Selected dates (CSV)</span>
                <textarea wire:model.lazy="selectedDatesCsv" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm" placeholder="2026-08-03,2026-08-05"></textarea>
                @error('selectedDatesCsv') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <p class="sm:col-span-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600" x-show="$wire.dateMode !== 'pick'" x-cloak>
                Selected days: <span class="font-semibold">{{ $previewDayCount }}</span>
            </p>
            <label class="sm:col-span-2">
                <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                    <input wire:model.live="autoSplitCredits" type="checkbox" class="rounded border-slate-300">
                    Auto-split with-pay / without-pay from credits
                </span>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Days with pay</span>
                <input wire:model="daysWpay" type="number" step="0.001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled($autoSplitCredits)>
            </label>
            <label>
                <span class="text-xs font-semibold uppercase text-slate-500">Days w/o pay</span>
                <input wire:model="daysWopay" type="number" step="0.001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled($autoSplitCredits)>
            </label>
            <label class="sm:col-span-2">
                <span class="text-xs font-semibold uppercase text-slate-500">Note</span>
                <textarea wire:model="applicantNote" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Reason (optional)"></textarea>
            </label>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" x-on:click="erpOverlay.close('my-leave')" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">Close</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Submit</button>
            </div>
        </form>
    </x-setup-form-modal>
</div>
