<x-app-layout pageTitle="Tasks">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tasks</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and track all project tasks</p>
            </div>
            @can('create', App\Models\Task::class)
                <a href="{{ route('tasks.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Task
                </a>
            @endcan
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            @if($tasks->count())
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Deadline</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($tasks as $task)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-gray-900 text-sm">{{ $task->title }}</div>
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $task->task_code }}</div>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ $task->unit->name }}</td>
                                <td class="px-5 py-3">
                                    @php
                                        $priorityClass = match($task->priority) {
                                            'high'   => 'bg-red-100 text-red-700',
                                            'medium' => 'bg-yellow-100 text-yellow-700',
                                            'low'    => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $priorityClass }}">
                                        {{ ucfirst($task->priority) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @php
                                        $statusClass = match($task->status) {
                                            'pending'     => 'bg-gray-100 text-gray-600',
                                            'in_progress' => 'bg-blue-100 text-blue-700',
                                            'submitted'   => 'bg-purple-100 text-purple-700',
                                            'verified'    => 'bg-teal-100 text-teal-700',
                                            'completed'   => 'bg-green-100 text-green-700',
                                        };
                                        $statusLabel = str_replace('_', ' ', ucfirst($task->status));
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600">
                                    {{ $task->deadline->format('M d, Y') }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($tasks->hasPages())
                    <div class="px-5 py-4 border-t border-gray-100">
                        {{ $tasks->links() }}
                    </div>
                @endif
            @else
                <div class="px-5 py-16 text-center">
                    <svg class="mx-auto w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-gray-500 text-sm">No tasks found.</p>
                    @can('create', App\Models\Task::class)
                        <a href="{{ route('tasks.create') }}" class="mt-3 inline-flex text-indigo-600 text-sm font-medium hover:underline">Create your first task</a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
