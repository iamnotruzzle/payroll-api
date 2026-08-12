<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">My Shift Swaps</h2>
        <p class="text-sm text-slate-600">Request a same-day swap with a colleague. A scheduler must approve before shifts change. Locked rosters cannot be swapped.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form wire:submit="requestSwap" class="grid gap-3 rounded-md border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-2">
        <div>
            <label class="text-sm font-medium">My assignment</label>
            <select wire:model.live="my_assignment_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Select…</option>
                @foreach ($myAssignments as $assignment)
                    <option value="{{ $assignment->id }}">
                        {{ $assignment->schedule_date->toDateString() }} · {{ $assignment->shiftCode?->code }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium">Partner assignment (same day)</label>
            <select wire:model="partner_assignment_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Select…</option>
                @foreach ($partnerAssignments as $assignment)
                    <option value="{{ $assignment->id }}">
                        {{ $assignment->employee_id }} · {{ $assignment->shiftCode?->code }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label class="text-sm font-medium">Notes</label>
            <input wire:model="notes" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Submit request</button>
    </form>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-3 text-left">Date</th>
                    <th class="px-3 py-3 text-left">With</th>
                    <th class="px-3 py-3 text-left">Status</th>
                    <th class="px-3 py-3 text-left"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($swaps as $swap)
                    <tr>
                        <td class="px-3 py-2">{{ $swap->schedule_date->toDateString() }}</td>
                        <td class="px-3 py-2">
                            {{ $swap->requester_emp_id === $empId ? $swap->responder_emp_id : $swap->requester_emp_id }}
                            · {{ $swap->requesterAssignment?->shiftCode?->code }} ↔ {{ $swap->responderAssignment?->shiftCode?->code }}
                        </td>
                        <td class="px-3 py-2"><span class="rounded bg-slate-100 px-2 py-0.5 text-xs uppercase">{{ $swap->status }}</span></td>
                        <td class="px-3 py-2 text-right space-x-2">
                            @if ($swap->status === 'pending' && $swap->responder_emp_id === $empId)
                                <button wire:click="accept({{ $swap->id }})" class="text-xs text-emerald-700 hover:underline">Accept</button>
                            @endif
                            @if ($swap->isOpen() && $swap->requester_emp_id === $empId)
                                <button wire:click="cancel({{ $swap->id }})" class="text-xs text-red-600 hover:underline">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No swap requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</section>
