<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Leave Card</h2>
            <p class="text-sm text-slate-600">Employee leave history, credit balances, and printable leave card.</p>
        </div>
        <a href="{{ route('leave.credits') }}" class="text-sm font-semibold text-[#696cff] hover:underline">Credits</a>
    </div>

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <label class="block">
            <span class="text-xs font-semibold uppercase text-slate-500">Find employee</span>
            <input wire:model.live.debounce.300ms="search" type="search" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Name or emp_id" @disabled($empId !== '')>
        </label>
        @if ($empId !== '')
            <button wire:click="$set('empId', '')" type="button" class="mt-2 text-sm font-semibold text-[#696cff] hover:underline">Clear selection</button>
        @endif
        @if ($matches->isNotEmpty())
            <ul class="mt-3 divide-y divide-slate-100 rounded-md border border-slate-200">
                @foreach ($matches as $match)
                    <li>
                        <button wire:click="selectEmployee('{{ $match->emp_id }}')" type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50">
                            <span>
                                <span class="font-semibold">{{ $match->full_name }}</span>
                                <span class="text-slate-500"> · {{ $match->emp_id }}</span>
                            </span>
                            <span class="text-xs text-slate-500">{{ $match->department?->department ?? '' }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($employee)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold">{{ $employee->full_name }}</h3>
                    <p class="text-sm text-slate-600">
                        {{ $employee->emp_id }}
                        · {{ $employee->employmentStatus?->status ?? '—' }}
                        · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}
                        · {{ $employee->position?->position ?? $employee->position?->position_name ?? '—' }}
                    </p>
                    <p class="text-xs text-slate-500">Hired {{ optional($employee->date_hired)->format('Y-m-d') ?: '—' }}</p>
                    <p class="mt-2 text-sm text-slate-700">
                        VL <strong>{{ number_format((float) $employee->vacation_leave_credits, 3) }}</strong>
                        <span class="mx-2 text-slate-300">|</span>
                        SL <strong>{{ number_format((float) $employee->sick_leave_credits, 3) }}</strong>
                    </p>
                </div>
                <a href="{{ route('leave.card.print', $employee->emp_id) }}" target="_blank" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Print leave card</a>
            </div>
        </section>

        @if ($computed)
            <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">Computed entitlements (date_hired + status)</div>
                <div class="grid gap-3 px-4 py-3 text-sm sm:grid-cols-2">
                    <div class="rounded-md border border-slate-100 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-500">Vacation leave</p>
                        <p class="mt-1 font-semibold">{{ number_format((float) $computed['vl']['computed'], 3) }} computed</p>
                        <p class="text-xs text-slate-600">earned {{ number_format((float) $computed['vl']['earned'], 3) }} − used {{ number_format((float) $computed['vl']['used'], 3) }} − undertime {{ number_format((float) $computed['vl']['undertime'], 3) }}</p>
                        <p class="text-xs text-slate-500">stored {{ number_format((float) $computed['vl']['stored'], 3) }} (Δ {{ number_format((float) $computed['vl']['delta'], 3) }})</p>
                    </div>
                    <div class="rounded-md border border-slate-100 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-500">Sick leave</p>
                        <p class="mt-1 font-semibold">{{ number_format((float) $computed['sl']['computed'], 3) }} computed</p>
                        <p class="text-xs text-slate-600">earned {{ number_format((float) $computed['sl']['earned'], 3) }} − used {{ number_format((float) $computed['sl']['used'], 3) }}</p>
                        <p class="text-xs text-slate-500">stored {{ number_format((float) $computed['sl']['stored'], 3) }} (Δ {{ number_format((float) $computed['sl']['delta'], 3) }})</p>
                    </div>
                </div>
                @if (! ($computed['accrual_eligible'] ?? false))
                    <p class="border-t border-slate-100 px-4 py-2 text-xs text-amber-700">{{ $computed['accrual_skip_reason'] }}</p>
                @endif
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-white text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Leave type</th>
                            <th class="px-4 py-3 text-right">Max</th>
                            <th class="px-4 py-3 text-right">Used</th>
                            <th class="px-4 py-3 text-right">Remaining</th>
                            <th class="px-4 py-3 text-left">Eligibility</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($computed['entitlements'] as $ent)
                            <tr>
                                <td class="px-4 py-3">
                                    {{ $ent['leave_name'] }}
                                    @if ($ent['period'])
                                        <span class="block text-[11px] text-slate-500">{{ $ent['period'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ ($ent['max_value'] ?? 0) > 0 ? number_format((float) $ent['max_value'], 1) : '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ ($ent['max_value'] ?? 0) > 0 ? number_format((float) $ent['used'], 1) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium">
                                    @if (($ent['max_value'] ?? 0) > 0)
                                        {{ number_format((float) ($ent['remaining'] ?? 0), 1) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs @if (($ent['eligible'] ?? true) === false) text-amber-700 @else text-slate-600 @endif">
                                    @if (($ent['eligible'] ?? null) === null)
                                        n/a
                                    @elseif ($ent['eligible'])
                                        Eligible
                                    @else
                                        Ineligible — {{ implode(' ', $ent['eligibility_notes'] ?? []) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">Leave history</div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-white text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Period</th>
                        <th class="px-4 py-3 text-left">Days</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leaves as $leave)
                        <tr>
                            <td class="px-4 py-3">{{ $leave->leave_type_name }}</td>
                            <td class="px-4 py-3">{{ optional($leave->start_date)->format('Y-m-d') }} → {{ optional($leave->end_date)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $leave->days_wpay, 2) }} / {{ number_format((float) $leave->days_wopay, 2) }}</td>
                            <td class="px-4 py-3">{{ $leave->status_name ?: \App\Support\Hris\LeaveStatuses::nameFor($leave->status !== null ? (int) $leave->status : null) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No leave records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">Recent leave logs</div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-xs uppercase text-slate-500">
                        <th class="px-4 py-3 text-left">When</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">VL / SL</th>
                        <th class="px-4 py-3 text-left">Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ optional($log->created_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $log->action }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $log->vlc, 3) }} / {{ number_format((float) $log->slc, 3) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $log->remarks }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No leave logs.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @else
        <div class="rounded-md border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
            Search and select an employee to open their leave card.
        </div>
    @endif
</div>
