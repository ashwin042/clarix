{{--
    Four states, and only one of them is ever on screen: locked (the agency's
    plan does not include it), connected, a code waiting to be sent, or the
    opening pitch.

    wire:poll runs only while a code is outstanding — the card flips to
    "Connected" by itself when the bot completes the link, and stops polling the
    moment there is nothing to wait for.

    The copy says "Task Bot" throughout. The pipeline behind it has an internal
    name, and that name belongs in config keys, class names and headers, never
    here — same rule the AXOKAI card follows, enforced by a sweep in the tests.
--}}
<div @if ($code && ! $linked) wire:poll.5s @endif>

    @if ($refusal)
        <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-4 py-3">
            <p class="text-sm text-amber-800 dark:text-amber-300">{{ $refusal }}</p>
        </div>
    @endif

    @if (! $planAllows)

        <p class="text-sm text-gray-600 dark:text-slate-400">
            Link your Telegram account to the Task Bot to file tasks from your phone — name, code, deadline, priority, credit and a file.
        </p>
        <p class="mt-2 text-xs font-medium text-gray-500 dark:text-slate-500">
            Not included in your plan. Upgrade to Pro to unlock the Task Bot.
        </p>

    @elseif ($linked)

        <div class="flex flex-col gap-3">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Connected</p>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                    Linked {{ $linkedAt?->diffForHumans() }}. The Task Bot files your submissions under your name.
                </p>
            </div>

            <button
                type="button"
                wire:click="disconnect"
                wire:confirm="Disconnect the Task Bot? It will stop accepting tasks from you until you link again."
                class="self-start shrink-0 px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10"
            >
                Disconnect
            </button>
        </div>

    @elseif ($code)

        <p class="text-sm text-gray-600 dark:text-slate-400">
            Send this code to the Task Bot on Telegram. It works once, and lapses in {{ $ttlMinutes }} minutes.
        </p>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <code class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-slate-800 font-mono text-base tracking-[0.2em] text-gray-900 dark:text-slate-100">{{ $code }}</code>

            <a
                href="https://t.me/{{ $botUsername }}?start={{ $code }}"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3 py-2 text-xs font-semibold rounded-lg bg-violet-600 text-white hover:opacity-90"
            >
                Open Telegram
            </a>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">
            Send it in a direct message to the bot — never in a group, where everyone could read it.
        </p>

        <button
            type="button"
            wire:click="generate"
            class="mt-3 text-xs font-medium text-gray-500 dark:text-slate-400 hover:underline"
        >
            Generate a new code
        </button>

    @else

        <p class="text-sm text-gray-600 dark:text-slate-400">
            Link your Telegram account to the Task Bot to file tasks from your phone — name, code, deadline, priority, credit and a file.
        </p>

        <button
            type="button"
            wire:click="generate"
            class="mt-3 px-3 py-2 text-xs font-semibold rounded-lg bg-violet-600 text-white hover:opacity-90"
        >
            Generate Task Bot code
        </button>

    @endif

</div>
