<div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Attendance</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ now()->format('l, j M Y') }}</p>
        </div>

        @if($today)
            @php
                $badge = match($today->status) {
                    'present'  => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                    'absent'   => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
                    'half_day' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                    'on_leave' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
                    default    => 'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-300',
                };
            @endphp
            <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-medium {{ $badge }}">
                {{ \App\Models\Attendance::STATUSES[$today->status] ?? $today->status }}
            </span>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
        <div>
            <p class="text-xs text-gray-400 dark:text-slate-500">In</p>
            <p class="font-medium text-gray-900 dark:text-slate-100">{{ $today?->clock_in?->format('H:i') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 dark:text-slate-500">Out</p>
            <p class="font-medium text-gray-900 dark:text-slate-100">{{ $today?->clock_out?->format('H:i') ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 dark:text-slate-500">Worked</p>
            <p class="font-medium text-gray-900 dark:text-slate-100">{{ $today?->workedForHumans() ?? '—' }}</p>
        </div>
    </div>

    @error('attendance')
        <p class="mt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror

    <div class="mt-4 flex items-center gap-2">
        @if($canClockIn)
            <button type="button" wire:click="clockIn" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors disabled:opacity-60">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Clock in
            </button>
        @elseif($canClockOut)
            <button type="button" wire:click="clockOut" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-medium transition-colors disabled:opacity-60">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Clock out
            </button>
        @else
            <p class="text-sm text-gray-500 dark:text-slate-400">Day complete. Thanks!</p>
        @endif

        @if($compact)
            <a href="{{ route('attendance.index') }}"
                class="ml-auto text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                View attendance
            </a>
        @endif
    </div>
</div>
