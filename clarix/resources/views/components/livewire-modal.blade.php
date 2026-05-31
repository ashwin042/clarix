@props(['title' => '', 'maxWidth' => 'lg'])

@php
$widthClass = match ($maxWidth) {
    'sm'  => 'max-w-sm',
    'md'  => 'max-w-md',
    'lg'  => 'max-w-lg',
    'xl'  => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    default => 'max-w-lg',
};
@endphp

<div
    x-data="{ show: @entangle('showModal').live }"
    x-show="show"
    x-on:keydown.escape.window="show = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/70 dark:bg-black/70 backdrop-blur-sm"
        @click="show = false"
    ></div>

    {{-- Panel --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl dark:shadow-black/50 dark:ring-1 dark:ring-white/5 w-full {{ $widthClass }} max-h-[90vh] flex flex-col z-10 overflow-hidden"
    >
        @if($title)
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-800/60 flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ $title }}</h3>
                <button @click="show = false" class="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors rounded-lg p-1 hover:bg-gray-100 dark:hover:bg-slate-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif
        <div class="overflow-y-auto flex-1">
            {{ $slot }}
        </div>
    </div>
</div>
