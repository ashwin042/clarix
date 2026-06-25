<div>
    {{-- Header --}}
    <div class="flex items-center gap-2 mb-1">
        <a href="{{ route('admin.units.index') }}" class="text-sm text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors">Units</a>
        <svg class="w-4 h-4 text-gray-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-sm text-gray-600 dark:text-slate-400">{{ $unit->name }}</span>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0">
                <span class="text-sm font-bold text-indigo-600">{{ strtoupper(substr($unit->name, 0, 2)) }}</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $unit->name }}</h1>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Unit analytics overview</p>
            </div>
        </div>
        <a href="{{ route('admin.units.index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 border border-gray-300 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Units
        </a>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Tasks</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-slate-100 mt-1">{{ $totalTasks }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Completed Tasks</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ $completedTasks }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Pending Tasks</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ $pendingTasks }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Credits Earned</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-slate-100 mt-1">{{ number_format($totalCredits, 2) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
        </div>
    </div>

    {{-- Project Managers --}}
    <div class="mb-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100 mb-3">Project Managers</h2>
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
            @if($pms->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                        <thead class="bg-gray-50 dark:bg-slate-950/60">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Email</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Tasks</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Completed Tasks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                            @foreach($pms as $pm)
                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-slate-100">{{ $pm->name }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $pm->email }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $pm->total_tasks_count }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $pm->completed_tasks_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-12 text-center">
                    <p class="text-sm text-gray-500 dark:text-slate-400">No project managers assigned to this unit.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Tasks Breakdown --}}
    <div class="mb-8">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100 mb-3">Tasks Breakdown</h2>
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
            @if($tasks->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                        <thead class="bg-gray-50 dark:bg-slate-950/60">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Task Name</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Type</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned Supervisor</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Writer</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credits</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Deadline</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                            @foreach($tasks as $task)
                                @php
                                    $sc = match($task->status) {
                                        'pending'     => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400',
                                        'in_progress' => 'bg-blue-100 text-blue-700',
                                        'completed'   => 'bg-green-100 text-green-700',
                                        'cancelled'   => 'bg-rose-100 text-rose-700',
                                        default       => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-slate-100">
                                        <a href="{{ route('tasks.show', $task) }}" class="hover:text-indigo-600 transition-colors">{{ $task->title }}</a>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->task_type ? ucfirst($task->task_type) : '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->assignedAdmin?->name ?? '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->writers->pluck('name')->join(', ') ?: '-' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-sm font-medium text-gray-700 dark:text-slate-300">{{ $task->credit_amount ? number_format($task->credit_amount, 2) : '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $task->deadline?->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-12 text-center">
                    <p class="text-sm text-gray-500 dark:text-slate-400">No tasks found for this unit.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Task Types Summary --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100 mb-3">Task Types Summary</h2>
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            @if($taskTypeCounts->count())
                <div class="flex flex-wrap gap-3">
                    @foreach($taskTypeCounts as $type => $count)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300">
                            {{ ucfirst($type) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-slate-400">No task types recorded for this unit.</p>
            @endif
        </div>
    </div>
</div>
