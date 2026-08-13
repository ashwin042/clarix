@php
    use App\Livewire\AI\Calendar;

    // Chip palette, keyed by the tone the component works out.
    $chipTone = [
        'overdue'   => 'bg-rose-50 text-rose-700 ring-rose-200 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/25 dark:hover:bg-rose-500/20',
        'high'      => 'bg-amber-50 text-amber-800 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/25 dark:hover:bg-amber-500/20',
        'normal'    => 'bg-indigo-50 text-indigo-700 ring-indigo-200 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/25 dark:hover:bg-indigo-500/20',
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/25 dark:hover:bg-emerald-500/20',
        'cancelled' => 'bg-gray-100 text-gray-500 ring-gray-200 line-through hover:bg-gray-200 dark:bg-slate-800 dark:text-slate-500 dark:ring-slate-700 dark:hover:bg-slate-700',
    ];

    $statusTone = [
        'pending'     => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'in_progress' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
        'completed'   => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'cancelled'   => 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-400',
    ];

    $priorityTone = [
        'high'   => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'medium' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'low'    => 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-400',
    ];
@endphp

<div class="flex h-full gap-4">

    {{-- ==================== calendar ==================== --}}
    <section class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900">

        {{-- toolbar --}}
        <header class="flex h-14 flex-shrink-0 flex-wrap items-center gap-2 border-b border-gray-100 px-3 dark:border-slate-800/60 sm:px-4">

            <div class="flex items-center gap-0.5">
                <button type="button" wire:click="step(-1)" title="Previous"
                    class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800/60 dark:hover:text-slate-200">
                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span class="sr-only">Previous {{ $view }}</span>
                </button>
                <button type="button" wire:click="step(1)" title="Next"
                    class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800/60 dark:hover:text-slate-200">
                    <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                    <span class="sr-only">Next {{ $view }}</span>
                </button>
            </div>

            <h2 class="ml-1 min-w-0 truncate text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $label }}</h2>

            <span class="hidden text-[12px] text-gray-400 dark:text-slate-500 sm:inline">
                {{ $total }} {{ Str::plural('task', $total) }}
            </span>

            <div class="ml-auto flex items-center gap-2">
                <button type="button" wire:click="goToday"
                    class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-[12.5px] font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800/60">
                    Today
                </button>

                {{-- view switcher --}}
                <div class="flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-slate-800">
                    @foreach (['week' => 'Week', 'month' => 'Month'] as $key => $text)
                        <button type="button" wire:click="setView('{{ $key }}')"
                            class="rounded-md px-2.5 py-1 text-[12.5px] font-medium transition-colors {{ $view === $key ? 'bg-white text-gray-900 shadow-sm dark:bg-slate-700 dark:text-slate-100' : 'text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                            {{ $text }}
                        </button>
                    @endforeach
                </div>
            </div>
        </header>

        {{-- weekday header --}}
        <div class="grid flex-shrink-0 grid-cols-7 border-b border-gray-100 dark:border-slate-800/60">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                <div class="px-2 py-2 text-center text-[10.5px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">{{ $dow }}</div>
            @endforeach
        </div>

        {{-- grid. Week gets one tall row of day columns, month gets six short
             rows; the cells themselves are identical in both. --}}
        <div class="min-h-0 flex-1 overflow-y-auto">
            {{-- auto-rows-fr, not a fixed grid-rows-N: a month spans five or
                 six weeks depending on where it starts, and hardcoding six
                 leaves a dead row on the short ones. --}}
            <div class="grid h-full auto-rows-fr grid-cols-7">
                @foreach ($days as $day)
                    <div class="min-h-0 border-b border-r border-gray-100 p-1.5 last:border-r-0 dark:border-slate-800/60 {{ $day['muted'] ? 'bg-gray-50/60 dark:bg-slate-950/30' : '' }} {{ $loop->iteration % 7 === 0 ? 'border-r-0' : '' }}">

                        <div class="mb-1 flex justify-center">
                            <span class="flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-[11.5px] font-semibold
                                {{ $day['today']
                                    ? 'bg-indigo-600 text-white'
                                    : ($day['muted'] ? 'text-gray-300 dark:text-slate-700' : 'text-gray-500 dark:text-slate-400') }}">
                                {{ $day['date']->day }}
                            </span>
                        </div>

                        <div class="space-y-1 {{ $view === 'week' ? 'overflow-y-auto' : '' }}">
                            @foreach ($day['tasks']->take($view === 'month' ? 3 : 99) as $task)
                                @php $tone = Calendar::tone($task); @endphp
                                <button type="button" wire:click="select({{ $task->id }})"
                                    title="{{ $task->title }}"
                                    class="block w-full truncate rounded-md px-1.5 py-1 text-left text-[11px] font-medium ring-1 transition-colors {{ $chipTone[$tone] }} {{ $selected?->id === $task->id ? 'ring-2 ring-offset-1 dark:ring-offset-slate-900' : '' }}">
                                    {{ $task->title }}
                                </button>
                            @endforeach

                            @if ($view === 'month' && $day['tasks']->count() > 3)
                                <span class="block px-1.5 text-[10.5px] font-medium text-gray-400 dark:text-slate-500">
                                    +{{ $day['tasks']->count() - 3 }} more
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- legend --}}
        <div class="flex flex-shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 px-4 py-2 dark:border-slate-800/60">
            @foreach (['overdue' => 'Overdue', 'high' => 'High priority', 'normal' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $text)
                <span class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-slate-400">
                    <span class="h-2 w-2 rounded-full ring-1 {{ $chipTone[$key] }}"></span>{{ $text }}
                </span>
            @endforeach
        </div>
    </section>

    {{-- ================== detail sidebar ================== --}}
    <aside class="hidden w-80 flex-shrink-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900 xl:flex">

        @if ($selected)
            <div class="flex h-14 flex-shrink-0 items-center justify-between gap-2 border-b border-gray-100 px-4 dark:border-slate-800/60">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Task detail</span>
                <button type="button" wire:click="clearSelection" title="Close"
                    class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800/50 dark:hover:text-slate-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span class="sr-only">Close</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <p class="text-[11px] font-medium text-gray-400 dark:text-slate-500">{{ $selected->task_code }}</p>
                <h3 class="mt-1 text-[15px] font-semibold leading-snug text-gray-900 dark:text-slate-100">{{ $selected->title }}</h3>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold {{ $statusTone[$selected->status] ?? $statusTone['pending'] }}">
                        {{ Str::headline($selected->status) }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold {{ $priorityTone[$selected->priority] ?? $priorityTone['low'] }}">
                        {{ ucfirst($selected->priority) }} priority
                    </span>
                </div>

                <dl class="mt-5 space-y-3.5 border-t border-gray-100 pt-4 dark:border-slate-800/60">
                    @php
                        // Deadlines are date-only in the schema, so there is no
                        // time of day to show here.
                        $overdue = $selected->deadline->isBefore(now()->startOfDay())
                            && ! in_array($selected->status, ['completed', 'cancelled'], true);
                    @endphp

                    <div>
                        <dt class="text-[11px] font-medium text-gray-400 dark:text-slate-500">Due date</dt>
                        <dd class="mt-0.5 text-[13px] font-medium {{ $overdue ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-slate-200' }}">
                            {{ $selected->deadline->format('D, j M Y') }}
                            @if ($overdue)
                                <span class="text-[11px] font-semibold">· overdue</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-medium text-gray-400 dark:text-slate-500">Unit / client</dt>
                        <dd class="mt-0.5 text-[13px] text-gray-800 dark:text-slate-200">{{ $selected->unit?->name ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-medium text-gray-400 dark:text-slate-500">Assigned writers</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($selected->writers as $writer)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 py-0.5 pl-0.5 pr-2 text-[11.5px] text-gray-700 dark:bg-slate-800 dark:text-slate-300">
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-indigo-100 text-[8px] font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                                        {{ strtoupper(substr($writer->name, 0, 1)) }}
                                    </span>
                                    {{ $writer->name }}
                                </span>
                            @empty
                                <span class="text-[13px] text-gray-400 dark:text-slate-500">Nobody assigned yet</span>
                            @endforelse
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-medium text-gray-400 dark:text-slate-500">Credits allocated</dt>
                        <dd class="mt-0.5 text-[13px] text-gray-800 dark:text-slate-200">
                            {{ $selected->credit_amount !== null ? rtrim(rtrim(number_format($selected->credit_amount, 2), '0'), '.') : '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-medium text-gray-400 dark:text-slate-500">Attached files</dt>
                        <dd class="mt-0.5 text-[13px] text-gray-800 dark:text-slate-200">
                            @if ($selected->files_count > 0)
                                <a href="{{ route('tasks.show', $selected) }}" class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                    {{ $selected->files_count }} {{ Str::plural('file', $selected->files_count) }}
                                </a>
                            @else
                                <span class="text-gray-400 dark:text-slate-500">None</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="flex-shrink-0 border-t border-gray-100 p-3 dark:border-slate-800/60">
                <a href="{{ route('tasks.show', $selected) }}"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-indigo-700">
                    View full task
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        @else
            {{-- empty state --}}
            <div class="flex flex-1 flex-col items-center justify-center px-6 text-center">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 dark:bg-slate-800">
                    <svg class="h-5 w-5 text-gray-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v-.5a2.5 2.5 0 00-5 0V5M9 11h6m-6 4h4M6 21h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <p class="mt-3.5 text-[13px] font-semibold text-gray-700 dark:text-slate-300">Select a task to see details</p>
                <p class="mt-1 text-[12px] leading-relaxed text-gray-400 dark:text-slate-500">Click any chip on the calendar to open it here.</p>
            </div>
        @endif
    </aside>
</div>
