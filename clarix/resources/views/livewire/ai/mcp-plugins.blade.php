<div class="space-y-8">

    {{-- ============ MCP explainer (unchanged) ============ --}}
    <div class="max-w-2xl">
        <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10">
                <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span>

            <h1 class="mt-5 text-xl font-bold text-gray-900 dark:text-slate-100">MCP &amp; Plugins</h1>
            <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-slate-400">
                Connect Clarix to your own tools over the Model Context Protocol, so AXOKAI can read and write in the systems your team already uses.
            </p>

            <div class="mt-6 flex items-center gap-2 border-t border-gray-100 pt-5 dark:border-slate-800/60">
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-800 dark:text-slate-400">Coming soon</span>
                <a href="{{ route('ai.chatbot') }}" class="text-[13px] font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Open Chatbot instead</a>
            </div>
        </div>
    </div>

    {{-- ================ plugin library ================ --}}
    <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">Connect your tools</h2>
        <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-gray-500 dark:text-slate-400">
            Link the apps your team already uses. Once connected, AXOKAI and Clarix automations can work across all of them from one place.
        </p>

        {{-- One open card at a time, so the state lives here on the grid rather
             than on each card: openPlugin holds the index of the open one, or
             null when they are all shut. A card is open when its own index
             matches, so opening one closes the rest without any card needing to
             know about its neighbours.

             items-start, or expanding one card stretches the whole row and
             leaves its neighbours with dead space below their copy. --}}
        <div x-data="{ openPlugin: null }" class="mt-5 grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($plugins as $plugin)
                @php $card = $loop->index; @endphp

                <div class="flex flex-col rounded-xl border border-gray-200 bg-white transition-colors dark:border-slate-800 dark:bg-slate-900"
                    :class="openPlugin === {{ $card }} && 'border-gray-300 dark:border-slate-700'">

                    {{-- clicking the open card again shuts it --}}
                    <button type="button"
                        @click="openPlugin = openPlugin === {{ $card }} ? null : {{ $card }}"
                        :aria-expanded="openPlugin === {{ $card }}"
                        aria-controls="plugin-panel-{{ $card }}"
                        class="flex w-full items-start gap-3 p-4 text-left">

                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl"
                            style="background-color: {{ $plugin['colour'] }}1F">
                            <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="{{ $plugin['colour'] }}" aria-hidden="true">
                                <path d="{{ $plugin['logo'] }}"/>
                            </svg>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $plugin['name'] }}</span>
                                <span class="flex flex-shrink-0 items-center gap-1.5">
                                    <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold {{ $tints[$plugin['category']] }}">{{ $plugin['category'] }}</span>
                                    <svg class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="openPlugin === {{ $card }} && 'rotate-180'"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </span>
                            </span>
                            <span class="mt-1 block text-[12.5px] leading-snug text-gray-500 dark:text-slate-400">{{ $plugin['blurb'] }}</span>
                        </span>
                    </button>

                    {{-- configuration panel --}}
                    <div id="plugin-panel-{{ $card }}" x-show="openPlugin === {{ $card }}" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="border-t border-gray-100 px-4 pb-4 pt-3.5 dark:border-slate-800/60">

                        @if ($plugin['connect'] ?? false)

                            {{-- The one live integration, so this panel holds the
                                 real thing rather than a mock of it. Linking binds
                                 one person, not the agency, which is why the card
                                 is a component with its own state and not fields
                                 on this page. --}}
                            <livewire:profile.connect-telegram wire:key="connect-telegram" />

                        @else

                            <div class="space-y-3">
                                @foreach ($plugin['fields'] as [$label, $placeholder, $type])
                                    <div>
                                        <label class="block text-[12px] font-medium text-gray-500 dark:text-slate-400">{{ $label }}</label>
                                        {{-- Disabled throughout: there is nothing behind these to save to. --}}
                                        <input type="{{ $type }}" placeholder="{{ $placeholder }}" disabled
                                            class="mt-1.5 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-[13px] text-gray-400 placeholder-gray-300 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-500 dark:placeholder-slate-600">
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3.5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-500/25 dark:bg-amber-500/10">
                                <svg class="mt-px h-3.5 w-3.5 flex-shrink-0 text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.5m0 3.5h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                                <p class="text-[11.5px] leading-relaxed text-amber-800 dark:text-amber-200/90">
                                    This integration is currently in development and will be available on Clarix soon.
                                </p>
                            </div>

                            <button type="button" disabled
                                class="mt-3.5 w-full cursor-not-allowed rounded-lg bg-gray-100 px-4 py-2 text-[13px] font-semibold text-gray-400 dark:bg-slate-800 dark:text-slate-600">
                                Save Setting
                            </button>

                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
