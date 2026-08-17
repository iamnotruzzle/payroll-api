<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Leave Requests</h2>
            <p class="text-sm text-slate-600">File, edit, cancel, and print leave applications.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">All apps</a>
            @if ($canRequest)
                <button
                    type="button"
                    x-on:click="erpOverlay.open($wire, 'leave-request', { editingId: null, empId: '', leaveType: null, filingDate: @js(now()->toDateString()), dateMode: 'weekdays', startDate: @js(now()->toDateString()), endDate: @js(now()->toDateString()), selectedDatesCsv: '', daysWpay: '1', daysWopay: '0', autoSplitCredits: true, leaveSpent: '', commutation: '', applicantNote: '', employeeSearch: '' })"
                    class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]"
                >
                    Apply Leave
                </button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search leave</span>
                <input wire:model.lazy="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee, ID, or remarks">
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Status
                <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="all">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="disapproved">Disapproved</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Rows
                <select wire:model.live="perPage" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="20">20</option>
                    <option value="40">40</option>
                    <option value="60">60</option>
                </select>
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Days</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($leaves as $leave)
                    @php
                        $statusKey = \App\Support\Hris\LeaveStatuses::keyFor($leave->status !== null ? (int) $leave->status : null);
                        $statusName = \App\Support\Hris\LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null);
                        $pending = (bool) ($pendingById[$leave->leave_id] ?? false);
                        $leaveDates = \App\Support\Hris\LeaveDates::for($leave);
                    @endphp
                    <tr wire:key="leave-{{ $leave->leave_id }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $leave->employee?->full_name ?: 'Unknown' }}</p>
                            <p class="text-xs text-slate-500">{{ $leave->emp_id }} · filed {{ optional($leave->filing_date)->format('Y-m-d') ?: '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $leave->leave_type_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ optional($leave->start_date)->format('Y-m-d') ?: '—' }}
                            <span class="text-slate-400">→</span>
                            {{ optional($leave->end_date)->format('Y-m-d') ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <span title="With pay">{{ number_format((float) $leave->days_wpay, 2) }} WP</span>
                            <span class="mx-1 text-slate-300">/</span>
                            <span title="Without pay">{{ number_format((float) $leave->days_wopay, 2) }} WOP</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($statusKey === 'approved')
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $statusName }}</span>
                            @elseif ($statusKey === 'pending')
                                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">{{ $statusName }}</span>
                            @elseif ($statusKey === 'disapproved')
                                <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ $statusName }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ $statusName }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('leave.requests.print', $leave->leave_id) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
                            @if ($pending && $canRequest)
                                <button
                                    type="button"
                                    x-on:click="erpOverlay.open($wire, 'leave-request', { editingId: {{ $leave->leave_id }}, empId: @js((string) $leave->emp_id), leaveType: {{ (int) $leave->leave_type }}, filingDate: @js(optional($leave->filing_date)?->toDateString() ?: now()->toDateString()), startDate: @js($leaveDates[0] ?? (optional($leave->start_date)?->toDateString() ?: '')), endDate: @js($leaveDates !== [] ? $leaveDates[array_key_last($leaveDates)] : (optional($leave->end_date)?->toDateString() ?: '')), selectedDatesCsv: @js(\App\Support\Hris\LeaveDates::toCsv($leaveDates)), dateMode: 'pick', daysWpay: @js((string) ($leave->days_wpay ?? '')), daysWopay: @js((string) ($leave->days_wopay ?? '0')), autoSplitCredits: false, leaveSpent: @js((string) ($leave->leave_spent ?? '')), commutation: @js((string) ($leave->commutation ?? '')), applicantNote: @js((string) ($leave->applicant_note ?? '')), employeeSearch: @js((string) $leave->emp_id) }, true)"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50"
                                >Edit</button>
                                <button wire:click="cancelRequest({{ $leave->leave_id }})" wire:confirm="Cancel this leave request?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No leave requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>{{ $leaves->links() }}</div>

    <x-setup-form-drawer name="leave-request" title="Apply Leave" edit-title="Edit Leave" size="lg">
        <form wire:submit="save" class="space-y-4">
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Employee</span>
                <input wire:model.lazy="employeeSearch" type="search" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Filter employees">
                <select wire:model="empId" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" x-bind:disabled="editing">
                    <option value="">Select employee</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->emp_id }}">{{ $employee->full_name }} ({{ $employee->emp_id }})</option>
                    @endforeach
                </select>
                @error('empId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Leave type</span>
                <select wire:model="leaveType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select type</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->leave_type_id }}">{{ $type->leave_name }}</option>
                    @endforeach
                </select>
                @error('leaveType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Date mode</span>
                <select wire:model.live="dateMode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="weekdays">Fill range as weekdays (Mon–Fri)</option>
                    <option value="calendar">Fill range as calendar days</option>
                    <option value="pick">Pick specific dates</option>
                </select>
                @error('dateMode') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Filing date</span>
                    <input wire:model="filingDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('filingDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Start</span>
                    <input wire:model.live="startDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('startDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">End</span>
                    <input wire:model.live="endDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('endDate') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="block" x-show="$wire.dateMode === 'pick'" x-cloak>
                <span class="text-xs font-semibold uppercase text-slate-500">Selected dates (CSV)</span>
                <textarea wire:model.lazy="selectedDatesCsv" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm" placeholder="2026-08-03,2026-08-05,2026-08-07"></textarea>
                <span class="mt-1 block text-xs text-slate-500">Comma-separated YYYY-MM-DD. Start/end fill can seed the list; edit to drop weekends or gaps.</span>
                @error('selectedDatesCsv') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <p class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600" x-show="$wire.dateMode !== 'pick'" x-cloak>
                Selected days: <span class="font-semibold text-slate-800">{{ $previewDayCount }}</span>
                @if ($selectedDatesCsv)
                    <span class="mt-1 block font-mono text-[11px] text-slate-500">{{ $selectedDatesCsv }}</span>
                @endif
            </p>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block sm:col-span-3">
                    <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                        <input wire:model.live="autoSplitCredits" type="checkbox" class="rounded border-slate-300">
                        Auto-split with-pay / without-pay from credits
                    </span>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Days with pay</span>
                    <input wire:model="daysWpay" type="number" step="0.001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled($autoSplitCredits)>
                    @error('daysWpay') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Days w/o pay</span>
                    <input wire:model="daysWopay" type="number" step="0.001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled($autoSplitCredits)>
                    @error('daysWopay') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-slate-500">Leave location (spent)</span>
                    <input wire:model="leaveSpent" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. Philippines, Outpatient, CTO">
                </label>
            </div>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Commutation</span>
                <input wire:model="commutation" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Optional">
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Applicant note</span>
                <textarea wire:model="applicantNote" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Reason / details (not stored in remarks)"></textarea>
                @error('applicantNote') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="w-full rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]" x-text="editing ? 'Save changes' : 'Submit request'">
                Submit request
            </button>
        </form>
    </x-setup-form-drawer>
</div>
