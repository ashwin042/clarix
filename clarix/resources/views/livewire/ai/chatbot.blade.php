{{-- Chat and forget: no history rail, so the panel is the full width of the
     content area. h-full, not min-h-full — the composer is pinned to the
     bottom and only the thread scrolls, so the column needs a fixed height to
     divide up rather than growing with its content. --}}
<div class="flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900"
    x-data="{
        menu: null,
        scrollDown() { this.$nextTick(() => { this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight }) }
    }"
    x-init="scrollDown()"
    @chat-updated.window="scrollDown()">

    {{-- ==================== header ==================== --}}
    <header class="flex h-14 flex-shrink-0 items-center gap-2 border-b border-gray-100 bg-white/80 px-4 backdrop-blur dark:border-slate-800/60 dark:bg-slate-900/80">

        <span class="mr-1 flex-shrink-0 text-sm font-semibold text-gray-800 dark:text-slate-200">Clarix AI</span>

        {{-- model selector. The live dot is the at-a-glance "which brain am I
             talking to" cue; it also tells you the panel is connected. --}}
        <div class="relative" @click.outside="menu === 'model' && (menu = null)">
            <button type="button" @click="menu = menu === 'model' ? null : 'model'"
                class="flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-1.5 text-[13px] font-medium text-gray-600 transition-all duration-150 hover:border-gray-200 hover:bg-gray-50 hover:shadow-sm dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800/60"
                :class="menu === 'model' && '!border-gray-200 bg-gray-50 shadow-sm dark:!border-slate-700 dark:bg-slate-800/60'">
                <span class="relative flex h-1.5 w-1.5 flex-shrink-0">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                </span>
                <span>{{ $model }}</span>
                <svg class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="menu === 'model' && 'rotate-180'"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="menu === 'model'" x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="absolute left-0 z-50 mt-1.5 w-56 origin-top-left rounded-xl border border-gray-200/80 bg-white p-1 shadow-xl shadow-gray-900/10 ring-1 ring-black/[0.02] dark:border-slate-700/60 dark:bg-slate-900 dark:shadow-2xl dark:shadow-black/40">
                @foreach ($models as $m)
                    <button type="button" wire:click="setModel('{{ $m }}')" @click="menu = null"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-[13px] transition-colors {{ $model === $m ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' : 'text-gray-700 hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        <span class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full {{ $model === $m ? 'bg-indigo-500' : 'bg-gray-300 dark:bg-slate-600' }}"></span>
                            {{ $m }}
                        </span>
                        @if ($model === $m)
                            <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- thinking effort selector --}}
        <div class="relative" @click.outside="menu === 'effort' && (menu = null)">
            <button type="button" @click="menu = menu === 'effort' ? null : 'effort'"
                class="flex items-center gap-1.5 rounded-lg border border-transparent px-2.5 py-1.5 text-[13px] font-medium text-gray-600 transition-all duration-150 hover:border-gray-200 hover:bg-gray-50 hover:shadow-sm dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800/60"
                :class="menu === 'effort' && '!border-gray-200 bg-gray-50 shadow-sm dark:!border-slate-700 dark:bg-slate-800/60'">
                <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span>{{ $effort }}</span>
                <svg class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200" :class="menu === 'effort' && 'rotate-180'"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="menu === 'effort'" x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="absolute left-0 z-50 mt-1.5 w-64 origin-top-left rounded-xl border border-gray-200/80 bg-white p-1 shadow-xl shadow-gray-900/10 ring-1 ring-black/[0.02] dark:border-slate-700/60 dark:bg-slate-900 dark:shadow-2xl dark:shadow-black/40">
                @foreach ($efforts as $label => $desc)
                    <button type="button" wire:click="setEffort('{{ $label }}')" @click="menu = null"
                        class="flex w-full items-start justify-between gap-2 rounded-lg px-2.5 py-2 text-left transition-colors {{ $effort === $label ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-gray-50 dark:hover:bg-slate-800' }}">
                        <span class="min-w-0">
                            <span class="block text-[13px] {{ $effort === $label ? 'font-semibold text-indigo-700 dark:text-indigo-400' : 'font-medium text-gray-700 dark:text-slate-300' }}">{{ $label }}</span>
                            <span class="mt-0.5 block text-[11.5px] leading-snug text-gray-400 dark:text-slate-500">{{ $desc }}</span>
                        </span>
                        @if ($effort === $label)
                            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Share is gone: nothing is saved, so there is no session to hand to
             anyone. Clearing the thread is still worth a control. --}}
        <div class="relative ml-auto" @click.outside="menu === 'options' && (menu = null)">
            <button type="button" @click="menu = menu === 'options' ? null : 'options'" title="Chat options"
                class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800/50 dark:hover:text-slate-300">
                <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5h.01M12 12h.01M12 19h.01"/>
                </svg>
                <span class="sr-only">Chat options</span>
            </button>
            <div x-show="menu === 'options'" x-cloak x-transition
                class="absolute right-0 z-50 mt-1 w-44 rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-slate-700/60 dark:bg-slate-900 dark:shadow-2xl dark:shadow-black/40">
                <button type="button" wire:click="clear" @click="menu = null"
                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-slate-800">Clear conversation</button>
            </div>
        </div>
    </header>

    {{-- ==================== thread ==================== --}}
    <div x-ref="thread" class="flex-1 overflow-y-auto px-4 py-7 sm:px-6">
        <div class="mx-auto max-w-3xl space-y-7">

            @forelse ($messages as $i => $m)
                @if ($m['role'] === 'user')
                    {{-- user: right-aligned bubble, lifted off the page --}}
                    <div class="chat-message flex justify-end" wire:key="msg-{{ $i }}">
                        <div class="max-w-[85%] whitespace-pre-line rounded-[1.125rem] rounded-br-md border border-gray-200/70 bg-white px-4 py-3 text-[14px] leading-[1.7] text-gray-800 shadow-[0_2px_10px_-3px_rgba(15,23,42,0.14)] dark:border-slate-700/60 dark:bg-slate-800 dark:text-slate-200 dark:shadow-[0_2px_10px_-4px_rgba(0,0,0,0.7)]">{{ $m['body'] }}</div>
                    </div>
                @elseif ($m['role'] === 'error')
                    {{-- ours, not the model's: quota reached, network, outage --}}
                    <div class="chat-message flex gap-3.5" wire:key="msg-{{ $i }}">
                        <span class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/15 dark:ring-amber-400/25">
                            <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.5m0 3.5h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1 rounded-xl border-l-2 border-amber-400 bg-amber-50/70 px-3.5 py-2.5 text-[14px] leading-[1.7] text-amber-800 dark:border-amber-500/50 dark:bg-amber-500/10 dark:text-amber-200/90">{{ $m['body'] }}</div>
                    </div>
                @else
                    {{-- assistant: indigo accent rail and a faint tint, so a
                         reply reads as a distinct block rather than loose text --}}
                    <div class="chat-message flex gap-3.5" wire:key="msg-{{ $i }}">
                        <x-axokai-mark />
                        {{-- Markdown, not plain text. Rendered through
                             ChatMarkdown, which strips raw HTML and unsafe
                             links — this is the only unescaped output on the
                             page. Styling lives in .chat-md in app.css. --}}
                        <div class="chat-md min-w-0 flex-1 rounded-xl rounded-tl-md border-l-2 border-indigo-500/60 bg-indigo-50/40 px-4 py-3 text-[14px] leading-[1.75] text-gray-700 dark:border-indigo-400/50 dark:bg-indigo-500/[0.06] dark:text-slate-300">{!! $markdown->render($m['body']) !!}</div>
                    </div>
                @endif
            @empty
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <span class="chat-idle-mark flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-600">
                        <svg class="h-7 w-7 text-white" viewBox="0 -3.5 24 24" fill="currentColor">
                            <path d="M12 2.5c.3 3.4 2.6 5.7 6 6-3.4.3-5.7 2.6-6 6-.3-3.4-2.6-5.7-6-6 3.4-.3 5.7-2.6 6-6Z"/>
                        </svg>
                    </span>
                    <p class="mt-5 text-sm font-semibold text-gray-800 dark:text-slate-200">Ask Clarix anything about your work</p>
                    <p class="mt-1.5 max-w-sm text-[13px] leading-relaxed text-gray-500 dark:text-slate-400">Stalled tasks, credit burn, who is free this week. Start typing below.</p>
                </div>
            @endforelse

            {{-- Typing indicator, and the trigger for the call itself: send()
                 only appends the user's message and flips $pending, so the
                 thread paints first and the request goes out from here. Alpine
                 runs x-init when Livewire morphs this block in. --}}
            @if ($pending)
                <div class="chat-message flex gap-3.5" wire:key="pending" x-init="$wire.reply()">
                    <x-axokai-mark />
                    <div class="flex min-w-0 items-center gap-1.5 rounded-xl rounded-tl-md border-l-2 border-indigo-500/60 bg-indigo-50/40 px-4 py-3.5 dark:border-indigo-400/50 dark:bg-indigo-500/[0.06]"
                        role="status" aria-label="Clarix AI is replying">
                        <span class="chat-dot h-2 w-2 rounded-full bg-indigo-500/70 dark:bg-indigo-400/70"></span>
                        <span class="chat-dot h-2 w-2 rounded-full bg-indigo-500/70 dark:bg-indigo-400/70"></span>
                        <span class="chat-dot h-2 w-2 rounded-full bg-indigo-500/70 dark:bg-indigo-400/70"></span>
                        <span class="sr-only">Clarix AI is replying…</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- =================== composer ===================
         Alpine owns the draft so the send button enables without a round trip
         per keystroke; the value is handed to Livewire on submit. --}}
    @php
        // Out of messages, or one is already in flight: either way the
        // composer is inert until something changes.
        $locked = $remaining < 1 || $pending;
    @endphp

    <div class="flex-shrink-0 border-t border-gray-100 px-4 py-3 dark:border-slate-800/60 sm:px-6">
        <form x-data="{ text: '' }"
            @submit.prevent="if (text.trim() && ! @js($locked)) { $wire.send(text); text = '' }"
            class="mx-auto max-w-3xl">
            {{-- The indigo glow is a wide, low-opacity ring rather than a
                 border colour change, so the bar lifts on focus instead of
                 just outlining. --}}
            <div class="flex items-end gap-2 rounded-2xl border p-2 shadow-sm transition-all duration-200 focus-within:border-indigo-400 focus-within:shadow-[0_0_0_4px_rgba(99,102,241,0.12),0_4px_16px_-6px_rgba(79,70,229,0.35)] {{ $remaining < 1 ? 'border-gray-200 bg-gray-50 dark:border-slate-800 dark:bg-slate-800/30' : 'border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-800/60' }}">

                <button type="button" title="Attach a file" @disabled($locked)
                    class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-gray-400 transition-all duration-150 enabled:hover:bg-gray-100 enabled:hover:text-gray-600 enabled:active:scale-90 disabled:cursor-not-allowed disabled:opacity-50 dark:enabled:hover:bg-slate-700/60 dark:enabled:hover:text-slate-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                    </svg>
                    <span class="sr-only">Attach a file</span>
                </button>

                <textarea x-model="text" rows="1" @disabled($locked)
                    placeholder="{{ $remaining < 1 ? 'Daily limit reached — resets tomorrow' : ($pending ? 'Clarix AI is replying…' : 'Message Clarix AI') }}"
                    @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); if (text.trim() && ! @js($locked)) { $wire.send(text); text = ''; $el.style.height = 'auto' } }"
                    class="max-h-40 min-h-[36px] flex-1 resize-none border-0 bg-transparent py-2 text-[14px] leading-relaxed text-gray-800 placeholder-gray-400 focus:ring-0 disabled:cursor-not-allowed dark:text-slate-200 dark:placeholder-slate-500"></textarea>

                <button type="submit" :disabled="!text.trim() || @js($locked)"
                    class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg transition-all duration-150"
                    :class="(text.trim() && ! @js($locked))
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 hover:bg-indigo-700 hover:scale-105 hover:shadow-lg hover:shadow-indigo-600/40 active:scale-95'
                        : 'bg-gray-100 text-gray-300 cursor-not-allowed dark:bg-slate-800 dark:text-slate-600'">
                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M5 12l7-7 7 7"/>
                    </svg>
                    <span class="sr-only">Send message</span>
                </button>
            </div>

            <p class="mt-2 text-center text-[11px] {{ $remaining < 1 ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-slate-500' }}">
                @if ($remaining < 1)
                    Daily limit of {{ $limit }} messages reached. This resets tomorrow.
                @else
                    {{ $remaining }} {{ Str::plural('message', $remaining) }} remaining today · Clarix AI can make mistakes.
                @endif
            </p>
        </form>
    </div>
</div>
