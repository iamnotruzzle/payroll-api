<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Department Schedule Profile</h2>
        <p class="text-sm text-slate-600">
            Turn optional Schedule capabilities on or off for
            {{ $department?->department ?? 'your department' }}.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="max-w-3xl space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <div>
                <p class="font-semibold text-slate-900">Mode: {{ $modeLabel }}</p>
                <p class="mt-1">
                    Core schedule flow (draft → review → approve → <strong>lock → DTR sync</strong>) always stays available.
                    Flags below only gate optional UI.
                </p>
            </div>
            <span class="rounded-md px-3 py-1 text-xs font-semibold {{ $isCno ? 'bg-cyan-100 text-cyan-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $isCno ? 'CNO / Nursing' : 'Department + areas' }}
            </span>
        </div>

        @if ($isCno)
            <div class="rounded-md border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                Departments under HRIS division_id={{ $cnoDivisionId }} (Nursing Service) default to full nursing flags
                ({{ strtolower($unitNoun) }}, floaters, on-call, swaps, census) for NDOS parity.
                Profiles auto-provision on first visit or via
                <code class="text-xs">php artisan schedule:provision-department-profiles --apply</code>.
            </div>
        @else
            <div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                Default is a simple department roster (optional flags off).
                To support <strong>multiple areas under this office/department</strong>, turn on
                <strong>{{ strtolower($unitNoun) }}</strong> only — leave floaters / on-call / census / swaps off unless needed.
            </div>
        @endif

        <div class="space-y-3">
            <label class="flex items-start gap-3 rounded-md border border-slate-200 px-4 py-3">
                <input wire:model="uses_units" type="checkbox" class="mt-1" @disabled(! $canManage)>
                <span>
                    <span class="block text-sm font-semibold text-slate-900">
                        {{ $isCno ? 'Units (wards / clinics / areas)' : 'Areas (multi-area under office/department)' }}
                    </span>
                    <span class="mt-1 block text-sm text-slate-600">
                        @if ($isCno)
                            Enable schedule units under this department and handled-unit scheduler scope (NDOS locations).
                        @else
                            Enable Schedule → Areas CRUD for multiple sections/areas without requiring nursing extras.
                        @endif
                    </span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-slate-200 px-4 py-3">
                <input wire:model="uses_floaters" type="checkbox" class="mt-1" @disabled(! $canManage)>
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Floaters</span>
                    <span class="mt-1 block text-sm text-slate-600">Enable floater pool management and temporary floater flags on assignments.</span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-slate-200 px-4 py-3">
                <input wire:model="uses_on_call" type="checkbox" class="mt-1" @disabled(! $canManage)>
                <span>
                    <span class="block text-sm font-semibold text-slate-900">On-call pools</span>
                    <span class="mt-1 block text-sm text-slate-600">Enable primary and second on-call pool management.</span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-slate-200 px-4 py-3">
                <input wire:model="uses_swaps" type="checkbox" class="mt-1" @disabled(! $canManage)>
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Shift swaps</span>
                    <span class="mt-1 block text-sm text-slate-600">Enable shift-swap request → approve workflow (self-service + scheduler).</span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-slate-200 px-4 py-3">
                <input wire:model="uses_census" type="checkbox" class="mt-1" @disabled(! $canManage)>
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Duty census</span>
                    <span class="mt-1 block text-sm text-slate-600">Enable duty census (headcount by day × shift).</span>
                </span>
            </label>
        </div>

        @if ($canManage)
            <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save profile</button>
        @else
            <p class="text-sm text-slate-500">You can view this profile. Saving requires <code class="text-xs">schedule.manage</code>.</p>
        @endif
    </form>
</section>
