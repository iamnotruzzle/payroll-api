<x-layouts.app :title="$title">
    <section class="erp-coming-soon mx-auto max-w-2xl rounded-2xl border px-5 py-8 text-center sm:px-8">
        <div class="erp-coming-soon-badge mx-auto grid h-14 w-14 place-items-center rounded-2xl">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="6" width="20" height="12" rx="2"/>
                <path d="M12 12h.01M17 12h.01M7 12h.01"/>
            </svg>
        </div>

        <p class="erp-brand-eyebrow mt-5 text-[11px] font-bold uppercase">{{ $moduleLabel }}</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight text-[color:var(--erp-text)]">
            {{ $featureLabel ?: $moduleLabel }} is under construction
        </h2>
        <p class="erp-subtle mx-auto mt-3 max-w-lg text-sm leading-relaxed">
            This workspace item is reserved in the HRIS &amp; Payroll navigation while the module is being built.
            Existing schedule, timekeeping, and payroll tools remain available from the sidebar.
        </p>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <a href="{{ route('home') }}" class="erp-coming-soon-btn inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white">
                Back to workspace
            </a>
            <a href="javascript:history.back()" class="erp-coming-soon-secondary inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold">
                Go back
            </a>
        </div>
    </section>
</x-layouts.app>
