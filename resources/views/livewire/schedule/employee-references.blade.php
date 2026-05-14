<section class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">External Employee Reference Sync</h2>
            <p class="text-sm text-slate-600">Keep HRIS, timekeeping, and payroll employee identifiers aligned.</p>
        </div>
        <button wire:click="sync" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
            Sync Active Employees
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-semibold">References</h3>
            <input wire:model.live.debounce.250ms="search" placeholder="Search employee" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm sm:w-72">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Employee</th>
                        <th class="px-3 py-2">HRIS ID</th>
                        <th class="px-3 py-2">Timekeeping ID</th>
                        <th class="px-3 py-2">Payroll ID</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($references as $reference)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $reference->display_name ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $reference->hris_employee_id }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $reference->timekeeping_employee_id ?? '-' }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ $reference->payroll_employee_id }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded px-2 py-1 text-xs {{ $reference->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $reference->is_active ? 'active' : 'inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No employee references synced yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
