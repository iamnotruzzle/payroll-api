<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">My Training</h2>
            <p class="text-sm text-slate-600">Your TARF / LDI requests and invitations (legacy HRIS tables).</p>
        </div>
        @if ($canRequest)
            <button wire:click="openForm" type="button" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Request training</button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($showForm)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
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
                    <button type="button" wire:click="closeForm" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                </div>
            </form>
        </section>
    @endif

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
