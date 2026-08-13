<div class="max-w-2xl">
    <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10">
            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </span>

        <h1 class="mt-5 text-xl font-bold text-gray-900 dark:text-slate-100">{{ $feature }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-slate-400">{{ $blurb }}</p>

        <div class="mt-6 flex items-center gap-2 border-t border-gray-100 pt-5 dark:border-slate-800/60">
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-slate-800 dark:text-slate-400">Coming soon</span>
            <a href="{{ route('ai.chatbot') }}" class="text-[13px] font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">Open Chatbot instead</a>
        </div>
    </div>
</div>
