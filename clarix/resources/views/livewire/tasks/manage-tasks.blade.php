<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Tasks</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Manage and track all project tasks</p>
        </div>
        {{-- Drawn off the permission the button actually needs. As a
             not-a-writer test it offered the modal to any other role whether
             or not the agency had granted them tasks.create — save() refused,
             but only after the form had been filled in. --}}
        @if(auth()->user()->hasPermission('tasks.create'))
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Task
        </button>
        @endif
    </div>

    {{-- View switcher --}}
    <div class="flex items-center gap-1 p-1 mb-5 w-fit bg-gray-100 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg">
        @foreach(['kanban' => 'Kanban', 'table' => 'Table'] as $view => $viewLabel)
            <button type="button" wire:click="setView('{{ $view }}')"
                @class([
                    'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
                    'bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 shadow-sm' => $activeView === $view,
                    'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' => $activeView !== $view,
                ])>
                {{ $viewLabel }}
            </button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="flex flex-col md:flex-row md:flex-wrap md:items-center gap-3 mb-5">
        <div class="relative w-full md:w-auto md:flex-1 md:min-w-[200px] md:max-w-xs">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search tasks..."
                class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <select wire:model.live="filterStatus" class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            {{-- Built from the constant so this list cannot fall behind it. --}}
            <option value="">All statuses</option>
            @foreach(\App\Models\Task::STATUSES as $value)
                <option value="{{ $value }}">{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterPriority" class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All priorities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
        </select>
        <select wire:model.live="filterTaskType" class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Types</option>
            <option value="tech">Tech</option>
            <option value="content">Content</option>
            <option value="accounts">Accounts</option>
            <option value="maths">Maths</option>
            <option value="nursing">Nursing</option>
            <option value="science">Science</option>
            <option value="civil">Civil</option>
            <option value="others">Others</option>
        </select>
        @if(auth()->user()->reachesEveryUnit())
        <select wire:model.live="filterUnit" class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All units</option>
            @foreach($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
        </select>
        @endif
    </div>

    @if($activeView === 'kanban')
    {{-- Kanban board. The columns are flex children with a min-width floor, so
         they share the available width evenly when it is wide enough for all
         four and fall back to horizontal scroll when it is not. Past xl the
         floor is dropped, which is what keeps a full desktop free of scroll.
         Below that the container snaps, so a swipe lands on a whole column. --}}
    <div data-kanban-board
        class="kanban-scroll flex gap-4 lg:gap-5 overflow-x-auto snap-x snap-mandatory xl:snap-none pb-3">
            @foreach($board as $status => $column)
                <div wire:key="kanban-col-{{ $status }}"
                    {{-- The height cap only applies from md up. Below that the filter bar
                         stacks into a tall column of its own, so a fixed viewport offset
                         would be wrong; letting the page scroll is the right mobile call. --}}
                    class="snap-start flex-1 min-w-[280px] xl:min-w-0 flex flex-col md:max-h-[calc(100vh-19rem)] bg-gray-50 dark:bg-slate-900/50 border border-gray-200 dark:border-slate-800 rounded-xl">

                    {{-- Column header --}}
                    @php
                        $dot = match($status) {
                            'pending'         => 'bg-gray-400 dark:bg-slate-500',
                            'on_hold'         => 'bg-orange-500',
                            'in_progress'     => 'bg-blue-500',
                            'sent_for_review' => 'bg-amber-500',
                            'completed'       => 'bg-green-500',
                            default           => 'bg-gray-400 dark:bg-slate-500',
                        };
                    @endphp
                    {{-- Anchored above the scroll area, so it stays put while the cards move. --}}
                    <div class="flex-shrink-0 flex items-center gap-2 px-4 py-3 border-b border-gray-200 dark:border-slate-800">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $dot }}"></span>
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-slate-300 truncate">{{ $column['label'] }}</h2>
                        <span class="ml-auto flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 dark:bg-slate-800 text-gray-600 dark:text-slate-400">{{ $column['total'] }}</span>
                    </div>

                    {{-- Cards. min-h-0 lets this flex child actually shrink, which is what
                         keeps the overflow on the column instead of pushing the page taller. --}}
                    <div
                        data-kanban-column
                        data-status="{{ $status }}"
                        class="kanban-scroll flex-1 min-h-0 p-3 space-y-3 overflow-y-auto"
                    >
                        @foreach($column['tasks'] as $task)
                            @php
                                // Reordering inside a column is a plain edit; moving between
                                // columns changes the status, which is the stricter right.
                                $mayReorder = auth()->user()->can('update', $task);
                                $mayChangeStatus = auth()->user()->can('updateStatus', $task);
                                $overdue = $task->deadline->isPast() && $task->status !== 'completed';
                                $cardWriters = $task->assignments->pluck('writer')->filter();
                            @endphp
                            <div
                                wire:key="kanban-card-{{ $task->id }}"
                                data-task-id="{{ $task->id }}"
                                data-draggable="{{ $mayReorder ? '1' : '0' }}"
                                data-can-change-status="{{ $mayChangeStatus ? '1' : '0' }}"
                                @class([
                                    'group bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg p-3 shadow-sm transition-shadow hover:shadow-md',
                                    'cursor-grab active:cursor-grabbing' => $mayReorder,
                                    'opacity-80' => $status === 'completed',
                                ])
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('tasks.show', $task) }}" wire:navigate
                                        class="text-sm font-bold text-gray-900 dark:text-slate-100 hover:text-indigo-600 transition-colors line-clamp-2">
                                        {{ $task->title }}
                                    </a>
                                    <span class="text-[10px] font-mono text-gray-400 dark:text-slate-500 flex-shrink-0 pt-0.5">{{ $task->task_code }}</span>
                                </div>

                                <p class="mt-1.5 text-xs text-gray-500 dark:text-slate-400 truncate">{{ $task->pm?->name ?? 'No PM' }}</p>

                                {{-- Attachment counters. Each is omitted at zero
                                     rather than shown as "0", so a card only
                                     carries the marks it has earned and an
                                     empty task looks exactly as it did before.
                                     Counts come from withCount() on the board
                                     query; the data- attributes are what the
                                     tests read, so changing an icon cannot
                                     break them. --}}
                                @if($task->regular_files_count || $task->completed_files_count || $task->notes_count)
                                    <div class="mt-2 flex items-center gap-3 text-gray-400 dark:text-slate-500">
                                        @if($task->regular_files_count)
                                            <span data-card-files="{{ $task->regular_files_count }}"
                                                title="{{ $task->regular_files_count }} {{ Str::plural('file', $task->regular_files_count) }}"
                                                class="inline-flex items-center gap-1 text-[11px]">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                {{ $task->regular_files_count }}
                                            </span>
                                        @endif
                                        @if($task->completed_files_count)
                                            <span data-card-completed-files="{{ $task->completed_files_count }}"
                                                title="{{ $task->completed_files_count }} completed {{ Str::plural('file', $task->completed_files_count) }}"
                                                class="inline-flex items-center gap-1 text-[11px] text-green-600 dark:text-green-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                {{ $task->completed_files_count }}
                                            </span>
                                        @endif
                                        @if($task->notes_count)
                                            <span data-card-notes="{{ $task->notes_count }}"
                                                title="{{ $task->notes_count }} {{ Str::plural('note', $task->notes_count) }}"
                                                class="inline-flex items-center gap-1 text-[11px]">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                                {{ $task->notes_count }}
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <span class="text-xs {{ $overdue ? 'text-red-600 font-medium' : 'text-gray-500 dark:text-slate-400' }}">
                                        {{ $task->deadline->format('M d, Y') }}
                                    </span>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400">
                                        {{ number_format($task->credit_amount, 2) }}
                                    </span>
                                </div>

                                @if($cardWriters->isNotEmpty())
                                    <div class="mt-3 pt-2.5 border-t border-gray-100 dark:border-slate-800/60 flex items-center -space-x-1.5">
                                        @foreach($cardWriters->take(4) as $writer)
                                            <span title="{{ $writer->name }}"
                                                class="w-6 h-6 rounded-full ring-2 ring-white dark:ring-slate-900 bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-semibold flex items-center justify-center">
                                                {{ Str::of($writer->name)->explode(' ')->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode('') }}
                                            </span>
                                        @endforeach
                                        @if($cardWriters->count() > 4)
                                            <span class="w-6 h-6 rounded-full ring-2 ring-white dark:ring-slate-900 bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 text-[10px] font-semibold flex items-center justify-center">
                                                +{{ $cardWriters->count() - 4 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if($column['tasks']->isEmpty())
                            <p class="py-8 text-center text-xs text-gray-400 dark:text-slate-500">No tasks</p>
                        @endif
                    </div>

                    @if($status === 'completed' && $column['total'] > $column['tasks']->count())
                        <div class="flex-shrink-0 px-4 py-2.5 border-t border-gray-200 dark:border-slate-800">
                            <a href="{{ route('tasks.completed') }}" wire:navigate
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition-colors">
                                Showing {{ $column['tasks']->count() }} of {{ $column['total'] }} — view all completed
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
    </div>
    @else
    {{-- Table --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-visible">
        @if($tasks->count())
            {{-- Mobile card fallback for the table below md --}}
            <div class="md:hidden divide-y divide-gray-100 dark:divide-slate-800/60">
                @foreach($tasks as $task)
                    @php
                        $sc = match($task->status) { 'pending' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400', 'on_hold' => 'bg-orange-100 text-orange-700', 'in_progress' => 'bg-blue-100 text-blue-700', 'sent_for_review' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-rose-100 text-rose-700', default => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400' };
                    @endphp
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-bold font-mono text-gray-900 dark:text-slate-100 hover:text-indigo-600 transition-colors break-all">{{ $task->task_code }}</a>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 {{ $sc }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">PM</dt>
                                <dd class="text-sm text-gray-600 dark:text-slate-400 mt-0.5">{{ $task->pm?->name ?? '-' }}</dd>
                            </div>
                            @if(auth()->user()->isAdmin())
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Supervisor</dt>
                                <dd class="text-sm text-gray-600 dark:text-slate-400 mt-0.5">{{ $task->assignedAdmin?->name ?? '-' }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Deadline</dt>
                                <dd class="text-sm mt-0.5 {{ $task->deadline->isPast() && $task->status !== 'completed' ? 'text-red-600 font-medium' : 'text-gray-600 dark:text-slate-400' }}">{{ $task->deadline->format('M d, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Credits</dt>
                                <dd class="text-sm font-medium text-gray-700 dark:text-slate-300 mt-0.5">{{ number_format($task->credit_amount, 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Files</dt>
                                <dd class="text-sm text-gray-600 dark:text-slate-400 mt-0.5">{{ $task->files->count() ?: '-' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Writers</dt>
                                <dd class="mt-1">
                                    @if(auth()->user()->isAdmin() && $task->assignments->count())
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($task->assignments as $assignment)
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $assignment->writer->name }}</span>
                                            @endforeach
                                        </div>
                                    @elseif(auth()->user()->isAdmin())
                                        <span class="text-sm text-gray-400 dark:text-slate-500">-</span>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-slate-400">{{ $task->assignments->count() }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="flex items-center gap-2 pt-1">
                            <a href="{{ route('tasks.show', $task) }}"
                                class="flex-1 text-center px-3 py-2 text-xs font-medium text-gray-600 dark:text-slate-400 border border-gray-200 dark:border-slate-800 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">View</a>
                            @if(!auth()->user()->isWriter())
                            <button wire:click="openEdit({{ $task->id }})"
                                class="flex-1 px-3 py-2 text-xs font-medium text-indigo-600 border border-indigo-100 dark:border-indigo-900/40 hover:bg-indigo-50 rounded-lg transition-colors">Edit</button>
                            @endif
                            @if(auth()->user()->isAdmin())
                            <button wire:click="openDeleteModal({{ $task->id }}, '{{ $task->task_code }} - {{ $task->title }}')"
                                class="flex-1 px-3 py-2 text-xs font-medium text-red-500 border border-red-100 dark:border-red-900/40 hover:bg-red-50 rounded-lg transition-colors">Delete</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Table (md and up) --}}
            <table class="hidden md:table min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                <thead class="bg-gray-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer select-none" wire:click="sortBy('title')">
                            <div class="flex items-center gap-1">
                                Task
                                @if($sortBy === 'title')
                                    <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">PM</th>
                        @if(auth()->user()->isAdmin())
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned Supervisor</th>
                        @endif
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer select-none" wire:click="sortBy('deadline')">
                            <div class="flex items-center gap-1">
                                Deadline
                                @if($sortBy === 'deadline')
                                    <svg class="w-3 h-3 {{ $sortDir === 'asc' ? '' : 'rotate-180' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credits</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Writers</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Files</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                    @foreach($tasks as $task)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3">
                                <a href="{{ route('tasks.show', $task) }}" class="text-sm font-bold font-mono text-gray-900 dark:text-slate-100 hover:text-indigo-600 transition-colors">{{ $task->task_code }}</a>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->pm?->name ?? '-' }}</td>
                            @if(auth()->user()->isAdmin())
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->assignedAdmin?->name ?? '-' }}</td>
                            @endif
                            <td class="px-5 py-3">
                                @php
                                    $sc = match($task->status) { 'pending' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400', 'on_hold' => 'bg-orange-100 text-orange-700', 'in_progress' => 'bg-blue-100 text-blue-700', 'sent_for_review' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-rose-100 text-rose-700', default => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400' };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm {{ $task->deadline->isPast() && $task->status !== 'completed' ? 'text-red-600 font-medium' : 'text-gray-600 dark:text-slate-400' }}">
                                {{ $task->deadline->format('M d, Y') }}
                            </td>
                            <td class="px-5 py-3 text-sm font-medium text-gray-700 dark:text-slate-300">{{ number_format($task->credit_amount, 2) }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">
                                @if(auth()->user()->isAdmin() && $task->assignments->count())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($task->assignments as $assignment)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $assignment->writer->name }}</span>
                                        @endforeach
                                    </div>
                                @elseif(auth()->user()->isAdmin())
                                    <span class="text-gray-400 dark:text-slate-500">-</span>
                                @else
                                    {{ $task->assignments->count() }}
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if($task->files->count())
                                    <div class="relative" x-data="{ open: false }">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            @click.outside="open = false"
                                            class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-colors text-xs font-medium"
                                            title="{{ $task->files->count() }} {{ $task->files->count() === 1 ? 'file' : 'files' }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            {{ $task->files->count() }}
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            style="background-color: white;"
                                            class="absolute left-0 top-8 z-50 w-64 rounded-xl border border-gray-200 dark:border-slate-700 shadow-xl dark:shadow-slate-900/60 dark:bg-slate-900"
                                        >
                                            <div class="px-3 py-2 border-b border-gray-100 dark:border-slate-800/60 rounded-t-xl bg-white dark:bg-slate-900">
                                                <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Files</p>
                                            </div>
                                            <ul class="divide-y divide-gray-100 dark:divide-slate-800/60 max-h-48 overflow-y-auto bg-white dark:bg-slate-900">
                                                @foreach($task->files as $file)
                                                <li class="flex items-center justify-between px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        <div class="min-w-0">
                                                            <p class="text-xs font-medium text-gray-800 dark:text-slate-200 truncate">{{ $file->original_name }}</p>
                                                            <p class="text-[10px] text-gray-400 dark:text-slate-500">{{ $file->file_size_formatted }}</p>
                                                        </div>
                                                    </div>
                                                    <a href="{{ route('tasks.files.download', [$task, $file]) }}"
                                                       class="ml-2 px-2 py-1 text-[10px] font-medium text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors shrink-0">
                                                        Download
                                                    </a>
                                                </li>
                                                @endforeach
                                            </ul>
                                            @can('uploadFiles', $task)
                                            <div class="px-3 py-2 border-t border-gray-100 dark:border-slate-800/60 rounded-b-xl bg-white dark:bg-slate-900">
                                                <button
                                                    type="button"
                                                    @click="open = false; $dispatch('open-upload-modal', { uploadUrl: '{{ route('tasks.files.store', $task) }}' })"
                                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-colors"
                                                >
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    Upload more files
                                                </button>
                                            </div>
                                            @endcan
                                        </div>
                                    </div>
                                @else
                                    @can('uploadFiles', $task)
                                    <button
                                        type="button"
                                        @click="$dispatch('open-upload-modal', { uploadUrl: '{{ route('tasks.files.store', $task) }}' })"
                                        title="Upload files"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800/50 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                    @else
                                    <span class="text-gray-400 dark:text-slate-500 text-sm">-</span>
                                    @endcan
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tasks.show', $task) }}"
                                        class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">View</a>
                                    @if(!auth()->user()->isWriter())
                                    <button wire:click="openEdit({{ $task->id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Edit</button>
                                    @endif
                                    @if(auth()->user()->isAdmin())
                                    <button wire:click="openDeleteModal({{ $task->id }}, '{{ $task->task_code }} - {{ $task->title }}')"
                                        class="px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 rounded-lg transition-colors">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($tasks->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $tasks->links() }}</div>
            @endif
        @else
            <div class="py-16 text-center dark:text-slate-400">
                <div class="w-12 h-12 mx-auto bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-slate-400">No tasks found.</p>
            </div>
        @endif
    </div>
    @endif

    {{-- Task Modal --}}
    <x-livewire-modal :title="$editingId ? 'Edit Task' : 'New Task'" maxWidth="xl">
        <form wire:submit="save" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Title</label>
                <input wire:model="title" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('title') border-red-400 @enderror"
                    placeholder="Task title">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Task Code</label>
                    <input wire:model="task_code" type="text"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('task_code') border-red-400 @enderror"
                        placeholder="e.g. CA_001">
                    @error('task_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                    @if(auth()->user()->reachesEveryUnit())
                        <select wire:model.live="unit_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('unit_id') border-red-400 @enderror">
                            <option value="">Select unit</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" value="{{ $units->first()?->name ?? '-' }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                    @endif
                    @error('unit_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- PM field --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Responsible PM</label>
                @if(auth()->user()->reachesEveryUnit())
                    @if($unit_id && $pmsForUnit->count())
                        <select wire:model="pm_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('pm_id') border-red-400 @enderror">
                            <option value="">Select PM</option>
                            @foreach($pmsForUnit as $pm)
                                <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                            @endforeach
                        </select>
                    @elseif($unit_id)
                        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2.5">No PMs found in this unit. Assign a PM to this unit first.</p>
                    @else
                        <input type="text" value="Select a unit first" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-400 dark:text-slate-500 cursor-not-allowed">
                    @endif
                @else
                    <div class="relative">
                        <input type="text" value="{{ auth()->user()->name }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">Automatically assigned to you</p>
                @endif
                @error('pm_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Assigned Supervisor --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Assigned Supervisor</label>
                <select wire:model="assigned_admin_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('assigned_admin_id') border-red-400 @enderror">
                    <option value="">Unassigned</option>
                    @foreach($adminUsers as $adminUser)
                        <option value="{{ $adminUser->id }}">{{ $adminUser->name }}</option>
                    @endforeach
                </select>
                @error('assigned_admin_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Task Type</label>
                <select wire:model="task_type"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('task_type') border-red-400 @enderror">
                    <option value="">— Select type —</option>
                    <option value="tech">Tech</option>
                    <option value="content">Content</option>
                    <option value="accounts">Accounts</option>
                    <option value="maths">Maths</option>
                    <option value="nursing">Nursing</option>
                    <option value="science">Science</option>
                    <option value="civil">Civil</option>
                    <option value="others">Others</option>
                </select>
                @error('task_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Important Notes</label>
                <textarea wire:model="important_notes" rows="3"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    placeholder="Optional instructions..."></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Priority</label>
                    <select wire:model="priority"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Status</label>
                    {{-- The permission, not the role. This asked isAdmin(),
                         which left a supervisor holding tasks.update_status
                         looking at a disabled box on the same task it could
                         drag between columns on the board behind this modal.
                         $maySetStatus is the same question save() asks, so the
                         field appears exactly when the value would be kept. --}}
                    @if($maySetStatus)
                        <select wire:model="status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach(\App\Models\Task::STATUSES as $value)
                                <option value="{{ $value }}">{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                            @endforeach
                        </select>
                    @else
                        {{-- The task's real state. This read "Pending" whatever
                             the task was, so a PM opening an in-progress task
                             was shown a status it was not in. --}}
                        <input type="text" value="{{ $lockedStatusLabel }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Deadline</label>
                    <input wire:model="deadline" type="date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('deadline') border-red-400 @enderror">
                    @error('deadline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Credit Amount</label>
                    <input wire:model="credit_amount" type="number" min="0" step="0.01" placeholder="e.g. 1.5"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('credit_amount') border-red-400 @enderror">
                    @error('credit_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if(!$editingId)
            <p class="text-xs text-gray-400 dark:text-slate-500 bg-gray-50 dark:bg-slate-950/50 border border-gray-200 dark:border-slate-800 rounded-lg px-3 py-2.5">
                Files can be uploaded from the task detail page after creation.
            </p>
            @endif

            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $editingId ? 'Save Changes' : 'Create Task' }}
                </button>
                <button type="button" wire:click="$set('showModal', false)"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </x-livewire-modal>

    {{-- Shared inline upload modal (triggered from Files column) --}}
    <div
        x-data="{
            show: false,
            uploadUrl: '',
            dragging: false,
            uploading: false,
            fileObjects: [],
            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            },
            addFiles(fileList) {
                Array.from(fileList).forEach(f => {
                    const exists = this.fileObjects.find(e => e.name === f.name && e.size === f.size);
                    if (!exists) this.fileObjects.push(f);
                });
                this.syncInput();
            },
            removeFile(index) {
                this.fileObjects.splice(index, 1);
                this.syncInput();
            },
            syncInput() {
                const dT = new DataTransfer();
                this.fileObjects.forEach(f => dT.items.add(f));
                this.$refs.fileInput.files = dT.files;
            },
            reset() {
                this.fileObjects = [];
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },
            handleDrop(e) {
                this.dragging = false;
                if (e.dataTransfer?.files.length) this.addFiles(e.dataTransfer.files);
            }
        }"
        x-on:open-upload-modal.window="uploadUrl = $event.detail.uploadUrl; fileObjects = []; uploading = false; show = true"
        x-on:keydown.escape.window="show = false; reset()"
        x-show="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="show = false; reset()"></div>
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Upload Files</h3>
                <button @click="show = false; reset()" class="text-gray-400 dark:text-slate-500 hover:text-gray-600 p-1 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="uploadUrl" method="POST" enctype="multipart/form-data" class="p-6 space-y-4"
                @submit="if (uploadUrl && fileObjects.length) uploading = true">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Files <span class="text-gray-400 dark:text-slate-500 font-normal">(max 50 MB each)</span></label>
                    <div
                        class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-indigo-300 hover:bg-gray-50 dark:bg-slate-950/50'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                    >
                        <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-sm text-gray-600 dark:text-slate-400">Drag & drop files here, or <span class="text-indigo-600 font-medium">browse</span></p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Multiple files supported</p>
                        <input
                            x-ref="fileInput"
                            type="file"
                            name="files[]"
                            multiple
                            class="sr-only"
                            @change="addFiles($event.target.files)"
                        >
                    </div>
                    <template x-if="fileObjects.length > 0">
                        <ul class="mt-2 space-y-1">
                            <template x-for="(f, i) in fileObjects" :key="i">
                                <li class="flex items-center justify-between text-xs bg-gray-50 dark:bg-slate-950/50 border border-gray-200 dark:border-slate-800/60 rounded-lg px-3 py-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="truncate text-gray-700 dark:text-slate-300" x-text="f.name"></span>
                                    </div>
                                    <div class="flex items-center gap-2 ml-2 shrink-0">
                                        <span class="text-gray-400 dark:text-slate-500" x-text="formatSize(f.size)"></span>
                                        <button type="button" @click.stop="removeFile(i)"
                                            class="text-gray-400 dark:text-slate-500 hover:text-red-500 transition-colors p-0.5 rounded hover:bg-red-50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
                {{-- Upload progress (indeterminate) --}}
                <div x-show="uploading" x-cloak class="space-y-1.5">
                    <div class="upload-progress-track"></div>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                        <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Uploading your files…
                    </p>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        :disabled="!uploadUrl || fileObjects.length === 0 || uploading"
                        class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-2">
                        <svg x-show="uploading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <span x-text="uploading ? 'Uploading…' : 'Upload'">Upload</span>
                    </button>
                    <button type="button" :disabled="uploading" @click="show = false; reset()"
                        class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-delete-confirm-modal
        title="Delete Task"
        :description="'You are about to delete: ' . $deletingName"
        :consequences="['Delete all associated files', 'Remove all writer assignments', 'This action cannot be undone']"
    />
</div>

{{-- Kanban drag and drop. Loaded from the same CDN the dashboard charts use. --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    var GROUP = 'clarix-task-board';

    function idsIn(column) {
        return Array.from(column.querySelectorAll('[data-task-id]'))
            .map(function (card) { return parseInt(card.dataset.taskId, 10); });
    }

    function componentFor(el) {
        var root = el.closest('[wire\\:id]');

        return root ? window.Livewire.find(root.getAttribute('wire:id')) : null;
    }

    function initBoard() {
        if (typeof Sortable === 'undefined') return;

        document.querySelectorAll('[data-kanban-column]').forEach(function (column) {
            // Livewire morphs these nodes in place, so an already-wired column
            // keeps its instance rather than stacking a second one on top.
            if (column._kanbanSortable) return;

            column._kanbanSortable = new Sortable(column, {
                group: GROUP,
                animation: 150,
                draggable: '[data-task-id]',
                filter: '[data-draggable="0"]',
                // Filtering a card takes it out of the drag and nothing more.
                // preventOnFilter defaults to true, which calls
                // preventDefault() on the pointerdown that lands anywhere on a
                // filtered card — including its title link — so the one group
                // that cannot reorder also lost the ability to open a task.
                // Cancelling the drag is the `return` below the filter check;
                // it does not need the browser's default suppressed too.
                preventOnFilter: false,
                ghostClass: 'opacity-40',
                dragClass: 'shadow-lg',
                // On touch a plain swipe has to stay a swipe, or the board
                // could not be scrolled at all on a phone. Holding briefly is
                // what picks a card up; the mouse keeps its instant drag.
                delay: 160,
                delayOnTouchOnly: true,
                touchStartThreshold: 5,
                // Scroll the board while dragging a card towards its edge.
                scroll: true,
                scrollSensitivity: 80,
                scrollSpeed: 12,
                onStart: function () {
                    // Snap points fight a drag that crosses columns.
                    document.querySelectorAll('[data-kanban-board]')
                        .forEach(function (board) { board.classList.add('is-dragging'); });
                },
                onMove: function (evt) {
                    // Crossing columns is a status change, which not everyone
                    // who can reorder is allowed to make.
                    if (evt.from === evt.to) return true;

                    return evt.dragged.dataset.canChangeStatus === '1';
                },
                onEnd: function (evt) {
                    document.querySelectorAll('[data-kanban-board]')
                        .forEach(function (board) { board.classList.remove('is-dragging'); });

                    var from = evt.from;
                    var to = evt.to;

                    if (from === to && evt.oldIndex === evt.newIndex) return;

                    var fromStatus = from.dataset.status;
                    var toStatus = to.dataset.status;

                    var columnOrders = {};
                    columnOrders[toStatus] = idsIn(to);

                    if (fromStatus !== toStatus) {
                        columnOrders[fromStatus] = idsIn(from);
                    }

                    var component = componentFor(to);

                    if (component) {
                        component.call('moveTask', parseInt(evt.item.dataset.taskId, 10), toStatus, columnOrders);
                    }
                },
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initBoard);
    document.addEventListener('livewire:navigated', initBoard);
    document.addEventListener('livewire:init', function () {
        // Switching views or filtering re-renders the board, which can bring in
        // columns that were not on the page when it first loaded.
        Livewire.hook('morphed', initBoard);
    });

    initBoard();
})();
</script>
