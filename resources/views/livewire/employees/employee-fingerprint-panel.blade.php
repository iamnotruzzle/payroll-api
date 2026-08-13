<section
    class="space-y-4"
    x-data="{
        open: false, busy: false, error: '', message: '', slot: 1,
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
        async begin(slot, replacing) {
            if (replacing && ! confirm('Replace the existing fingerprint in this slot?')) return;
            this.slot = slot; this.error = ''; this.message = ''; this.open = true;
            await this.checkScanner();
        },
        async capture() {
            if (! await this.checkScanner()) { this.error = this.scannerMessage; return; }
            this.busy = true; this.error = ''; this.message = 'Place the finger on the U.are.U 4500 when prompted.';
            try {
                const captured = await fetch(this.helperUrl + '/enroll', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({employee_id: @js($employee->emp_id), slot: this.slot})});
                const payload = await captured.json();
                if (!captured.ok) throw new Error(payload.message || 'Fingerprint capture failed.');
                this.message = 'Capture complete. Saving securely…';
                const saved = await fetch('/timekeeping/fingerprints/' + encodeURIComponent(@js($employee->emp_id)) + '/' + this.slot, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify(payload)});
                const result = await saved.json();
                if (!saved.ok) throw new Error(result.message || 'Fingerprint could not be saved.');
                this.message = result.message; await this.$wire.$refresh();
            } catch (e) { this.error = e.message || 'Fingerprint enrollment failed.'; this.message = ''; }
            finally { this.busy = false; }
        }
    }"
>
    <div class="flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-slate-900">Biometric registration</h3><p class="mt-1 text-sm text-slate-500">Manage the employee’s two fingerprint enrollment slots.</p></div><a href="{{ route('payroll.fingerprint-registration') }}" class="text-sm font-semibold text-blue-600 hover:underline">Fingerprint directory</a></div>
    <div class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm" :class="scannerDetected ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : (helperOnline ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-rose-200 bg-rose-50 text-rose-800')">
        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="scannerDetected ? 'bg-emerald-500' : (helperOnline ? 'bg-amber-500' : 'bg-rose-500')"></span>
        <span x-text="scannerMessage"></span>
        <span x-show="scannerModels.length" class="font-semibold" x-text="scannerModels.join(', ')"></span>
        <button type="button" x-on:click="checkScanner" class="ml-auto font-semibold underline">Check again</button>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach([1 => $finger1, 2 => $finger2] as $slot => $registered)
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Finger {{ $slot }}</p><p class="mt-2 text-lg font-semibold {{ $registered ? 'text-emerald-700' : 'text-slate-700' }}">{{ $registered ? 'Registered' : 'Not registered' }}</p></div><svg class="h-10 w-10 {{ $registered ? 'text-emerald-600' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4M14 13.12c0 2.38 0 6.38-1 8.88M2 12a10 10 0 0 1 18-6M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 12 0v2"/></svg></div>
                @if($canManage)<button type="button" x-on:click="begin({{ $slot }}, {{ $registered ? 'true' : 'false' }})" class="mt-4 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">{{ $registered ? 'Replace fingerprint' : 'Enroll fingerprint' }}</button>@endif
            </article>
        @endforeach
    </div>
    @unless($canManage)<p class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">Fingerprint enrollment is available to authorized timekeeping managers for active employees.</p>@endunless
    <template x-teleport="body"><div x-cloak x-show="open" class="fixed inset-0 z-[110] flex items-center justify-center p-4" role="dialog" aria-modal="true"><button type="button" class="absolute inset-0 bg-slate-950/45" x-on:click="if(!busy) open=false" aria-label="Close"></button><div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl"><div class="flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold">Fingerprint Enrollment</h3><p class="mt-1 text-sm text-slate-500">{{ $employee->emp_id }} — {{ $employee->full_name }}</p></div><button type="button" x-on:click="open=false" x-bind:disabled="busy" class="rounded-md border px-3 py-2 text-sm">Close</button></div><div class="mt-4 rounded-md border px-3 py-2 text-sm" :class="scannerDetected ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'" x-text="scannerMessage"></div><div class="mt-6 rounded-lg border bg-slate-50 p-5 text-center"><p class="font-semibold">Finger <span x-text="slot"></span></p><p class="mt-1 text-sm text-slate-500">Multiple scans will be requested to create a reliable template.</p></div><p x-show="message" class="mt-4 rounded-md bg-blue-50 p-3 text-sm text-blue-800" x-text="message"></p><p x-show="error" class="mt-4 rounded-md bg-rose-50 p-3 text-sm text-rose-700" x-text="error"></p><div class="mt-5 flex justify-end"><button type="button" x-on:click="capture" x-bind:disabled="busy || !scannerDetected" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" x-text="busy ? 'Capturing…' : 'Start Capture'"></button></div></div></div></template>
</section>
