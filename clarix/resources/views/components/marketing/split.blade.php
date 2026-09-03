@props([
    'reverse' => false,
    'ratio' => 'lg:grid-cols-2',
])

{{--
    A text column beside a visual. Alternating `reverse` down a page is what
    gives the product pages their rhythm without each row needing its own
    grid classes — the gap and the vertical centring stay put.

    The order swap is lg-only: stacked on a phone the text always comes first,
    because a mockup with no preceding sentence explains nothing.
--}}
<div {{ $attributes->merge(['class' => 'grid items-center gap-10 ' . $ratio . ' lg:gap-16']) }}>
    <div class="{{ $reverse ? 'lg:order-2' : '' }}">
        {{ $text }}
    </div>

    <div class="{{ $reverse ? 'lg:order-1' : '' }}">
        {{ $visual }}
    </div>
</div>
