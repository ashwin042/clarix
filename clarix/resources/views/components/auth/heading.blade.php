@props(['title', 'subtitle' => null])

<div {{ $attributes->class(['auth-rise mb-8']) }} style="--i:1">
    <h1 class="text-[28px] font-semibold tracking-tight leading-tight" style="color: var(--auth-text)">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-2 text-[15px] leading-relaxed" style="color: var(--auth-muted)">{{ $subtitle }}</p>
    @endif
</div>
