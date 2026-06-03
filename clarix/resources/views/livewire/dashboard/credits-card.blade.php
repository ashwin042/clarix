<div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5">
    <div class="flex items-start justify-between mb-4">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credits Earned</p>
            <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($credits, 2) }}</p>
            <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">
                @if($period === 'today') Today
                @elseif($period === 'month') This month
                @else All time
                @endif
            </p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
        </div>
    </div>

    <div class="bg-gray-100 dark:bg-slate-800 rounded-lg p-0.5 flex gap-0.5">
        <button wire:click="setPeriod('today')"
            class="flex-1 text-xs font-medium py-1.5 rounded-md transition-colors {{ $period === 'today' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' }}">
            Today
        </button>
        <button wire:click="setPeriod('month')"
            class="flex-1 text-xs font-medium py-1.5 rounded-md transition-colors {{ $period === 'month' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' }}">
            This Month
        </button>
        <button wire:click="setPeriod('total')"
            class="flex-1 text-xs font-medium py-1.5 rounded-md transition-colors {{ $period === 'total' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' }}">
            Total
        </button>
    </div>
</div>
