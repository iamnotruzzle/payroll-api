<x-layouts.app :title="$title" mode="launcher">
    <section class="erp-launcher">
        <div class="erp-launcher-intro">
            <p class="erp-brand-eyebrow text-[11px] font-bold uppercase">MMMHMC</p>
            <h2 class="mt-1 font-bold tracking-tight text-[color:var(--erp-text)]">
                HRIS &amp; Payroll
            </h2>
            <p class="erp-subtle mt-1 max-w-xl text-sm leading-relaxed">
                Choose an app to continue{{ $employeeName ? ', '.$employeeName : '' }}.
            </p>
        </div>

        <div class="erp-launcher-grid">
            @foreach ($modules as $module)
                <a
                    href="{{ $module['href'] }}"
                    class="erp-launcher-app group"
                    data-accent="{{ $module['accent'] }}"
                    title="{{ ($module['available'] ?? false) ? $module['label'] : $module['label'].' (coming soon)' }}"
                >
                    <span class="erp-launcher-icon" aria-hidden="true">
                        @php($icon = $module['icon'])
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            @switch($icon)
                                @case('user')
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                    @break
                                @case('users')
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>
                                    @break
                                @case('umbrella')
                                    <path d="M12 13v8M8 21h8M12 3a8 8 0 0 0-8 8h16a8 8 0 0 0-8-8z"/>
                                    @break
                                @case('calendar-range')
                                    <path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2M8 14h3M13 14h3M8 18h8"/>
                                    @break
                                @case('file-clock')
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 13v3l2 1"/>
                                    @break
                                @case('wallet')
                                    <path d="M20 7V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10H5a3 3 0 0 1-3-3V7"/><path d="M16 14h.01"/>
                                    @break
                                @case('graduation-cap')
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
                                    @break
                                @case('award')
                                    <circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>
                                    @break
                                @case('settings')
                                    <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.9 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>
                                    @break
                            @endswitch
                        </svg>
                        @unless ($module['available'] ?? false)
                            <span class="erp-launcher-soon">Soon</span>
                        @endunless
                    </span>
                    <span class="erp-launcher-label">{{ $module['label'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
</x-layouts.app>
