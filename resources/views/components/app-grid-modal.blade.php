@php
    $apps = \App\Support\ErpNavigation::visibleApps();
    $icons = \App\Support\ErpNavigation::icons();
@endphp

<template x-teleport="body">
    <div
        x-cloak
        x-show="appGridOpen"
        x-transition.opacity.duration.180ms
        x-effect="document.documentElement.classList.toggle('erp-app-grid-open', appGridOpen)"
        x-on:keydown.escape.window="if (appGridOpen) closeAppGrid()"
        x-on:keydown.tab="trapAppGridFocus($event)"
        class="erp-app-grid-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="app-grid-title"
        aria-describedby="app-grid-description"
    >
        <button
            type="button"
            class="erp-app-grid-backdrop"
            x-on:click="closeAppGrid()"
            aria-label="Close app grid"
        ></button>

        <section x-ref="appGridPanel" class="erp-app-grid-panel" x-on:click.stop>
            <header class="erp-app-grid-header">
                <div>
                    <h2 id="app-grid-title">Applications</h2>
                    <p id="app-grid-description">Choose an application to continue.</p>
                </div>
                <button
                    x-ref="appGridClose"
                    type="button"
                    class="erp-app-grid-close"
                    x-on:click="closeAppGrid()"
                    aria-label="Close app grid"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                    <span>Close</span>
                </button>
            </header>

            <nav class="erp-app-grid-list" aria-label="Applications">
                @foreach ($apps as $app)
                    @php($isAvailable = $app['available'] ?? false)
                    <a
                        href="{{ $app['href'] }}"
                        class="erp-app-grid-item {{ ($app['active'] ?? false) ? 'erp-app-grid-item--active' : '' }}"
                        title="{{ $isAvailable ? 'Open '.$app['label'] : $app['label'].' (module under construction)' }}"
                    >
                        <span class="erp-app-grid-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $icons[$app['icon']] ?? $icons['grid'] }}" />
                            </svg>
                        </span>
                        <span class="erp-app-grid-label">{{ $app['label'] }}</span>
                        @unless ($isAvailable)
                            <span class="erp-app-grid-status">Coming soon</span>
                        @endunless
                    </a>
                @endforeach
            </nav>

            <footer class="erp-app-grid-footer">
                <span class="erp-app-grid-footer-note">
                    Role-based access
                    <kbd class="erp-app-grid-shortcut">Alt A</kbd>
                </span>
                <a href="{{ route('home') }}">Open full workspace</a>
            </footer>
        </section>
    </div>
</template>
