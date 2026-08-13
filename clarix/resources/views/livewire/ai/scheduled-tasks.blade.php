{{--
    Scheduled Tasks & Automations.

    Standard app theme, unlike the AI Overview page next door: this one follows
    the light/dark tokens the rest of the backend uses. The exception is the
    flow graph inside each card, which is deliberately dark in both themes —
    near-black nodes with an indigo glow on a dotted canvas — so the chain
    reads as a diagram rather than as more page furniture.

    Geometry note for the flow row: it is items-start, and the connectors carry
    mt-5 so they meet the middle of a h-10 node. Change the node size and the
    connectors have to move with it.

    Everything interactive is disabled. The create button is wrapped in a span
    because a disabled <button> emits no mouse events, so the tooltip has to
    hang off a parent that can still be hovered.

    The automation cards render at full opacity and full colour, as they would
    if they were live; only the status pill and the switch on the bottom row
    carry the muted coming-soon treatment. The cards stay cursor-not-allowed
    and select-none, because nothing on them responds to a click.
--}}
<div>

    {{-- ========================== header ========================== --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-2xl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Scheduled Tasks &amp; Automations</h1>
            <p class="mt-1 text-sm leading-relaxed text-gray-500 dark:text-slate-400">
                Chain triggers to actions. AXOKAI watches your workflow and moves data, files, and updates automatically, no manual steps.
            </p>
        </div>

        <span class="group relative flex-shrink-0 self-start" title="Coming soon">
            <button type="button" disabled aria-describedby="new-automation-hint"
                class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-400 dark:bg-slate-800 dark:text-slate-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                New Automation
            </button>

            <span id="new-automation-hint" role="tooltip"
                class="pointer-events-none absolute right-0 top-full z-20 mt-1.5 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-[11px] font-semibold text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 dark:bg-slate-700">
                Coming soon
            </span>
        </span>
    </div>

    {{-- ======================== empty state ======================== --}}
    <div class="rounded-xl border border-gray-200 bg-white px-6 py-12 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-500/10">
            <svg class="h-7 w-7 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                {{-- calendar with a sparkle in the corner --}}
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h11M13.5 21H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v4.5"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 14l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9.9-2.6z"/>
            </svg>
        </span>

        <h2 class="mt-5 text-base font-bold text-gray-900 dark:text-slate-100">No automations yet</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-gray-500 dark:text-slate-400">
            Once available, you'll be able to chain triggers to actions and let AXOKAI run them for you here.
        </p>

        <span class="mt-5 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-800 dark:text-slate-400">
            Coming soon
        </span>
    </div>

    {{-- ===================== automation previews ===================== --}}
    <div class="mt-8">
        <div class="mb-3.5 flex flex-wrap items-baseline gap-x-2 gap-y-1">
            <h2 class="text-base font-bold text-gray-900 dark:text-slate-100">Automation Workflows</h2>
            <p class="text-[12.5px] text-gray-500 dark:text-slate-400">Existing automations</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($automations as $automation)
                <article class="flex cursor-not-allowed select-none flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">

                    {{-- trigger --}}
                    <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-[10.5px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $tints[$automation['kind']] }}">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $automation['trigger_icon'] }}"/>
                        </svg>
                        Trigger: {{ $automation['trigger'] }}
                    </span>

                    {{-- flow graph. Dark in both themes on purpose; the dotted
                         canvas is an inline gradient because Tailwind arbitrary
                         values cannot carry the commas. --}}
                    <div class="mt-4 rounded-xl border border-white/[0.06] bg-[#12121a] px-4 py-4"
                        style="background-image: radial-gradient(circle, rgba(129,140,248,.16) 1px, transparent 1px); background-size: 13px 13px;">
                        <div class="flex items-start justify-between">

                            {{-- trigger node --}}
                            <div class="flex w-14 flex-col items-center gap-1.5">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1d1d28] text-indigo-300 ring-1 ring-indigo-400/30 shadow-[0_0_18px_-5px_rgba(99,102,241,0.95)]">
                                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $automation['trigger_icon'] }}"/>
                                    </svg>
                                </span>
                                <span class="text-[9px] font-semibold uppercase tracking-widest text-slate-500">Trigger</span>
                            </div>

                            {{-- connector --}}
                            <span class="relative mx-1 mt-5 h-px flex-1">
                                <span class="absolute inset-x-0 top-0 border-t border-dashed border-indigo-400/35"></span>
                                <svg class="absolute -top-[5px] right-0 h-2.5 w-2.5 text-indigo-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>

                            {{-- integration node(s), stacked when there are two --}}
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="flex items-center -space-x-2.5">
                                    @foreach ($automation['integrations'] as $integration)
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1d1d28] ring-1 ring-white/15 shadow-[0_0_18px_-5px_rgba(99,102,241,0.8)]">
                                            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="{{ $integration['ink'] }}" aria-hidden="true">
                                                <path d="{{ $integration['logo'] }}"/>
                                            </svg>
                                        </span>
                                    @endforeach
                                </span>
                                <span class="text-[9px] font-semibold uppercase tracking-widest text-slate-500">Via</span>
                            </div>

                            {{-- connector --}}
                            <span class="relative mx-1 mt-5 h-px flex-1">
                                <span class="absolute inset-x-0 top-0 border-t border-dashed border-indigo-400/35"></span>
                                <svg class="absolute -top-[5px] right-0 h-2.5 w-2.5 text-indigo-400/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>

                            {{-- output node --}}
                            <div class="flex w-14 flex-col items-center gap-1.5">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1d1d28] text-violet-300 ring-1 ring-violet-400/30 shadow-[0_0_18px_-5px_rgba(167,139,250,0.95)]">
                                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $automation['output_icon'] }}"/>
                                    </svg>
                                </span>
                                <span class="text-[9px] font-semibold uppercase tracking-widest text-slate-500">{{ $automation['output'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- what it does --}}
                    <h3 class="mt-4 text-sm font-bold text-gray-900 dark:text-slate-100">{{ $automation['title'] }}</h3>
                    <p class="mt-1.5 text-[12.5px] leading-relaxed text-gray-500 dark:text-slate-400">{{ $automation['description'] }}</p>

                    {{-- integrations in play --}}
                    <div class="mt-3.5 flex flex-wrap items-center gap-1.5">
                        @foreach ($automation['integrations'] as $integration)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-[10.5px] font-medium text-gray-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
                                <svg class="h-3 w-3 flex-shrink-0" viewBox="0 0 24 24" fill="{{ $integration['colour'] }}" aria-hidden="true">
                                    <path d="{{ $integration['logo'] }}"/>
                                </svg>
                                {{ $integration['name'] }}
                            </span>
                        @endforeach
                    </div>

                    {{-- status --}}
                    <div class="mt-4 flex items-center justify-between gap-2 border-t border-gray-100 pt-3.5 dark:border-slate-800/60">
                        {{-- The card is at full strength now, so these two
                             carry the muted coming-soon treatment themselves:
                             they are the only signal left that the automation
                             is not live. --}}
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide text-gray-400 opacity-70 dark:bg-slate-800 dark:text-slate-500">
                            Coming soon
                        </span>

                        {{-- Not a <button>: there is nothing to press, and a
                             disabled control here would only be a tab stop
                             that does nothing. --}}
                        <span class="flex h-5 w-9 flex-shrink-0 items-center rounded-full bg-gray-200 p-0.5 opacity-70 dark:bg-slate-800"
                            role="switch" aria-checked="false" aria-disabled="true" aria-label="Enable {{ $automation['title'] }}">
                            <span class="h-4 w-4 rounded-full bg-gray-50 shadow-sm dark:bg-slate-600"></span>
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</div>
