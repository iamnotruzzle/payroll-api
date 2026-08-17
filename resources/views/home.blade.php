<x-layouts.app :title="$title" mode="launcher">
    @php($icons = \App\Support\ErpNavigation::icons())

    <section class="erp-launcher" data-launcher-root>
        <div class="erp-launcher-atmosphere" aria-hidden="true">
            <span class="erp-launcher-orbit erp-launcher-orbit--outer" data-launcher-orbit><i></i></span>
            <span class="erp-launcher-orbit erp-launcher-orbit--inner" data-launcher-orbit><i></i></span>
            <span class="erp-launcher-haze" data-launcher-haze></span>
            <span class="erp-launcher-axis erp-launcher-axis--horizontal"></span>
            <span class="erp-launcher-axis erp-launcher-axis--vertical"></span>
            <span class="erp-launcher-calibration"></span>
        </div>

        <header class="erp-launcher-intro" data-launcher-intro>
            <div class="erp-launcher-intro-copy">
                <h1>Your workspace</h1>
                <p>
                    Choose an application to continue{{ $employeeName ? ', '.$employeeName : '' }}.
                    Your access follows your assigned role and permissions.
                </p>
            </div>
            <div class="erp-launcher-access-note" aria-label="Secure role-based access">
                <span aria-hidden="true"></span>
                <strong>Secure access</strong>
                <small>Role-based workspace</small>
            </div>
        </header>

        <div class="erp-launcher-groups">
            @foreach ($moduleGroups as $group)
                <section class="erp-launcher-group" aria-labelledby="launcher-group-{{ $group['key'] }}" data-launcher-group>
                    <header class="erp-launcher-group-heading">
                        <h2 id="launcher-group-{{ $group['key'] }}">{{ $group['label'] }}</h2>
                        <p>{{ $group['description'] }}</p>
                        <span class="erp-launcher-signal-track" aria-hidden="true"><i data-launcher-signal></i></span>
                    </header>

                    <div class="erp-launcher-grid">
                        @foreach ($group['modules'] as $module)
                            @php($isFeatured = $module['key'] === 'self-service')
                            @php($isWide = in_array($module['key'], ['employees', 'payroll'], true))
                            @php($isAvailable = $module['available'] ?? false)
                            <a
                                href="{{ $module['href'] }}"
                                class="erp-launcher-card {{ $isFeatured ? 'erp-launcher-card--featured' : '' }} {{ $isWide ? 'erp-launcher-card--wide' : '' }}"
                                data-module="{{ $module['key'] }}"
                                data-launcher-card
                                aria-describedby="launcher-description-{{ $module['key'] }}"
                                title="{{ $isAvailable ? $module['label'].' — '.$module['description'] : $module['label'].' (module under construction)' }}"
                            >
                                <span class="erp-launcher-card-corners" aria-hidden="true"></span>
                                <span class="erp-launcher-card-topline">
                                    <span class="erp-launcher-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="{{ $icons[$module['icon']] ?? $icons['grid'] }}" />
                                        </svg>
                                    </span>

                                    @unless ($isAvailable)
                                        <span class="erp-launcher-status"><span aria-hidden="true"></span>Coming soon</span>
                                    @endunless
                                </span>

                                <span class="erp-launcher-card-copy">
                                    <span class="erp-launcher-card-title">{{ $module['label'] }}</span>
                                    <span class="erp-launcher-card-description" id="launcher-description-{{ $module['key'] }}">{{ $module['description'] }}</span>
                                </span>

                                <span class="erp-launcher-card-action" aria-hidden="true">
                                    <span class="erp-launcher-card-action-label">{{ $isAvailable ? 'Open application' : 'View status' }}</span>
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 10h11M11 6l4 4-4 4" />
                                    </svg>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </section>
</x-layouts.app>
