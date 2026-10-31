@props(['target' => null, 'loading' => 'Working'])

<button {{ $attributes->merge(['type' => 'submit', 'class' => 'auth-btn']) }}
    @if ($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif>
    @if ($target)
        <svg wire:loading wire:target="{{ $target }}" class="auth-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"></circle>
            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
        <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}">{{ $loading }}</span>
    @else
        {{ $slot }}
    @endif
</button>
