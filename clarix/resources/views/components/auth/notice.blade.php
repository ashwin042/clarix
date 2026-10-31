@props(['status' => null])

@if ($status)
    <div role="status" {{ $attributes->class(['auth-notice']) }}>
        <svg class="mt-0.5 flex-none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
        <span>{{ $status }}</span>
    </div>
@endif
