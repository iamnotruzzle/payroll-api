<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My Training</h2>
            <p class="text-sm text-slate-600">Your TARF / LDI requests and invitations.</p>
        </div>
        @if ($canRequest)
            <button type="button" x-on:click="erpOverlay.open($wire, 'my-training', { trainingName: '', trainingVenue: '', sponsor: '', sponsorType: 1, startDate: @js(now()->toDateString()), endDate: @js(now()->toDateString()), hrs: '8', type: null, mode: 'f2f' })" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Request training</button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($pendingInvites->isNotEmpty())
        <section class="rounded-md border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-amber-900">Pending invitations</h3>
            <ul class="mt-3 space-y-3">
                @foreach ($pendingInvites as $invite)
                    <li class="flex flex-col gap-2 rounded-md border border-amber-100 bg-white px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-800">{{ $invite->trainingDetail?->training_name ?: $invite->tarf_no }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $invite->tarf_no }}
                                · {{ optional($invite->trainingDetail?->start_date)->format('Y-m-d') }} → {{ optional($invite->trainingDetail?->end_date)->format('Y-m-d') }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="respondInvite({{ $invite->id }}, 1)" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Accept</button>
                            <button wire:click="respondInvite({{ $invite->id }}, 2)" wire:confirm="Decline this training invitation?" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700">Decline</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-setup-form-drawer name="my-training" title="Request training" size="lg">
        <form wire:submit="submit" class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">Training name<input wire:model="trainingName" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="block text-sm">Venue<input wire:model="trainingVenue" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="block text-sm">Mode
                <select wire:model="mode" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="f2f">Face to face</option>
                    <option value="online">Online</option>
                    <option value="hybrid">Hybrid</option>
                </select>
            </label>
            <label class="block text-sm">Sponsor<input wire:model="sponsor" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="block text-sm">Sponsor type
                <select wire:model="sponsorType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="1">External</option>
                    <option value="2">Internal</option>
                </select>
            </label>
            <label class="block text-sm">Start<input wire:model="startDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="block text-sm">End<input wire:model="endDate" type="date" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="block text-sm">Hours<input wire:model="hrs" type="number" step="0.5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="block text-sm">Type
                <select wire:model="type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}">{{ $t->type }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Submit</button>
                <button type="button" x-on:click="erpOverlay.close('my-training')" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
            </div>
        </form>
    </x-setup-form-drawer>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">TARF</th>
                    <th class="px-4 py-3 text-left">Training</th>
                    <th class="px-4 py-3 text-left">Dates</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tarfs as $tarf)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $tarf->tarf_no }}</td>
                        <td class="px-4 py-3">{{ $tarf->training_name }}</td>
                        <td class="px-4 py-3">{{ optional($tarf->start_date)->format('Y-m-d') }} → {{ optional($tarf->end_date)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $statusLabels[(int) $tarf->status] ?? $tarf->status }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('training.show', $tarf->tarf_no) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Open</a>
                            <a href="{{ route('training.print', $tarf->tarf_no) }}" target="_blank" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Print</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No training requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
