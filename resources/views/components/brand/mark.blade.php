@props([
    'size' => 'md', // sm|md|lg|xl
])

@php
    $sizes = [
        'sm' => 'h-9 w-9',
        'md' => 'h-10 w-10',
        'lg' => 'h-11 w-11',
        'xl' => 'h-14 w-14',
    ];
    $class = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->class(['erp-brand-mark erp-brand-mark--image grid shrink-0 place-items-center overflow-hidden rounded-xl', $class]) }}>
    <img
        src="{{ asset('assets/brand/mmmhmc-hris-icon-enhanced.png') }}"
        alt="MMMHMC HRIS &amp; Payroll"
        width="56"
        height="56"
        class="erp-brand-mark-img erp-brand-mark-img--light h-full w-full object-contain p-1"
        decoding="async"
    >
    <img
        src="{{ asset('assets/brand/mmmhmc-hris-icon-transparent.png') }}"
        alt=""
        aria-hidden="true"
        width="56"
        height="56"
        class="erp-brand-mark-img erp-brand-mark-img--dark h-full w-full object-contain"
        decoding="async"
    >
</span>
