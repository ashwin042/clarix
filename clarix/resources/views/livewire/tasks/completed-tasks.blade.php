<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Completed Tasks</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">All tasks that have been completed</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col md:flex-row md:flex-wrap md:items-center gap-3 mb-5">
        <div class="relative w-full md:w-auto md:flex-1 md:min-w-[200px] md:max-w-xs">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search tasks..."
                class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
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
        @if(auth()->user()->isAdmin())
        <select wire:model.live="filterUnit" class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All units</option>
            @foreach($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
            @endforeach
        </select>
        @endif
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-visible">
        @if($tasks->count())
            {{-- Mobile card list (below md) --}}
            <div class="md:hidden divide-y divide-gray-100 dark:divide-slate-800/60">
                @foreach($tasks as $task)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-bold font-mono text-gray-900 dark:text-slate-100 hover:text-indigo-600 transition-colors break-all">{{ $task->task_code }}</a>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 bg-green-100 text-green-700">Completed</span>
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
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Completed</dt>
                                <dd class="text-sm text-gray-600 dark:text-slate-400 mt-0.5">{{ $task->completed_at?->format('M d, Y') ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Credits</dt>
                                <dd class="text-sm font-medium text-gray-700 dark:text-slate-300 mt-0.5">{{ number_format($task->credit_amount, 2) }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Writers</dt>
                                <dd class="mt-1">
                                    @if($task->assignments->count())
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($task->assignments as $assignment)
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $assignment->writer->name }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-slate-500">-</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        <div class="flex items-center gap-2 pt-1">
                            <a href="{{ route('tasks.show', $task) }}"
                                class="flex-1 text-center px-3 py-2 text-xs font-medium text-gray-600 dark:text-slate-400 border border-gray-200 dark:border-slate-800 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">View</a>
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
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider cursor-pointer select-none" wire:click="sortBy('completed_at')">
                            <div class="flex items-center gap-1">
                                Completed
                                @if($sortBy === 'completed_at')
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
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->completed_at?->format('M d, Y') ?? '-' }}</td>
                            <td class="px-5 py-3 text-sm font-medium text-gray-700 dark:text-slate-300">{{ number_format($task->credit_amount, 2) }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">
                                @if($task->assignments->count())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($task->assignments as $assignment)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $assignment->writer->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-slate-500">-</span>
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
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-slate-500 text-sm">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('tasks.show', $task) }}"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">View</a>
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
                    <svg class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7l2 2 4-4"/></svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-slate-400">No completed tasks found.</p>
            </div>
        @endif
    </div>
</div>
