@props(['launcher' => false])

@php
    $hasProfile = auth()->user()?->canAny(['self-service.profile', 'self-service.access']);
    $profileActive = request()->routeIs('self-service.profile*');
    $profileIndex = $launcher ? 2 : 3;
    $count = 2 + ($launcher ? 0 : 1) + ($hasProfile ? 1 : 0);
    $defaultIndex = $profileActive && $hasProfile ? $profileIndex : ($launcher ? 0 : 2);
@endphp

<nav
    class="erp-mobile-navigation"
    aria-label="Mobile workspace navigation"
    style="--mobile-nav-count: {{ $count }}; --mobile-nav-index: {{ $defaultIndex }}"
    :style="{ '--mobile-nav-index': appGridOpen ? 1 : (sidebarOpen ? {{ $launcher ? 0 : 2 }} : {{ $defaultIndex }}) }"
>
    <span class="erp-mobile-navigation-indicator" aria-hidden="true"><span></span></span>
    <a href="{{ route('home') }}" class="erp-mobile-navigation-item"
        :class="{ 'is-active': !appGridOpen && !sidebarOpen && {{ $defaultIndex }} === 0 }"
        @if ($launcher) aria-current="page" @endif>
        <span class="erp-mobile-navigation-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg></span>
        <span>Home</span>
    </a>
    <button type="button" class="erp-mobile-navigation-item"
        :class="{ 'is-active': appGridOpen }"
        x-on:click="closeMobileSidebar(); openAppGrid()"
        :aria-expanded="appGridOpen.toString()" aria-haspopup="dialog">
        <span class="erp-mobile-navigation-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
        <span>Apps</span>
    </button>
    @unless ($launcher)
        <button type="button" class="erp-mobile-navigation-item"
            :class="{ 'is-active': !appGridOpen && (sidebarOpen || {{ $defaultIndex }} === 2) }"
            x-on:click="toggleSidebar()" :aria-expanded="sidebarOpen.toString()" aria-controls="erp-application-navigation">
            <span class="erp-mobile-navigation-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></span>
            <span>Menu</span>
        </button>
    @endunless
    @if ($hasProfile)
        <a href="{{ route('self-service.profile') }}" class="erp-mobile-navigation-item"
            :class="{ 'is-active': !appGridOpen && !sidebarOpen && {{ $defaultIndex }} === {{ $profileIndex }} }"
            @if ($profileActive) aria-current="page" @endif>
            <span class="erp-mobile-navigation-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span>
            <span>Profile</span>
        </a>
    @endif
</nav>
