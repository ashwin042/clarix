@props(['id', 'label', 'errorKey', 'type' => 'text', 'toggle' => false])

@php
    $messages = (array) $errors->get($errorKey);
    $hasError = count($messages) > 0;
@endphp

<div class="auth-field">
    <div class="auth-label-row">
        <label for="{{ $id }}" class="auth-label">{{ $label }}</label>
        @isset($aside)
            {{ $aside }}
        @endisset
    </div>

    <div class="auth-control" @if ($toggle) x-data="{ show: false }" @endif>
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            @if ($toggle) :type="show ? 'text' : 'password'" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['auth-input', 'is-invalid' => $hasError, 'has-toggle' => $toggle]) }}
        >

        @if ($toggle)
            <button type="button" class="auth-toggle" x-on:click="show = !show" x-bind:aria-pressed="show.toString()" x-bind:aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>
            </button>
        @endif
    </div>

    @if ($hasError)
        <ul id="{{ $id }}-error" role="alert" class="auth-error auth-error-wrap">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
</div>
