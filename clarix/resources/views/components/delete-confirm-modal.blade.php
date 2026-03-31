@props(['title' => 'Delete Item', 'description' => '', 'consequences' => []])

<div
    x-data="{
        show: @entangle('showDeleteModal').live,
        confirmText: '',
        get isValid() { return this.confirmText.toLowerCase().trim() === 'delete'; }
    }"
    x-show="show"
    x-on:keydown.escape.window="if (show) { show = false; confirmText = ''; }"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    {{-- Backdrop --}}
    <div x-show="show"
        x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
        @click="show = false; confirmText = ''"></div>

    {{-- Panel --}}
    <div x-show="show"
        x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">

        {{-- Header --}}
        <div class="px-6 pt-6 pb-0">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                    @if($description)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $description }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Body --}}
        <div class="px-6 py-4 space-y-4">
            {{-- Consequences --}}
            @if(count($consequences))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                    <p class="text-xs font-semibold text-red-700 dark:text-red-400 uppercase tracking-wide mb-1.5">This will permanently:</p>
                    <ul class="space-y-1">
                        @foreach($consequences as $item)
                            <li class="flex items-start gap-2 text-sm text-red-600 dark:text-red-400">
                                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Confirmation Input --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                    Type <span class="font-bold text-red-600 dark:text-red-400">DELETE</span> to confirm
                </label>
                <input
                    x-model="confirmText"
                    type="text"
                    placeholder="Type DELETE to confirm"
                    autocomplete="off"
                    class="w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 transition-colors"
                    :class="confirmText.length > 0 && !isValid
                        ? 'border-red-300 dark:border-red-600 focus:ring-red-500 bg-red-50 dark:bg-red-900/10'
                        : 'border-gray-300 dark:border-gray-600 focus:ring-indigo-500 bg-white dark:bg-gray-700 dark:text-white'"
                    @keydown.enter="if (isValid) { $wire.confirmDelete(); confirmText = ''; }"
                >
                <p x-show="confirmText.length > 0 && !isValid" x-transition
                    class="mt-1 text-xs text-red-500">Please type DELETE exactly to proceed.</p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex gap-3 px-6 pb-6">
            <button
                x-bind:disabled="!isValid"
                @click="if (isValid) { $wire.confirmDelete(); confirmText = ''; }"
                class="flex-1 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200"
                :class="isValid
                    ? 'bg-red-600 text-white hover:bg-red-700 cursor-pointer'
                    : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed'"
            >
                Delete Permanently
            </button>
            <button @click="show = false; confirmText = ''"
                class="flex-1 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>
