@props([
    'title' => 'HRIS & Payroll',
    'mode' => 'app',
])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}">
    <script>
        (() => {
            const saved = localStorage.getItem('erp-theme');
            document.documentElement.dataset.theme = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="erp-body antialiased">
    @php
        $mode = $mode ?? 'app';
        $isLauncher = $mode === 'launcher';
        $account = auth()->user()?->loadMissing('employee');
        $employeeName = $account?->employee?->full_name ?: $account?->emp_id;
        $initial = strtoupper(substr((string) $employeeName, 0, 1));
        $apps = \App\Support\ErpNavigation::visibleApps();
        $currentApp = \App\Support\ErpNavigation::currentApp();
        $icons = \App\Support\ErpNavigation::icons();
        $navHref = fn (array $item): string => \App\Support\ErpNavigation::href($item);
    @endphp

    <div
        @unless($isLauncher)
            x-data="{
                sidebarOpen: localStorage.getItem('erp-sidebar-open') !== 'false',
                toggleSidebar() {
                    this.sidebarOpen = ! this.sidebarOpen;
                    localStorage.setItem('erp-sidebar-open', this.sidebarOpen ? 'true' : 'false');
                }
            }"
            :class="sidebarOpen ? 'lg:grid-cols-[248px_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,1fr)]'"
        @endunless
        class="{{ $isLauncher ? 'erp-launcher-scene min-h-screen' : 'erp-app-shell min-h-screen lg:grid' }}"
    >
        @unless ($isLauncher)
            <aside x-cloak x-show="sidebarOpen" x-transition.opacity.duration.150ms class="erp-sidebar border-b lg:sticky lg:top-0 lg:h-screen lg:overflow-hidden lg:border-b-0 lg:border-r">
                <div class="flex h-full min-h-0 max-h-screen flex-col">
                    <div class="erp-sidebar-pinned sticky top-0 z-20 shrink-0">
                        <div class="erp-sidebar-brand flex items-center gap-2 border-b px-4 py-4">
                            <a href="{{ route('home') }}" class="erp-brand flex min-w-0 flex-1 items-center gap-3">
                                <x-brand.mark size="md" />
                                <div class="min-w-0">
                                    <p class="erp-brand-eyebrow text-[10px] font-bold uppercase">MMMHMC</p>
                                    <h1 class="erp-brand-title truncate text-base font-bold">HRIS &amp; Payroll</h1>
                                </div>
                            </a>
                        </div>

                        <div class="erp-sidebar-home border-b px-3 py-3">
                            <a href="{{ route('home') }}" class="erp-nav-link erp-nav-link-depth-1 {{ request()->routeIs('home') ? 'erp-nav-link-active' : '' }}">
                                <span class="erp-nav-item-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="{{ $icons['grid'] }}"></path>
                                    </svg>
                                </span>
                                <span class="truncate font-semibold">All Apps</span>
                            </a>
                        </div>
                    </div>

                    <nav class="erp-sidebar-scroll min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-3 py-3 text-sm">
                        @if ($currentApp)
                            <div class="erp-nav-group">
                                <p class="erp-nav-group-label px-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide">
                                    {{ $currentApp['label'] }}
                                </p>
                                @foreach ($currentApp['menu_sections'] ?? [] as $section)
                                    @php
                                        $sectionKey = 'erp-nav-group-'.$currentApp['key'].'-'.\Illuminate\Support\Str::slug($section['label'] ?: 'navigation-'.$loop->index);
                                    @endphp
                                    <div
                                        x-data="{ expanded: localStorage.getItem(@js($sectionKey)) !== 'false' }"
                                        class="space-y-0.5 {{ ! $loop->first ? 'mt-3' : '' }}"
                                    >
                                        @if (! empty($section['label']))
                                            <button
                                                type="button"
                                                x-on:click="expanded = ! expanded; localStorage.setItem(@js($sectionKey), expanded ? 'true' : 'false')"
                                                class="erp-nav-section-toggle flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-[10px] font-semibold uppercase tracking-wider"
                                                :aria-expanded="expanded.toString()"
                                            >
                                                <span>{{ $section['label'] }}</span>
                                                <svg class="h-3.5 w-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                            </button>
                                        @endif
                                        <div x-cloak x-show="expanded" x-transition.opacity.duration.100ms class="space-y-0.5">
                                            @foreach ($section['items'] ?? [] as $item)
                                                <a
                                                    class="erp-nav-link erp-nav-link-depth-1 {{ ($item['active'] ?? false) ? 'erp-nav-link-active' : '' }}"
                                                    href="{{ $navHref($item) }}"
                                                >
                                                    <span class="erp-nav-item-icon" aria-hidden="true">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="{{ $icons[$item['icon']] ?? $icons['grid'] }}"></path>
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                                                    @if ($item['coming_soon'] ?? false)
                                                        <span class="erp-nav-soon">Soon</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="erp-nav-group space-y-0.5">
                                <p class="erp-nav-group-label px-2.5 pb-2 text-[11px] font-semibold uppercase tracking-wide">Apps</p>
                                @foreach ($apps as $app)
                                    <a class="erp-nav-link erp-nav-link-depth-1" href="{{ $app['href'] }}">
                                        <span class="erp-nav-item-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="{{ $icons[$app['icon']] ?? $icons['grid'] }}"></path>
                                            </svg>
                                        </span>
                                        <span class="min-w-0 truncate">{{ $app['label'] }}</span>
                                        @unless ($app['available'] ?? false)
                                            <span class="erp-nav-soon">Soon</span>
                                        @endunless
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </nav>
                </div>
            </aside>
        @endunless

        <section class="erp-app-main min-w-0">
            <header class="erp-topbar sticky top-0 z-30 border-b px-4 py-2 sm:px-5">
                <div class="flex w-full items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        @if ($isLauncher)
                            <a href="{{ route('home') }}" class="erp-brand flex items-center gap-3">
                                <x-brand.mark size="sm" />
                                <div class="min-w-0 hidden sm:block">
                                    <p class="erp-brand-eyebrow text-[10px] font-bold uppercase">MMMHMC</p>
                                    <p class="erp-brand-title truncate text-sm font-bold">HRIS &amp; Payroll</p>
                                </div>
                            </a>
                        @else
                            <button type="button" x-on:click="toggleSidebar()" class="erp-theme-toggle grid" :title="sidebarOpen ? 'Hide sidebar' : 'Show sidebar'" :aria-label="sidebarOpen ? 'Hide sidebar' : 'Show sidebar'" :aria-expanded="sidebarOpen.toString()">
                                <svg x-show="sidebarOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                                <svg x-show="! sidebarOpen" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 18l6-6-6-6"/><path d="M3 5h3v14H3z"/></svg>
                            </button>
                            <div class="erp-system-status hidden items-center gap-2 text-xs font-semibold sm:flex" aria-label="Current application">
                                <span class="erp-status-dot" aria-hidden="true"></span>
                                <span class="erp-subtle">
                                    <span class="erp-system-label">{{ $currentApp['label'] ?? 'Workspace' }}</span>
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 text-right">
                        @unless ($isLauncher)
                            <a href="{{ route('home') }}" class="erp-theme-toggle hidden sm:grid" title="All apps" aria-label="All apps">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="{{ $icons['grid'] }}"></path>
                                </svg>
                            </a>
                        @endunless
                        <button type="button" class="erp-theme-toggle" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme">
                            <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                            <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        </button>
                        <div class="relative" x-data="{ userMenuOpen: false }" x-on:keydown.escape.window="userMenuOpen = false">
                            <button
                                type="button"
                                class="erp-user-trigger flex items-center gap-2 rounded-xl px-1.5 py-1 text-right"
                                x-on:click="userMenuOpen = ! userMenuOpen"
                                :aria-expanded="userMenuOpen.toString()"
                                aria-haspopup="menu"
                            >
                                <span class="hidden sm:block">
                                    <span class="erp-user-name block text-sm font-semibold leading-tight">{{ $employeeName }}</span>
                                    <span class="erp-user-id block text-xs leading-tight">{{ $account?->emp_id }}</span>
                                </span>
                                <span class="erp-user-avatar grid h-8 w-8 place-items-center rounded-full text-xs font-bold">{{ $initial }}</span>
                                <svg class="erp-user-chevron h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': userMenuOpen }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6"></path>
                                </svg>
                            </button>

                            <div
                                x-cloak
                                x-show="userMenuOpen"
                                x-transition.origin.top.right
                                x-on:click.outside="userMenuOpen = false"
                                class="erp-user-menu absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-xl border p-2 text-left"
                                role="menu"
                            >
                                <div class="erp-user-menu-summary border-b px-3 py-2.5">
                                    <p class="erp-user-menu-label text-[10px] font-bold uppercase tracking-wider">Signed in as</p>
                                    <p class="erp-user-menu-name mt-1 truncate text-sm font-semibold">{{ $employeeName }}</p>
                                    <p class="erp-user-menu-id truncate text-xs">Employee ID {{ $account?->emp_id }}</p>
                                </div>
                                <a href="{{ route('home') }}" class="erp-user-menu-item mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold" role="menuitem">
                                    All apps
                                </a>
                                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="erp-user-menu-item erp-user-menu-item--danger flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold" role="menuitem">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                        </svg>
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="erp-content w-full px-3 py-4 sm:px-5 {{ $isLauncher ? 'erp-content-launcher' : 'erp-content-app' }}">
                @if (auth()->check() && (int) (auth()->user()->login_attempt ?? 1) === 0)
                    <div class="erp-shell-notice mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Please
                        <a href="{{ route('self-service.profile') }}" class="font-semibold underline">review and save your profile</a>
                        before using other modules.
                    </div>
                @endif
                {{ $slot }}
            </main>
        </section>
    </div>

    @livewireScripts
</body>
</html>
