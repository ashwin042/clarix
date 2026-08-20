<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Credit List</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Track credits earned from completed tasks.</p>
        </div>
        <a href="{{ route('credits.export', array_filter([
                'dateFrom'       => $dateFrom,
                'dateTo'         => $dateTo,
                'filterUnit'     => $filterUnit,
                'filterPm'       => $filterPm,
                'filterTaskType' => $filterTaskType,
                'viewMode'       => $viewMode,
            ])) }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Excel
        </a>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500 dark:text-slate-400">Total Credits</p>
                <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                    <div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div>
                </div>
            </div>
            <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($totals->total_credits ?? 0, 2) }}
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-slate-500 dark:text-slate-400">From completed tasks</p>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-500 dark:text-slate-400">Completed Tasks</p>
                <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                    <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                </div>
            </div>
            <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ $totals->task_count ?? 0 }}</p>
            <p class="mt-1 text-xs text-gray-400 dark:text-slate-500 dark:text-slate-400">Matching current filters</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-4">
        <div class="flex flex-wrap items-end gap-3">

            {{-- Date From --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-slate-400">From</label>
                <input type="date" wire:model.live="dateFrom"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>

            {{-- Date To --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-slate-400">To</label>
                <input type="date" wire:model.live="dateTo"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
            </div>

            @if(auth()->user()->isAdmin())
            {{-- Unit filter --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-slate-400">Unit</label>
                <select wire:model.live="filterUnit"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">All Units</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- PM filter --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-slate-400">Project Manager</label>
                <select wire:model.live="filterPm"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">All PMs</option>
                    @foreach($pms as $pm)
                        <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Task Type filter --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-slate-400">Task Type</label>
                <select wire:model.live="filterTaskType"
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
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
            </div>

            {{-- Clear --}}
            <button wire:click="clearFilters"
                class="px-4 py-2 text-sm text-gray-600 dark:text-slate-400 border border-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                Clear
            </button>

            {{-- View Mode toggle (admin and PM only) --}}
            @if(!auth()->user()->isWriter())
            <div class="ml-auto flex items-center bg-gray-100 dark:bg-slate-800 rounded-lg p-1 gap-1">
                <button wire:click="$set('viewMode','grouped')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $viewMode === 'grouped' ? 'bg-white dark:bg-slate-900 shadow-sm text-gray-900 dark:text-slate-100' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:text-slate-300' }}">
                    Grouped
                </button>
                <button wire:click="$set('viewMode','unified')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $viewMode === 'unified' ? 'bg-white dark:bg-slate-900 shadow-sm text-gray-900 dark:text-slate-100' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:text-slate-300' }}">
                    Unified
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Unit tabs (admin only, shown when there is more than one unit in the current results) --}}
    @if(auth()->user()->isAdmin() && $tabUnits->count() > 0)
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none">
        <div class="flex overflow-x-auto gap-1 p-1.5" style="-webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
            {{-- All Units tab --}}
            <button wire:click="$set('filterUnit', '')"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors
                    {{ $filterUnit === ''
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800/60' }}">
                All Units
            </button>

            {{-- Per-unit tabs --}}
            @foreach($tabUnits as $tabUnit)
                <button wire:click="$set('filterUnit', '{{ $tabUnit->id }}')"
                    class="flex-shrink-0 px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors
                        {{ $filterUnit === (string)$tabUnit->id
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800/60' }}">
                    {{ $tabUnit->name }}
                </button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Grouped view (admin and PM only) --}}
    @if(!auth()->user()->isWriter() && $viewMode === 'grouped')
        @if(!empty($grouped) && $grouped->count())
            @foreach($grouped as $group)
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none overflow-hidden">
                    {{-- Unit header --}}
                    <div class="flex items-center justify-between px-6 py-3.5 bg-gray-50 dark:bg-slate-950/60 border-b border-gray-200 dark:border-slate-800/60">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-indigo-100 rounded-md flex items-center justify-center flex-shrink-0">
                                <span class="text-indigo-600 text-xs font-bold">{{ strtoupper(substr($group['unit']?->name ?? '?', 0, 1)) }}</span>
                            </div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $group['unit']?->name ?? 'Unknown Unit' }}</h4>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="text-gray-500 dark:text-slate-400">{{ $group['count'] }} task{{ $group['count'] !== 1 ? 's' : '' }}</span>
                            <span class="font-semibold text-indigo-600">{{ number_format($group['credits'], 2) }} credits</span>
                        </div>
                    </div>

                    {{-- Tasks table --}}
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                        <thead>
                            <tr class="bg-white dark:bg-slate-900">
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Writer</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Assigned Supervisor</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Completed</th>
                                <th class="px-6 py-2.5 text-right text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider pr-6">Credits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($group['tasks'] as $task)
                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="px-6 py-3 text-xs font-mono text-gray-500 dark:text-slate-400">{{ $task->task_code }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-800 dark:text-slate-200">
                                        {{ $task->writers->pluck('name')->join(', ') ?: '-' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->task_type ? ucfirst($task->task_type) : '-' }}</td>
                                    <td class="px-6 py-3">
                                        @php
                                            $pBadge = ['low' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400', 'medium' => 'bg-blue-100 text-blue-700', 'high' => 'bg-amber-100 text-amber-700', 'urgent' => 'bg-rose-100 text-rose-700'];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $pBadge[$task->priority] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400' }}">
                                            {{ ucfirst($task->priority) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->assignedAdmin?->name ?? '-' }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->completed_at?->format('d M Y') ?? '-' }}</td>
                                    <td class="px-6 py-3 text-sm font-semibold text-indigo-600 text-right pr-6">
                                        {{ $task->credit_amount ? number_format($task->credit_amount, 2) : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-indigo-50 border-t border-indigo-100">
                                <td colspan="6" class="px-6 py-2.5 text-sm font-semibold text-indigo-700">Unit Subtotal</td>
                                <td class="px-6 py-2.5 text-sm font-bold text-indigo-700 text-right pr-6">{{ number_format($group['credits'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        @else
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-12 flex flex-col items-center justify-center text-center">
                <div class="w-12 h-12 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-600 dark:text-slate-400">No completed tasks found</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Try adjusting the date range or filters.</p>
            </div>
        @endif
    @endif

    {{-- Unified / flat view --}}
    @if(auth()->user()->isWriter() || $viewMode === 'unified')
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none overflow-hidden">
            @if($tasks->count())
                <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                    <thead class="bg-gray-50 dark:bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Writer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Unit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Assigned Supervisor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider">Completed</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 dark:text-slate-500 uppercase tracking-wider pr-6">Credits</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($tasks as $task)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-3 text-xs font-mono text-gray-500 dark:text-slate-400">{{ $task->task_code }}</td>
                                <td class="px-6 py-3 text-sm text-gray-800 dark:text-slate-200">
                                    {{ $task->writers->pluck('name')->join(', ') ?: '-' }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->task_type ? ucfirst($task->task_type) : '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->unit?->name ?? '-' }}</td>
                                <td class="px-6 py-3">
                                    @php $pBadge = ['low' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400', 'medium' => 'bg-blue-100 text-blue-700', 'high' => 'bg-amber-100 text-amber-700', 'urgent' => 'bg-rose-100 text-rose-700']; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $pBadge[$task->priority] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400' }}">
                                        {{ ucfirst($task->priority) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->assignedAdmin?->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->completed_at?->format('d M Y') ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm font-semibold text-indigo-600 text-right pr-6">
                                    {{ $task->credit_amount ? number_format($task->credit_amount, 2) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($tasks->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-800/60">
                        {{ $tasks->links() }}
                    </div>
                @endif
            @else
                <div class="p-12 flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-600 dark:text-slate-400">No completed tasks found</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Try adjusting the date range or filters.</p>
                </div>
            @endif
        </div>
    @endif

</div>
