{{--
    Clarix AI Overview.

    Standard Clarix card language throughout — white on light, slate-900 on
    dark, gray-200/slate-800 borders, rounded-xl — the same as Calendar and
    Chatbot. What makes this page feel like the AI section is elevation and
    accent colour, not a different surface:

    - Cards sit on a heavier shadow than the app's default shadow-sm: a wider
      blur at a low opacity, so they lift off the page without looking heavy.
    - The two key cards (the AXOKAI banner and the model callout) carry an
      indigo-tinted shadow by default; the stat cards pick one up on hover.
    - Icon badges stay fully coloured — the amber-to-indigo bolt, the indigo
      sparkles, the per-category model badges. They carry the AI identity.

    Shadows are written as arbitrary values because the tinted ones are not in
    the default scale. In dark mode a coloured shadow reads as haze rather than
    depth, so the dark variants fall back to a plain black lift.

    Radii: rounded-xl for cards and rounded-lg inside them, matching the rest
    of the backend.
--}}
<div class="mx-auto max-w-6xl">

    {{-- ============ banner + usage (one card, two rows) ============ --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_10px_32px_-12px_rgba(79,70,229,0.30)] dark:border-slate-800 dark:bg-slate-900 dark:shadow-[0_10px_30px_-14px_rgba(0,0,0,0.75)]">

        {{-- who this is --}}
        <div class="flex items-start gap-5 p-6 sm:p-7">
            <span class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 via-orange-500 to-indigo-600 shadow-lg shadow-indigo-500/25">
                <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M13.2 2 4.6 13.6a.6.6 0 0 0 .48.96h4.4l-1.1 6.6a.6.6 0 0 0 1.06.46l8.6-11.6a.6.6 0 0 0-.48-.96h-4.4l1.1-6.6a.6.6 0 0 0-1.06-.46Z"/>
                </svg>
            </span>

            <div class="min-w-0 pt-0.5">
                <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-slate-100">Clarix, powered by AXOKAI</h2>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-gray-500 dark:text-slate-400">
                    Fast, automated project intelligence built into your workflow. No setup, just results.
                </p>
            </div>
        </div>

        {{-- allowance --}}
        <div class="flex flex-col gap-8 border-t border-gray-100 p-6 dark:border-slate-800/60 sm:p-7 lg:flex-row lg:items-center lg:justify-between">

            <div class="flex items-center gap-6">
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Messages Remaining</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">{{ $messageLimit }} per day · resets at midnight</p>
                    <p class="mt-3 text-5xl font-bold leading-none tracking-tight {{ $messagesRemaining < 1 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-slate-100' }}">{{ number_format($messagesRemaining) }}</p>
                </div>

                {{-- Today's allowance as a segment per message, filled for what
                     is left. A real bar beats a sparkline here: there is no
                     history to trend, only a quota being spent down. --}}
                <div class="hidden self-end rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/40 sm:block">
                    <div class="flex items-end gap-[3px]" aria-hidden="true">
                        @for ($i = 0; $i < $messageLimit; $i++)
                            <span class="h-6 w-1.5 rounded-sm {{ $i < $messagesRemaining ? 'bg-indigo-500' : 'bg-gray-200 dark:bg-slate-700' }}"></span>
                        @endfor
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-gray-400 dark:text-slate-500">
                        {{ $messageLimit - $messagesRemaining }} of {{ $messageLimit }} used today
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between lg:max-w-md lg:flex-1">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">Unlock higher limits</p>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-gray-500 dark:text-slate-400">
                        Upgrade your plan for more messages, faster automation, and priority AI access.
                    </p>
                </div>

                <a href="/home#pricing"
                    class="inline-flex flex-shrink-0 items-center gap-2 self-start rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/25 transition-colors hover:bg-indigo-700 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-6 6m6-6l6 6"/>
                    </svg>
                    Upgrade Plan
                </a>
            </div>
        </div>
    </div>

    {{-- ==================== the three counters ==================== --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stats as $stat)
            <a href="{{ $stat['href'] }}"
                class="group relative rounded-xl border border-gray-200 bg-white p-6 shadow-[0_6px_22px_-10px_rgba(15,23,42,0.20)] transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-[0_14px_34px_-12px_rgba(79,70,229,0.38)] dark:border-slate-800 dark:bg-slate-900 dark:shadow-[0_8px_24px_-14px_rgba(0,0,0,0.8)] dark:hover:border-indigo-500/40">

                <svg class="absolute right-5 top-5 h-4 w-4 text-gray-300 transition-all duration-200 group-hover:translate-x-0.5 group-hover:text-indigo-500 dark:text-slate-600 dark:group-hover:text-indigo-400"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>

                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-inset ring-indigo-600/10 transition-shadow duration-200 group-hover:shadow-[0_0_20px_-4px_rgba(99,102,241,0.55)] dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-400/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/>
                    </svg>
                </span>

                <p class="mt-6 text-[13px] font-medium text-gray-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                <p class="mt-2 text-[44px] font-bold leading-none tracking-tight text-gray-900 dark:text-slate-100">{{ $stat['value'] }}</p>
                <p class="mt-3 text-[13px] leading-snug text-gray-400 dark:text-slate-500">{{ $stat['blurb'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- ======================= the models ======================= --}}
    <div class="mt-12">
        <div class="flex items-center gap-1.5">
            <h2 class="text-base font-bold text-gray-900 dark:text-slate-100">The Models</h2>
            <svg class="h-4 w-4 text-gray-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </div>

        {{-- accent banner: a soft indigo-to-violet tint, the same accent family
             the app uses for its indigo-50 highlights, just carried across. --}}
        <div class="mt-4 flex flex-col gap-4 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-violet-50 p-5 shadow-[0_8px_26px_-12px_rgba(79,70,229,0.32)] dark:border-indigo-500/25 dark:from-indigo-500/10 dark:to-violet-500/10 dark:shadow-[0_8px_24px_-14px_rgba(0,0,0,0.8)] sm:flex-row sm:items-center sm:justify-between sm:gap-6">
            <div class="flex items-start gap-4">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-white text-indigo-600 shadow-sm ring-1 ring-inset ring-indigo-600/10 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-400/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.7 4.8L18.5 9.5 13.7 11.2 12 16l-1.7-4.8L5.5 9.5l4.8-1.7L12 3zM18 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8L18 15z"/>
                    </svg>
                </span>
                <p class="text-sm leading-relaxed text-indigo-900 dark:text-indigo-100/90">
                    AXOKAI is the engine behind every model here. Purpose-built for project automation.
                </p>
            </div>

            <a href="https://axokai.codesnextdoor.com/" target="_blank" rel="noopener noreferrer"
                class="group inline-flex flex-shrink-0 items-center gap-1.5 self-start rounded-lg text-sm font-semibold text-indigo-600 transition-colors hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-white sm:self-auto">
                Learn More
                <svg class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        {{-- grouped list. items-start, or a two-model box stretches to match
             the tallest one in the row and leaves dead space below it. --}}
        <div class="mt-5 grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modelGroups as $group => $models)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-[0_6px_22px_-10px_rgba(15,23,42,0.20)] dark:border-slate-800 dark:bg-slate-900 dark:shadow-[0_8px_24px_-14px_rgba(0,0,0,0.8)]">
                    <p class="text-[10.5px] font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">{{ $group }}</p>

                    <ul class="mt-4 space-y-2.5">
                        @foreach ($models as $model)
                            <li class="flex items-center gap-3.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ $groupStyles[$group]['tint'] }}">
                                    <svg class="h-[17px] w-[17px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $groupStyles[$group]['icon'] }}"/>
                                    </svg>
                                </span>
                                <span class="truncate text-sm font-medium text-gray-900 dark:text-slate-100">{{ $model }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-gray-500 dark:text-slate-500">
            New models are added regularly. Full details available in Model Settings.
        </p>
    </div>
</div>
