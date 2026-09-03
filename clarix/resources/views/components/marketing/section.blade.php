@props([
    'surface' => 'white',
    'width' => 'max-w-6xl',
    'divider' => false,
    'id' => null,
])

@php
    // The four surfaces the marketing site actually uses. Anything outside this
    // set would be a new colour on a site that has exactly these.
    $surfaces = [
        'white' => 'bg-white',
        'soft'  => 'bg-[#F5F4FA]',
        'page'  => 'bg-[#FCFCFD]',
        'ink'   => 'bg-[#4F46E5] text-white',
    ];

    $classes = $surfaces[$surface] ?? $surfaces['white'];

    // A hairline above the section, the way the homepage separates its soft
    // bands from the white ones. Never on ink, where it would not read.
    if ($divider && $surface !== 'ink') {
        $classes .= ' border-t border-black/[.05]';
    }
@endphp

<section @if ($id) id="{{ $id }}" @endif
         {{ $attributes->merge(['class' => 'relative px-6 py-20 sm:py-24 ' . $classes]) }}>
    <div class="mx-auto {{ $width }}">
        {{ $slot }}
    </div>
</section>
