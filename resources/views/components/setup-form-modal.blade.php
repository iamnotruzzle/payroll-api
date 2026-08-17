@props([
    'name',
    'title',
    'editTitle' => null,
    'description' => null,
    'size' => 'md',
    'width' => null,
])

@php
    $resolvedSize = $size;
    if ($width) {
        $resolvedSize = match (true) {
            str_contains($width, '6xl'), str_contains($width, '5xl'), str_contains($width, '4xl') => 'wide',
            str_contains($width, '3xl'), str_contains($width, '2xl') => 'lg',
            str_contains($width, 'xl') => 'lg',
            default => 'md',
        };
    }
@endphp

<template x-teleport="body">
    <div
        x-data="{
            name: @js($name),
            open: false,
            editing: false,
            pristine: true,
            init() {
                window.__erpOverlayState = window.__erpOverlayState || {};
                window.__erpOverlayState[this.name] ??= { open: false, editing: false, pristine: true };
                const state = window.__erpOverlayState[this.name];
                this.open = state.open;
                this.editing = state.editing;
                this.pristine = state.pristine !== false;
            },
        }"
        x-cloak
        x-show="open"
        x-transition.opacity.duration.150ms
        x-bind:class="{ 'erp-overlay--pristine': pristine }"
        x-on:keydown.escape.window="if (open) window.erpOverlay.close(name)"
        x-on:submit.capture="pristine = false; window.erpOverlay.getState(name).pristine = false"
        x-on:erp-overlay-open.window="
            if (($event.detail?.name ?? null) !== name) return;
            editing = Boolean($event.detail?.editing);
            pristine = true;
            open = true;
            window.erpOverlay.getState(name).open = true;
            window.erpOverlay.getState(name).editing = editing;
            window.erpOverlay.getState(name).pristine = true;
        "
        x-on:erp-overlay-close.window="
            if ($event.detail?.name && $event.detail.name !== name) return;
            open = false;
            window.erpOverlay.getState(name).open = false;
        "
        class="erp-overlay erp-overlay--modal"
        role="dialog"
        aria-modal="true"
        :aria-label="editing && @js($editTitle) ? @js($editTitle) : @js($title)"
    >
        <button type="button" x-on:click="window.erpOverlay.close(name)" class="erp-overlay-backdrop" :aria-label="'Close ' + (editing && @js($editTitle) ? @js($editTitle) : @js($title))"></button>
        <div class="erp-overlay-panel erp-overlay-panel--{{ $resolvedSize }}" x-on:click.stop>
            <header class="erp-overlay-header">
                <div>
                    <h2 class="erp-overlay-title" x-text="editing && @js($editTitle) ? @js($editTitle) : @js($title)">{{ $title }}</h2>
                    @if ($description)
                        <p class="erp-overlay-description">{{ $description }}</p>
                    @endif
                </div>
                <button type="button" x-on:click="window.erpOverlay.close(name)" class="erp-overlay-close">Close</button>
            </header>
            <div class="erp-overlay-body erp-content">
                {{ $slot }}
            </div>
        </div>
    </div>
</template>
