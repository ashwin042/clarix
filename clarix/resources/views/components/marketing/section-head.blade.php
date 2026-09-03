@props([
    'eyebrow' => null,
    'heading',
    'lede' => null,
    'align' => 'center',
    'tone' => 'dark',
])

@php
    $centred = $align === 'center';

    // On the indigo band the type inverts, the way the AXOKAI section does.
    $headingTone = $tone === 'light' ? 'text-white' : '';
    $ledeTone    = $tone === 'light' ? 'text-white/75' : 'text-[#4A4F63]';
    $eyebrowTone = $tone === 'light' ? 'text-white/70' : 'text-indigo-600';
@endphp

<div class="{{ $centred ? 'text-center' : '' }}">
    @if ($eyebrow)
        <span class="block text-[12.5px] font-semibold uppercase tracking-[.08em] {{ $eyebrowTone }}">
            {{ $eyebrow }}
        </span>
    @endif

    <h2 class="{{ $eyebrow ? 'mt-3' : '' }} font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] sm:text-[44px] {{ $headingTone }}">
        {{ $heading }}
    </h2>

    @if ($lede)
        <p class="mt-5 max-w-2xl text-[15px] leading-relaxed sm:text-base {{ $ledeTone }} {{ $centred ? 'mx-auto' : '' }}">
            {{ $lede }}
        </p>
    @endif
</div>
