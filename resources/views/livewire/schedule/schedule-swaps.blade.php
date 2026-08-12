<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Shift Swaps</h2>
        <p class="text-sm text-slate-600">
            Request → accept → approve workflow for {{ $department?->department ?? 'your department' }}.
            Locked monthly schedules cannot be swapped (approve → lock → DTR remains intact).
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($canManage)
        <form wire:submit="createSwap" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-2">
            <div>
                <label class="text-sm font-medium">Requester assignment</label>
                <select wire:model="requester_assignment_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select…</option>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->id }}">
                            {{ $assignment->schedule_date->toDateString() }} · {{ $assignment->employee_id }} · {{ $assignment->shiftCode?->code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Responder assignment</label>
                <select wire:model="responder_assignment_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select…</option>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->id }}">
                            {{ $assignment->schedule_date->toDateString() }} · {{ $assignment->employee_id }} · {{ $assignment->shiftCode?->code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="text-sm font-medium">Notes</label>
                <input wire:model="notes" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Create swap request</button>
        </form>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center gap-2">
            <h3 class="font-semibold">Queue</h3>
            <select wire:model.live="statusFilter" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                <option value="open">Open</option>
                <option value="pending">Pending</option>
                <option value="accepted">Accepted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="all">All</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Requester</th>
                        <th class="px-3 py-2">Responder</th>
                        <th class="px-3 py-2">Shifts</th>
                        <th class="px-3 py-2">Status</th>
                        @if ($canManage)<th class="px-3 py-2"></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($swaps as $swap)
                        @php
                            $reqName = $names->get($swap->requester_emp_id);
                            $resName = $names->get($swap->responder_emp_id);
                        @endphp
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $swap->schedule_date->toDateString() }}</td>
                            <td class="px-3 py-2">{{ $reqName ? $reqName->lastname.', '.$reqName->firstname : $swap->requester_emp_id }}</td>
                            <td class="px-3 py-2">{{ $resName ? $resName->lastname.', '.$resName->firstname : $swap->responder_emp_id }}</td>
                            <td class="px-3 py-2">
                                {{ $swap->requesterAssignment?->shiftCode?->code ?: '?' }}
                                ↔
                                {{ $swap->responderAssignment?->shiftCode?->code ?: '?' }}
                            </td>
                            <td class="px-3 py-2"><span class="rounded bg-slate-100 px-2 py-0.5 text-xs uppercase">{{ $swap->status }}</span></td>
                            @if ($canManage)
                                <td class="px-3 py-2 text-right space-x-2">
                                    @if ($swap->isOpen())
                                        <button wire:click="approve({{ $swap->id }})" class="text-xs text-emerald-700 hover:underline">Approve</button>
                                        <button wire:click="reject({{ $swap->id }})" class="text-xs text-red-600 hover:underline">Reject</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No swaps.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
