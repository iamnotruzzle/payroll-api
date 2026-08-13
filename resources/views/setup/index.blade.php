<x-layouts.app title="Setup">
    <div class="space-y-6">
        <header class="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white px-5 py-5 shadow-sm sm:flex-row sm:items-end sm:px-6">
            <div>
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-blue-600">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                    System configuration
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">Setup workspace</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Manage the shared references and rules used across HRIS, scheduling, timekeeping, and payroll.</p>
            </div>
            <div class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm">
                {{ collect($sections)->sum(fn ($section) => count($section['items'] ?? [])) }} available tools
            </div>
        </header>

        <section class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20M12 8v4M12 16h.01"/></svg>
            <div><p class="text-sm font-semibold">Historical payrolls are protected</p><p class="mt-0.5 text-sm leading-5 text-blue-800">Reference and salary changes apply prospectively. Finalized payrolls retain their saved employee details and calculation snapshots.</p></div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach($sections as $section)
                <section class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-blue-200 hover:shadow-md">
                    <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">{{ $section['label'] }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ count($section['items']) }} configuration {{ count($section['items']) === 1 ? 'area' : 'areas' }}</p>
                    </div>
                    <div class="divide-y divide-slate-100 px-2 py-2">
                        @foreach($section['items'] as $item)
                            <a href="{{ \App\Support\ErpNavigation::href($item) }}" class="erp-setup-dashboard-link flex items-center gap-3 rounded-lg px-3 py-3 transition">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-blue-100 bg-blue-50 text-blue-600">
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] ?? $icons['settings'] }}"/></svg>
                                </span>
                                <span class="min-w-0 flex-1 text-sm font-medium text-slate-700">{{ $item['label'] }}</span>
                                <svg class="h-4 w-4 text-slate-400 transition group-hover:text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
