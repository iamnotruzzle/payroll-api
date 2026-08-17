<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Leave Credits</h2>
            <p class="text-sm text-slate-600">Stored VL/SL plus hire-date / employment-status entitlements for all displayable leave types. Undertime remains via MRA / payroll adjustments.</p>
        </div>
        <a href="{{ route('home') }}" class="text-sm font-semibold text-[#696cff] hover:underline">All apps</a>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search</span>
                <input wire:model.lazy="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee or ID">
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                <input wire:model.live="showComputed" type="checkbox" class="rounded border-slate-300">
                Show computed
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Rows
                <select wire:model.live="perPage" class="rounded-md border border-slate-300 px-2 py-2 text-sm normal-case">
                    <option value="20">20</option>
                    <option value="40">40</option>
                </select>
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-right">VL stored</th>
                    @if ($showComputed)
                        <th class="px-4 py-3 text-right">VL computed</th>
                    @endif
                    <th class="px-4 py-3 text-right">SL stored</th>
                    @if ($showComputed)
                        <th class="px-4 py-3 text-right">SL computed</th>
                        <th class="px-4 py-3 text-left">Other remaining</th>
                    @endif
                    <th class="px-4 py-3 text-left">Recent undertime adj.</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    @php
                        $adjustments = $undertimeByEmp->get($employee->emp_id, collect())->take(2);
                        $computed = $computedByEmp->get($employee->emp_id);
                        $otherBits = collect($computed['entitlements'] ?? [])
                            ->filter(fn ($e) => ($e['max_value'] ?? 0) > 0)
                            ->take(3);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $employee->full_name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $employee->emp_id }}
                                · {{ $employee->employmentStatus?->status ?? '—' }}
                                · {{ $employee->department?->department ?? $employee->department?->department_name ?? '—' }}
                                @if ($employee->date_hired)
                                    · hired {{ optional($employee->date_hired)->format('Y-m-d') }}
                                @endif
                            </p>
                            @if ($showComputed && $computed && ! ($computed['accrual_eligible'] ?? false))
                                <p class="mt-1 text-xs text-amber-700">{{ $computed['accrual_skip_reason'] ?? 'Not eligible for VL/SL accrual' }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $employee->vacation_leave_credits, 3) }}</td>
                        @if ($showComputed)
                            <td class="px-4 py-3 text-right">
                                @if ($computed)
                                    <span class="font-medium">{{ number_format((float) $computed['vl']['computed'], 3) }}</span>
                                    <span class="block text-[11px] text-slate-500">earn {{ number_format((float) $computed['vl']['earned'], 3) }} − used {{ number_format((float) $computed['vl']['used'], 3) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="px-4 py-3 text-right font-medium">{{ number_format((float) $employee->sick_leave_credits, 3) }}</td>
                        @if ($showComputed)
                            <td class="px-4 py-3 text-right">
                                @if ($computed)
                                    <span class="font-medium">{{ number_format((float) $computed['sl']['computed'], 3) }}</span>
                                    <span class="block text-[11px] text-slate-500">earn {{ number_format((float) $computed['sl']['earned'], 3) }} − used {{ number_format((float) $computed['sl']['used'], 3) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @forelse ($otherBits as $bit)
                                    <p>
                                        <span @class(['text-amber-700' => ! ($bit['eligible'] ?? true)])>
                                            {{ $bit['leave_name'] }}: {{ number_format((float) ($bit['remaining'] ?? 0), 1) }}
                                            @if (! ($bit['eligible'] ?? true)) (ineligible) @endif
                                        </span>
                                    </p>
                                @empty
                                    <span class="text-slate-400">—</span>
                                @endforelse
                            </td>
                        @endif
                        <td class="px-4 py-3 text-xs text-slate-600">
                            @forelse ($adjustments as $adjustment)
                                <p>{{ number_format((float) $adjustment->adjustment_days, 3) }}d · {{ $adjustment->undertime_tardy_minutes }} min · {{ optional($adjustment->created_at)->format('Y-m-d') }}</p>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('leave.card', ['empId' => $employee->emp_id]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Card</a>
                            @if ($canEdit)
                                <button wire:click="edit('{{ $employee->emp_id }}')" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showComputed ? 8 : 5 }}" class="px-4 py-8 text-center text-slate-500">No employees found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>{{ $employees->links() }}</div>

    <x-setup-form-drawer name="leave-credits" title="Edit leave credits" size="lg">
        <form wire:submit="save" class="space-y-4">
            <p class="text-xs text-slate-500">{{ $empId }}</p>
            @if ($computedDetail)
                <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
                    <p class="font-semibold uppercase tracking-wide text-slate-500">Hire-date recompute</p>
                    <p class="mt-1">{{ $computedDetail['status_label'] }} · hired {{ $computedDetail['date_hired'] ?? '—' }} · {{ $computedDetail['months_of_service'] }} mos · rate {{ number_format((float) $computedDetail['monthly_rate'], 3) }}</p>
                    <p class="mt-2">VL calc {{ number_format((float) $computedDetail['vl']['computed'], 3) }} (earn {{ number_format((float) $computedDetail['vl']['earned'], 3) }} − used {{ number_format((float) $computedDetail['vl']['used'], 3) }} − UT {{ number_format((float) $computedDetail['vl']['undertime'], 3) }})</p>
                    <p>SL calc {{ number_format((float) $computedDetail['sl']['computed'], 3) }} (earn {{ number_format((float) $computedDetail['sl']['earned'], 3) }} − used {{ number_format((float) $computedDetail['sl']['used'], 3) }})</p>
                    @if (! ($computedDetail['accrual_eligible'] ?? false))
                        <p class="mt-2 text-amber-700">{{ $computedDetail['accrual_skip_reason'] }}</p>
                    @else
                        <button wire:click="useComputedBalances" type="button" class="mt-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Fill form from computed</button>
                    @endif
                    <div class="mt-3 space-y-1 border-t border-slate-200 pt-2">
                        <p class="font-semibold uppercase tracking-wide text-slate-500">Other leave types</p>
                        @foreach ($computedDetail['entitlements'] ?? [] as $ent)
                            @if (($ent['max_value'] ?? 0) > 0)
                                <p @class(['text-amber-700' => ! ($ent['eligible'] ?? true)])>
                                    {{ $ent['leave_name'] }}: {{ number_format((float) ($ent['remaining'] ?? 0), 1) }} / {{ number_format((float) $ent['max_value'], 1) }}
                                    ({{ $ent['period'] }}, used {{ number_format((float) $ent['used'], 1) }})
                                    @if (! ($ent['eligible'] ?? true))
                                        — {{ implode(' ', $ent['eligibility_notes'] ?? ['ineligible']) }}
                                    @endif
                                </p>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Vacation leave</span>
                <input wire:model="vacationLeaveCredits" type="number" step="0.001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('vacationLeaveCredits') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Sick leave</span>
                <input wire:model="sickLeaveCredits" type="number" step="0.001" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('sickLeaveCredits') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Remarks</span>
                <textarea wire:model="remarks" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
            </label>

            <div class="rounded-md border border-slate-200">
                <div class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase text-slate-500">
                    Credit ledger (latest {{ $ledgerRows->count() }})
                </div>
                <div class="max-h-56 overflow-y-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-white text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Date</th>
                                <th class="px-3 py-2 font-semibold">Bucket</th>
                                <th class="px-3 py-2 font-semibold text-right">Delta</th>
                                <th class="px-3 py-2 font-semibold text-right">Balance</th>
                                <th class="px-3 py-2 font-semibold">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($ledgerRows as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ optional($row->effective_date)->format('Y-m-d') ?: '—' }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ $row->bucket }}</td>
                                    <td class="px-3 py-2 text-right @if ($row->delta < 0) text-rose-700 @elseif ($row->delta > 0) text-emerald-700 @endif">
                                        {{ $row->delta > 0 ? '+' : '' }}{{ number_format((float) $row->delta, 3) }}
                                    </td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $row->balance_after, 3) }}</td>
                                    <td class="px-3 py-2" title="{{ $row->remarks }}">{{ $row->source_label }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-slate-500">No ledger rows yet. Run <code class="text-[10px]">hris:seed-leave-credit-ledger</code> or save a credit change.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <button type="submit" class="w-full rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Save credits</button>
        </form>
    </x-setup-form-drawer>
</div>
