<div class="space-y-6">

    {{-- KPI Cards --}}
    <div class="grid grid-cols-3 gap-4">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Assigned Tasks</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Total assigned to you</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Pending</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ $stats['pending'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Awaiting your start</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Ready for Review</p>
                <p class="text-3xl font-bold text-teal-600 mt-1">{{ $stats['review'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Submitted for approval</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

    </div>

    {{-- Task table --}}
    @if($assignments->count())
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">My Active Tasks</h3>
        </div>
        <table class="min-w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-700">
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Task Code</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Deadline</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($assignments as $assignment)
                @php
                    $asc = match($assignment->status) {
                        'pending'         => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500',
                        'in_progress'     => 'bg-blue-100 text-blue-700',
                        'ready_for_review'=> 'bg-teal-100 text-teal-700',
                        default           => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500',
                    };
                    $overdue = $assignment->task->deadline?->isPast() && $assignment->status !== 'ready_for_review';
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <td class="px-5 py-3 text-xs font-mono text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $assignment->task->task_code }}</td>
                    <td class="px-5 py-3">
                        <a href="{{ route('tasks.show', $assignment->task) }}" class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:text-indigo-600 transition-colors">{{ $assignment->task->title }}</a>
                    </td>
                    <td class="px-5 py-3 whitespace-nowrap">
                        @if($assignment->task->deadline)
                            <span class="text-xs {{ $overdue ? 'text-red-600 font-semibold' : 'text-gray-500 dark:text-gray-400 dark:text-gray-500' }}">
                                {{ $overdue ? '⚠ ' : '' }}{{ $assignment->task->deadline->format('d M Y') }}
                            </span>
                        @else
                            <span class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500">—</span>
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
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 flex flex-col items-center justify-center text-center">
        <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-3">
            <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 dark:text-gray-500">No active tasks</p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Your assigned tasks will appear here.</p>
    </div>

    @endif

</div>
    @endif

</div>
