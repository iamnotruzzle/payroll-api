<div class="space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Salary Schedules</h1>
            <p class="text-sm text-slate-600">Publish effective-dated tranche matrices for Salary Grades 1–33 and Steps 1–8.</p>
        </div>
        <button
            type="button"
            x-on:click="erpOverlay.open($wire, 'salary-schedule', { selection: '', tranche: 1, effectiveDate: @js(now()->toDateString()) })"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white"
        >New Schedule</button>
    </div>
    @if(session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <section class="rounded-lg border bg-white p-5 shadow-sm">
        <label class="block max-w-xl">
            <span class="mb-1 block text-xs font-semibold uppercase text-slate-600">Existing tranche / effectivity date</span>
            <select wire:model.live="selection" class="w-full rounded-md border bg-white px-3 py-2">
                <option value="">Choose a schedule</option>
                @foreach($schedules as $schedule)
                    @php($date=$schedule->effectivity_date->format('Y-m-d'))
                    <option value="{{ $schedule->tranche_number }}|{{ $date }}">Tranche {{ $schedule->tranche_number }} — Effective {{ $date }}</option>
                @endforeach
            </select>
        </label>
        <p class="mt-3 text-sm text-slate-500">Select an existing schedule to inspect or update it in the drawer.</p>
    </section>
    <x-setup-form-drawer name="salary-schedule" title="New Salary Schedule" edit-title="Edit Salary Schedule" description="All 264 cells are required. Finalized payrolls remain unchanged." size="wide">
        <form wire:submit="publish" class="space-y-4">
            <div class="flex flex-wrap gap-3">
                <label>
                    <span class="block text-xs font-semibold uppercase text-slate-500">Tranche</span>
                    <input wire:model="tranche" type="number" min="1" class="mt-1 w-28 rounded-md border px-3 py-2">
                </label>
                <label>
                    <span class="block text-xs font-semibold uppercase text-slate-500">Effectivity date</span>
                    <input wire:model="effectiveDate" type="date" class="mt-1 rounded-md border px-3 py-2">
                </label>
                <button type="button" wire:click="load" class="self-end rounded-md border px-4 py-2 text-sm font-semibold">Load values</button>
            </div>
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif
            <div class="max-h-[calc(100dvh-250px)] overflow-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="sticky top-0 bg-slate-50">
                        <tr>
                            <th class="border p-2">SG</th>
                            @foreach(range(1,8) as $step)
                                <th class="border p-2">Step {{ $step }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(range(1,33) as $grade)
                            <tr>
                                <th class="border bg-slate-50 p-2">{{ $grade }}</th>
                                @foreach(range(1,8) as $step)
                                    <td class="border p-1"><input wire:model="matrix.{{ $grade }}.{{ $step }}" type="number" step="0.01" min="0" class="w-28 rounded border px-2 py-1 text-right"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-3 border-t pt-4">
                <button type="button" x-on:click="erpOverlay.close('salary-schedule')" class="rounded-md border px-4 py-2">Cancel</button>
                <button wire:confirm="Publish this complete salary schedule? Existing schedules and finalized payrolls remain unchanged." class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white">Publish Schedule</button>
            </div>
        </form>
    </x-setup-form-drawer>
</div>
