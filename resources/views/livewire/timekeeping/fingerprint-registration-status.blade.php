<section
    class="space-y-4"
    x-data="{
        open: false, busy: false, error: '', message: '', employee: null, slot: 1,
        helperOnline: false, scannerDetected: false, scannerMessage: 'Checking fingerprint scanner...', scannerModels: [],
        helperUrl: @js(config('biometrics.helper_url')),
        init() {
            this.checkScanner();
            setInterval(() => this.checkScanner(), 10000);
        },
        async checkScanner() {
            try {
                const response = await fetch(this.helperUrl + '/health', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                const health = await response.json();
                if (!response.ok) throw new Error(health.message || 'Fingerprint helper is unavailable.');
                this.helperOnline = true;
                this.scannerDetected = health.scanner_detected === true;
                this.scannerModels = health.scanner_models || [];
                this.scannerMessage = health.message || (this.scannerDetected ? 'Fingerprint scanner detected.' : 'No fingerprint scanner detected.');
                return this.scannerDetected;
            } catch (e) {
                this.helperOnline = false; this.scannerDetected = false; this.scannerModels = [];
                this.scannerMessage = 'Fingerprint helper is not running or cannot be reached.';
                return false;
            }
        },
        async begin(employee, slot, replacing) {
            if (replacing && ! confirm('Replace the existing fingerprint in this slot?')) return;
            this.employee = employee; this.slot = slot; this.error = ''; this.message = ''; this.open = true;
            await this.checkScanner();
        },
        async capture() {
            if (! await this.checkScanner()) { this.error = this.scannerMessage; return; }
            this.busy = true; this.error = ''; this.message = 'Place the finger on the U.are.U 4500 when prompted.';
            try {
                const captured = await fetch(this.helperUrl + '/enroll', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({employee_id: this.employee.id, slot: this.slot})
                });
                const payload = await captured.json();
                if (!captured.ok) throw new Error(payload.message || 'Fingerprint capture failed.');
                this.message = 'Capture complete. Saving securely…';
                const saved = await fetch('/timekeeping/fingerprints/' + encodeURIComponent(this.employee.id) + '/' + this.slot, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                    body: JSON.stringify(payload)
                });
                const result = await saved.json();
                if (!saved.ok) throw new Error(result.message || 'Fingerprint could not be saved.');
                this.message = result.message; await this.$wire.$refresh();
            } catch (e) { this.error = e.message || 'Fingerprint enrollment failed.'; this.message = ''; }
            finally { this.busy = false; }
        }
    }"
>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">Fingerprint registration status</h2>
            <p class="text-sm text-slate-600">Review employee fingerprint enrollment status.</p>
        </div>
    </div>

    @if($canManage)
        <div class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm" :class="scannerDetected ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : (helperOnline ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-rose-200 bg-rose-50 text-rose-800')">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="scannerDetected ? 'bg-emerald-500' : (helperOnline ? 'bg-amber-500' : 'bg-rose-500')"></span>
            <span x-text="scannerMessage"></span>
            <span x-show="scannerModels.length" class="font-semibold" x-text="scannerModels.join(', ')"></span>
            <button type="button" x-on:click="checkScanner" class="ml-auto font-semibold underline">Check again</button>
        </div>
    @endif

    @if (! $summary['columns_exist'])
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Fingerprint status is currently unavailable. Please contact the system administrator.
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
                    @if($canManage)<th class="px-4 py-3 text-right">Actions</th>@endif
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
                        @if($canManage)
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" x-on:click="begin({id: @js($employee->emp_id), name: @js($employee->full_name)}, 1, {{ (int) ($employee->has_fingerprint_1 ?? 0) }})" class="rounded-md border px-2.5 py-1.5 text-xs font-semibold">{{ (int) ($employee->has_fingerprint_1 ?? 0) ? 'Replace F1' : 'Enroll F1' }}</button>
                                <button type="button" x-on:click="begin({id: @js($employee->emp_id), name: @js($employee->full_name)}, 2, {{ (int) ($employee->has_fingerprint_2 ?? 0) }})" class="ml-1 rounded-md border px-2.5 py-1.5 text-xs font-semibold">{{ (int) ($employee->has_fingerprint_2 ?? 0) ? 'Replace F2' : 'Enroll F2' }}</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-8 text-center text-slate-500">No employees match the filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-100 px-4 py-3">
            {{ $employees->links() }}
        </div>
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="open" class="fixed inset-0 z-[110] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <button type="button" class="absolute inset-0 bg-slate-950/45" x-on:click="if(!busy) open=false" aria-label="Close"></button>
            <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold">Fingerprint Enrollment</h3><p class="mt-1 text-sm text-slate-500" x-text="employee ? employee.id + ' — ' + employee.name : ''"></p></div><button type="button" x-on:click="open=false" x-bind:disabled="busy" class="rounded-md border px-3 py-2 text-sm">Close</button></div>
                <div class="mt-4 rounded-md border px-3 py-2 text-sm" :class="scannerDetected ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'" x-text="scannerMessage"></div>
                <div class="mt-6 rounded-lg border bg-slate-50 p-5 text-center"><svg class="mx-auto h-14 w-14 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4M14 13.12c0 2.38 0 6.38-1 8.88M2 12a10 10 0 0 1 18-6M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 12 0v2"/></svg><p class="mt-3 font-semibold">Finger <span x-text="slot"></span></p><p class="mt-1 text-sm text-slate-500">Multiple scans will be requested to create a reliable template.</p></div>
                <p x-show="message" class="mt-4 rounded-md bg-blue-50 p-3 text-sm text-blue-800" x-text="message"></p><p x-show="error" class="mt-4 rounded-md bg-rose-50 p-3 text-sm text-rose-700" x-text="error"></p>
                <div class="mt-5 flex justify-end"><button type="button" x-on:click="capture" x-bind:disabled="busy || !scannerDetected" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span x-text="busy ? 'Capturing…' : 'Start Capture'"></span></button></div>
            </div>
        </div>
    </template>

</section>
