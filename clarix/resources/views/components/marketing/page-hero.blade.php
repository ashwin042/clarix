@props([
    'eyebrow' => null,
    'heading',
    'lede' => null,
    'align' => 'left',
    'tone' => 'dark',
    'primary' => null,
    'primaryLabel' => 'Get started',
    'secondaryLabel' => 'Book a demo',
    'secondary' => '/home#schedule-demo',
])

@php
    $centred = $align === 'center';
    $primary ??= route('login');

    $light = $tone === 'light';
@endphp

{{--
    A subpage hero. Same type roles and pill buttons as the homepage, one step
    down in scale: the homepage's h1 runs to 76px because it is the front door,
    and a product page that shouted as loudly would flatten the hierarchy
    between them.
--}}
<div class="{{ $centred ? 'mx-auto max-w-3xl text-center' : 'max-w-xl' }}">

    @if ($eyebrow)
        <span class="rise block text-[12.5px] font-semibold uppercase tracking-[.08em] {{ $light ? 'text-white/70' : 'text-indigo-600' }}">
            {{ $eyebrow }}
        </span>
    @endif

    <h1 class="rise {{ $eyebrow ? 'mt-4' : '' }} font-display text-[36px] font-normal leading-[1.04] tracking-[-0.025em] sm:text-[52px] lg:text-[58px] {{ $light ? 'text-white' : '' }}">
        {{ $heading }}
    </h1>

    @if ($lede)
        <p class="rise rise-2 mt-6 text-[15px] leading-relaxed sm:text-[17px] {{ $light ? 'text-white/75' : 'text-[#5B6076]' }} {{ $centred ? 'mx-auto max-w-xl' : '' }}">
            {{ $lede }}
        </p>
    @endif

    <div class="rise rise-3 mt-8 flex flex-col gap-3 sm:flex-row {{ $centred ? 'items-center justify-center' : 'sm:items-center' }}">
        <a href="{{ $primary }}"
           @if (str_starts_with($primary, 'http')) target="_blank" rel="noopener noreferrer" @endif
           class="inline-flex w-full items-center justify-center rounded-full px-6 py-3 text-[15px] font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto
                  {{ $light
                      ? 'bg-white text-[#0F1222] hover:bg-white/90 focus-visible:outline-white'
                      : 'bg-indigo-600 text-white shadow-[0_10px_24px_-8px_rgba(79,70,229,.7)] hover:bg-indigo-700 hover:shadow-[0_14px_30px_-8px_rgba(79,70,229,.8)] focus-visible:outline-indigo-600' }}">
            {{ $primaryLabel }}
        </a>

        @if ($secondaryLabel)
            <a href="{{ $secondary }}"
               class="inline-flex w-full items-center justify-center rounded-full border px-6 py-3 text-[15px] font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto
                      {{ $light
                          ? 'border-white/35 text-white hover:border-white/60 hover:bg-white/10 focus-visible:outline-white'
                          : 'border-[#0F1222]/[.14] bg-white text-[#0F1222] hover:border-[#0F1222]/25 focus-visible:outline-indigo-600' }}">
                {{ $secondaryLabel }}
            </a>
        @endif
    </div>
</div>
