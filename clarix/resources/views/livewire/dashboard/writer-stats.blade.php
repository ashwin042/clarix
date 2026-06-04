<div class="space-y-6">

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned Tasks</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-slate-100 mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Total assigned to you</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Pending</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ $stats['pending'] }}</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Awaiting your start</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Ready for Review</p>
                <p class="text-3xl font-bold text-teal-600 mt-1">{{ $stats['review'] }}</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Submitted for approval</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Completed Tasks</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completed'] }}</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Tasks finished</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>

        <livewire:dashboard.credits-card role="writer" />

    </div>

    {{-- Task table --}}
    @if($assignments->count())
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-800/60">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">My Active Tasks</h3>
        </div>
        <table class="min-w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-950/60 border-b border-gray-100 dark:border-slate-800/60">
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Task Code</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned Supervisor</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Deadline</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($assignments as $assignment)
                @php
                    $asc = match($assignment->status) {
                        'pending'         => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400',
                        'in_progress'     => 'bg-blue-100 text-blue-700',
                        'ready_for_review'=> 'bg-teal-100 text-teal-700',
                        default           => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400',
                    };
                    $overdue = $assignment->task->deadline?->isPast() && $assignment->status !== 'ready_for_review';
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-5 py-3 text-xs font-mono font-bold text-gray-900 dark:text-slate-100 whitespace-nowrap">{{ $assignment->task->task_code }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400 whitespace-nowrap">
                        {{ $assignment->task->assignedAdmin?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 whitespace-nowrap">
                        @if($assignment->task->deadline)
                            <span class="text-xs {{ $overdue ? 'text-red-600 font-semibold' : 'text-gray-500 dark:text-slate-400' }}">
                                {{ $overdue ? '⚠ ' : '' }}{{ $assignment->task->deadline->format('d M Y') }}
                            </span>
                        @else
                            <span class="text-xs text-gray-400 dark:text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $asc }}">
                            {{ str_replace('_', ' ', ucfirst($assignment->status)) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-12 flex flex-col items-center justify-center text-center">
        <div class="w-12 h-12 bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
            <svg class="h-6 w-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <p class="text-sm font-medium text-gray-600 dark:text-slate-400">No active tasks</p>
        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Your assigned tasks will appear here.</p>
    </div>

    @endif

    {{-- Recently Completed Tasks --}}
    @if($completedAssignments->count())
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-800/60">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Recently Completed Tasks</h3>
        </div>
        <table class="min-w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-950/60 border-b border-gray-100 dark:border-slate-800/60">
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Task Code</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned Supervisor</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Deadline</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-800/60">
                @foreach($completedAssignments as $assignment)
                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-5 py-3 text-xs font-mono font-bold text-gray-900 dark:text-slate-100 whitespace-nowrap">{{ $assignment->task->task_code }}</td>
                    <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400 whitespace-nowrap">
                        {{ $assignment->task->assignedAdmin?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 whitespace-nowrap">
                        @if($assignment->task->deadline)
                            <span class="text-xs text-gray-500 dark:text-slate-400">{{ $assignment->task->deadline->format('d M Y') }}</span>
                        @else
                            <span class="text-xs text-gray-400 dark:text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Completed</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
