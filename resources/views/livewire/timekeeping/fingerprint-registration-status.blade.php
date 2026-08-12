<section class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Fingerprint registration status</h2>
            <p class="text-sm text-slate-600">
                Read-only view of legacy <code class="text-xs">tbl_employee.fingerprint_1/2</code> enrollment.
                Template blobs are never shown. Enrollment still happens on biometric devices / legacy tools.
            </p>
        </div>
    </div>

    @if (! $summary['columns_exist'])
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Fingerprint columns were not found on <code>tbl_employee</code>. Status checks are unavailable until the legacy schema is present.
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Active employees</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($summary['total_active']) }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Both fingers</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format($summary['registered']) }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-amber-700">Partial</p>
                <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format($summary['partial']) }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-rose-700">Missing</p>
                <p class="mt-1 text-2xl font-semibold text-rose-900">{{ number_format($summary['missing']) }}</p>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <label class="text-sm font-medium">Search</label>
                <input type="search" wire:model.lazy="search" placeholder="emp_id or name"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm font-medium">Department</label>
                <select wire:model.live="departmentId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->department_id }}">{{ $department->department }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Status</label>
                <select wire:model.live="statusFilter" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @disabled(! $summary['columns_exist'])>
                    <option value="all">All</option>
                    <option value="registered">Both registered</option>
                    <option value="partial">Partial</option>
                    <option value="missing">Missing</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Emp ID</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Finger 1</th>
                    <th class="px-4 py-3">Finger 2</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $employee->emp_id }}</td>
                        <td class="px-4 py-3">{{ $employee->full_name }}</td>
                        <td class="px-4 py-3">{{ $employee->department?->department ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if (! $summary['columns_exist'])
                                <span class="text-slate-400">n/a</span>
                            @elseif ((int) ($employee->has_fingerprint_1 ?? 0) === 1)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Registered</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Missing</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (! $summary['columns_exist'])
                                <span class="text-slate-400">n/a</span>
                            @elseif ((int) ($employee->has_fingerprint_2 ?? 0) === 1)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Registered</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Missing</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No employees match the filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-100 px-4 py-3">
            {{ $employees->links() }}
        </div>
    </div>

    <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        <p class="font-medium">Gap vs DTR Encoding</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            <li>DTR Encoding handles labels, schedule encodings, tardiness/undertime, and corrections — not raw punch slot editing.</li>
            <li>Legacy admin could add/edit/delete raw <code>tbl_employee_dtr</code> rows; use DTR Correction Requests here, or biometric/client sync for device punches.</li>
            <li>Fingerprint enrollment UI is not ported; this page is status-only.</li>
        </ul>
    </div>
</section>
