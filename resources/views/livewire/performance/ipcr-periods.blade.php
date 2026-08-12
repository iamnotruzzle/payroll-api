<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">IPCR Periods</h2>
            <p class="text-sm text-slate-600">Performance periods on legacy <code>ipcr_*</code> tables. OPCR/MFO calibration polish deferred.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">All apps</a>
            @if ($canManage)
                <button wire:click="openCreate" type="button" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">New period</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <input wire:model.lazy="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search year / period">
    </section>

    @if ($showCreate)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <form wire:submit="createPeriod" class="grid gap-3 sm:grid-cols-4">
                <label class="text-sm">Year<input wire:model="year" type="number" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-sm">Type
                    <select wire:model="periodType" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="semester">Semester</option>
                        <option value="quarter">Quarter</option>
                    </select>
                </label>
                <label class="text-sm">Period
                    <select wire:model="period" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Save</button>
                    <button type="button" wire:click="$set('showCreate', false)" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                </div>
            </form>
        </section>
    @endif

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Employees with targets</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($periods as $period)
                    <tr wire:key="period-{{ $period->id }}">
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $period->label }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $targetCounts[$period->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="openEmployeePicker({{ $period->id }})" type="button" class="rounded-md bg-[#696cff] px-3 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Open employee</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">No IPCR periods yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-100 px-4 py-3">{{ $periods->links() }}</div>
    </section>

    @if ($openPeriodId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" wire:click="$set('openPeriodId', null)">
            <div class="w-full max-w-lg rounded-md bg-white p-5 shadow-xl" wire:click.stop>
                <h3 class="mb-3 text-lg font-semibold">Select employee</h3>
                <input wire:model.lazy="employeeSearch" type="search" class="mb-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee">
                <select wire:model="selectedEmpId" class="mb-4 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" size="8">
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->emp_id }}">{{ $emp->full_name }} ({{ $emp->emp_id }})</option>
                    @endforeach
                </select>
                @error('selectedEmpId') <p class="mb-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('openPeriodId', null)" class="rounded-md border border-slate-300 px-3 py-2 text-sm">Cancel</button>
                    <button type="button" wire:click="goToEmployee" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white">Open sheet</button>
                </div>
            </div>
        </div>
    @endif
</div>
