<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notifications</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500 mt-0.5">{{ $unreadCount }} unread</p>
        </div>
        @if($unreadCount > 0)
            <button wire:click="markAllRead"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Mark all as read
            </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-2 mb-5">
        @foreach(['' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $value => $label)
            <button wire:click="$set('filter', '{{ $value }}')"
                class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $filter === $value ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Notification list --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($notifications as $notification)
            @php
                $data = $notification->data;
                $isUnread = is_null($notification->read_at);
                $type = $data['type'] ?? '';
                $relatedId = $data['related_id'] ?? null;

                $link = match(true) {
                    in_array($type, ['task_created', 'task_status_updated']) && $relatedId => route('tasks.show', $relatedId),
                    in_array($type, ['issue_created', 'issue_resolved']) && $relatedId => route('issues.show', $relatedId),
                    default => '#',
                };

                $iconColor = match($type) {
                    'task_created' => 'text-blue-500 bg-blue-50',
                    'task_status_updated' => 'text-amber-500 bg-amber-50',
                    'issue_created' => 'text-red-500 bg-red-50',
                    'issue_resolved' => 'text-green-500 bg-green-50',
                    default => 'text-gray-500 dark:text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-900',
                };
                $typeIcon = match($type) {
                    'task_created' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
                    'task_status_updated' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>',
                    'issue_created' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>',
                    'issue_resolved' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                };
            @endphp
            <a href="{{ $link }}" wire:click="markAsRead('{{ $notification->id }}')"
                class="flex items-center gap-4 px-5 py-4 transition-colors {{ $isUnread ? 'bg-indigo-50/30 hover:bg-indigo-50' : 'hover:bg-gray-50 dark:bg-gray-900' }}">
                <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center {{ $iconColor }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $typeIcon !!}</svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm {{ $isUnread ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300' }}">
                        {{ $data['message'] ?? 'Notification' }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                @if($isUnread)
                    <span class="shrink-0 w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                @endif
            </a>
        @empty
            <div class="py-16 text-center dark:text-gray-400 dark:text-gray-500">
                <svg class="mx-auto w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500">No notifications found.</p>
            </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</div>
