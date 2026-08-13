@props(['title', 'description' => null, 'closeAction' => 'closeForm', 'width' => 'max-w-2xl'])

@teleport('body')
<div class="fixed inset-0 z-[100] flex justify-end" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <button type="button" wire:click="{{ $closeAction }}" class="absolute inset-0 bg-slate-950/35" aria-label="Close {{ $title }}"></button>
    <section class="relative flex h-full w-full {{ $width }} flex-col bg-white shadow-2xl">
        <header class="flex items-start justify-between gap-4 border-b px-6 py-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>
            <button type="button" wire:click="{{ $closeAction }}" class="rounded-md border px-3 py-2 text-sm font-medium hover:bg-slate-50">Close</button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </div>
    </section>
</div>
@endteleport
