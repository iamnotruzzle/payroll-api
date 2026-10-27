<section class="hris-api-diagnostics max-w-7xl space-y-5" x-data="{ resource: @entangle('endpoint'), range: @entangle('dateRange') }">
    <header>
        <h1 class="text-xl font-semibold">HRIS API Tests</h1>
        <p class="mt-1 text-sm text-slate-600">Test a documented endpoint and inspect its response. Tests do not import or change payroll records.</p>
    </header>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(320px,0.8fr)_minmax(0,1.2fr)]">
        <form wire:submit="runTest" class="space-y-5 rounded-lg border border-slate-200 bg-white p-5">
            <div>
                <label for="hris-test-endpoint" class="text-sm font-semibold">Endpoint</label>
                <select id="hris-test-endpoint" wire:model.live="endpoint" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($resources as $key => $definition)
                        <option value="{{ $key }}">{{ strtoupper($definition['method']) }} {{ $definition['path'] }}</option>
                    @endforeach
                </select>
                @error('endpoint') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div x-transition.opacity.duration.180ms x-show="['timekeeping','holidays'].includes(resource)" x-cloak class="space-y-3">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" x-model="range" class="rounded border-slate-300">Use an inclusive date range</label>
                <div x-transition.opacity.duration.180ms x-show="!range">
                    <label for="hris-test-period" class="text-xs font-medium">Calendar month</label>
                    <input id="hris-test-period" type="month" wire:model="period" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('period') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div x-transition.opacity.duration.180ms x-show="resource === 'leaves' || (['timekeeping','holidays'].includes(resource) && range)" x-cloak class="grid gap-3 sm:grid-cols-2">
                <div><label for="hris-test-from" class="text-xs font-medium">From</label><input id="hris-test-from" type="date" wire:model="from" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('from') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                <div><label for="hris-test-to" class="text-xs font-medium">To</label><input id="hris-test-to" type="date" wire:model="to" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            </div>

            <div x-transition.opacity.duration.180ms x-show="['basic-pay','employees','timekeeping'].includes(resource)" x-cloak>
                <label for="hris-test-employees" class="text-xs font-medium">Employee IDs <span class="font-normal text-slate-500">(optional)</span></label>
                <select id="hris-test-employees" data-select2-searchable data-model="employeeIds" data-defer-request="true" data-placeholder="Search employees by name or ID" multiple class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->emp_id }}" @selected(in_array((string) $employee->emp_id, $employeeIds, true))>{{ $employee->emp_id }} — {{ $employee->lastname }}, {{ $employee->firstname }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Select one or more local employees. Leading zeros are preserved.{{ $employees->isEmpty() ? ' Sync employee master data to populate this list.' : '' }}</p>
                @error('employeeIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div x-transition.opacity.duration.180ms x-show="['basic-pay','leaves','timekeeping'].includes(resource)" x-cloak>
                <label for="hris-test-departments" class="text-xs font-medium">Department IDs <span class="font-normal text-slate-500">(optional)</span></label>
                <select id="hris-test-departments" data-select2-searchable data-model="departmentIds" data-defer-request="true" data-placeholder="Search departments by name or ID" multiple class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($departments as $department)
                        <option value="{{ $department->external_id }}" @selected(in_array((string) $department->external_id, array_map('strval', $departmentIds), true))>{{ $department->external_id }} — {{ $department->name }}</option>
                    @endforeach
                </select>
                @if ($departments->isEmpty()) <p class="mt-1 text-xs text-slate-500">Sync department master data to populate this list.</p> @endif
                @error('departmentIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div x-transition.opacity.duration.180ms x-show="resource === 'basic-pay'" x-cloak class="grid gap-3 sm:grid-cols-2">
                <div><label for="hris-test-divisions" class="text-xs font-medium">Division IDs (optional)</label><input id="hris-test-divisions" type="text" wire:model="divisionIds" placeholder="1,3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('divisionIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
                <div><label for="hris-test-type" class="text-xs font-medium">Named payroll type (optional)</label><input id="hris-test-type" type="text" wire:model="payrollType" placeholder="regular" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('payrollType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            </div>
            <div x-transition.opacity.duration.180ms x-show="['employees','timekeeping'].includes(resource)" x-cloak>
                <label for="hris-test-groups" class="text-xs font-medium">Plantilla groups (optional)</label>
                <select id="hris-test-groups" data-select2-searchable data-model="groups" data-defer-request="true" data-placeholder="Search plantilla groups" multiple class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($groupOptions as $code => $label)
                        <option value="{{ $code }}" @selected(in_array($code, $groups, true))>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Groups and named IDs are additive on Employees. Timekeeping combines department and group filters.</p>
                @error('groups') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div x-transition.opacity.duration.180ms x-show="resource === 'salary-rates'" x-cloak><label for="hris-test-year" class="text-xs font-medium">Schedule year</label><input id="hris-test-year" type="text" wire:model="year" placeholder="2026 or all" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">@error('year') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</div>
            <label x-transition.opacity.duration.180ms x-show="['divisions','departments'].includes(resource)" x-cloak class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="activeOnly" class="rounded border-slate-300">Active records only</label>
            <label x-transition.opacity.duration.180ms x-show="resource === 'basic-pay'" x-cloak class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="includeInactive" class="rounded border-slate-300">Include inactive employees</label>
            <div class="border-t border-slate-200 pt-4">
                <button type="submit" wire:loading.attr="disabled" class="hris-api-test-action rounded-md px-4 py-2 text-sm font-semibold">
                    <span wire:loading.remove wire:target="runTest">Test endpoint</span><span wire:loading wire:target="runTest">Testing endpoint...</span>
                </button>
                <p class="mt-2 text-xs text-slate-500">Uses the configured server address and token. Credentials stay on the server.</p>
            </div>
        </form>

        <div class="min-w-0 space-y-4" aria-live="polite">
            <div wire:loading wire:target="runTest" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">Waiting for HRIS to respond...</div>
            <div wire:loading.remove wire:target="runTest">
                @if ($result)
                    <section class="space-y-4 rounded-lg border border-slate-200 bg-white p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-base font-semibold">Response</h2><span class="text-sm font-semibold {{ $result['success'] ? 'text-emerald-700' : 'text-red-600' }}">HTTP {{ $result['status'] }} · {{ $result['elapsed_ms'] }} ms</span></div>
                        <p class="break-all text-xs text-slate-500">{{ $result['method'] }} {{ $result['path'] }}</p>
                        <dl class="grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-slate-500">Returned records</dt><dd class="mt-1 font-semibold">{{ $result['count'] ?? 'No data list' }}</dd></div><div><dt class="text-xs text-slate-500">Preview contract check</dt><dd class="mt-1 font-semibold">{{ $result['issues'] ? 'Review issues' : 'Passed' }}</dd></div></dl>
                        @if ($result['issues'])<ul class="list-disc space-y-1 pl-5 text-sm text-amber-700">@foreach ($result['issues'] as $issue)<li>{{ $issue }}</li>@endforeach</ul>@endif
                        <details><summary class="cursor-pointer text-sm font-medium">Request filters</summary><pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all text-xs">{{ json_encode($result['parameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                    </section>
                    <section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
                        <h2 class="text-sm font-semibold">Response preview</h2>
                        <p class="mt-1 text-xs text-slate-500">Preview is limited to 25 items per collection. Credential fields are redacted. {{ $result['limited'] ? 'The full response contains more data than shown.' : '' }}</p>
                        <pre class="mt-4 max-h-[38rem] overflow-auto whitespace-pre-wrap break-all text-xs leading-relaxed">{{ $result['preview'] }}</pre>
                    </section>
                @else
                    <section class="rounded-lg border border-dashed border-slate-300 p-8"><h2 class="text-base font-semibold">Choose an endpoint to begin</h2><p class="mt-2 max-w-lg text-sm text-slate-600">Set its supported filters, then run a test. HTTP status, response time, and the JSON preview will appear here.</p></section>
                @endif
            </div>
        </div>
    </div>
</section>
