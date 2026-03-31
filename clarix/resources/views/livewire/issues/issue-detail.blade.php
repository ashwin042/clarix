<div>
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 mb-6">
        <a href="{{ route('issues.index') }}" class="hover:text-gray-600 dark:text-gray-400 dark:text-gray-500 transition-colors">Issues</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 dark:text-gray-300 font-medium truncate max-w-xs">{{ $issue->title }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Issue detail + thread --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Issue card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ $issue->title }}</h1>
                    @php
                        $sc = match($issue->status) {
                            'open'      => 'bg-blue-100 text-blue-700',
                            'in_review' => 'bg-yellow-100 text-yellow-700',
                            'resolved'  => 'bg-green-100 text-green-700',
                            'closed'    => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 dark:text-gray-500',
                        };
                        $pc = match($issue->priority) { 'high' => 'bg-red-100 text-red-700', 'medium' => 'bg-yellow-100 text-yellow-700', 'low' => 'bg-green-100 text-green-700' };
                    @endphp
                    <span class="shrink-0 inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $sc }}">
                        {{ str_replace('_', ' ', ucfirst($issue->status)) }}
                    </span>
                </div>
                <div class="flex items-center gap-3 mb-5 text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500">
                    <span class="inline-flex px-2 py-0.5 rounded-full font-medium {{ $pc }}">{{ ucfirst($issue->priority) }} Priority</span>
                    <span>Submitted by <span class="font-medium text-gray-600 dark:text-gray-400 dark:text-gray-500">{{ $issue->creator->name }}</span></span>
                    <span>{{ $issue->created_at->format('M d, Y') }}</span>
                </div>
                <div class="prose prose-sm max-w-none">
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap">{{ $issue->message }}</p>
                </div>
            </div>

            {{-- Thread --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Thread ({{ $issue->replies->count() }})</h2>
                </div>

                @if($issue->replies->count())
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($issue->replies as $reply)
                    @php $isAdmin = $reply->author->isAdmin(); @endphp
                    <div class="px-6 py-4 {{ $isAdmin ? 'bg-indigo-50/40' : '' }}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full {{ $isAdmin ? 'bg-indigo-600' : 'bg-gray-200' }} flex items-center justify-center">
                                    <span class="text-xs font-semibold {{ $isAdmin ? 'text-white' : 'text-gray-600 dark:text-gray-400 dark:text-gray-500' }}">
                                        {{ strtoupper(substr($reply->author->name, 0, 1)) }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $reply->author->name }}</span>
                                @if($isAdmin)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 text-indigo-700">Admin</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500">{{ $reply->created_at->format('M d, Y · H:i') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap ml-9">{{ $reply->message }}</p>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-6 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No replies yet.</div>
                @endif

                {{-- Reply form --}}
                @if($issue->status !== 'closed')
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    <form wire:submit="submitReply" class="space-y-3">
                        <textarea wire:model="replyMessage" rows="3"
                            placeholder="Write a reply..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none @error('replyMessage') border-red-400 @enderror"></textarea>
                        @error('replyMessage') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <div class="flex justify-end">
                            <button type="submit"
                                class="px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60">
                                <span wire:loading.remove wire:target="submitReply">Send Reply</span>
                                <span wire:loading wire:target="submitReply">Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>
                @else
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 text-center text-sm text-gray-400 dark:text-gray-500">This issue is closed.</div>
                @endif
            </div>
        </div>

        {{-- Right: Meta + Admin actions --}}
        <div class="space-y-5">

            {{-- Status card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Issue Details</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400 dark:text-gray-500">Status</dt>
                        <dd><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ str_replace('_', ' ', ucfirst($issue->status)) }}</span></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400 dark:text-gray-500">Priority</dt>
                        <dd><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $pc }}">{{ ucfirst($issue->priority) }}</span></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400 dark:text-gray-500">Submitted by</dt>
                        <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $issue->creator->name }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400 dark:text-gray-500">Role</dt>
                        <dd class="font-medium text-gray-700 dark:text-gray-300">{{ ucfirst($issue->creator->role) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400 dark:text-gray-500">Opened</dt>
                        <dd class="text-gray-600 dark:text-gray-400 dark:text-gray-500">{{ $issue->created_at->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400 dark:text-gray-500">Replies</dt>
                        <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $issue->replies->count() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Admin status change --}}
            @if(auth()->user()->isAdmin())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Update Status</h3>
                <div class="space-y-2">
                    @php
                        $transitions = [
                            'open'      => ['label' => 'Mark In Review',  'next' => 'in_review', 'color' => 'bg-yellow-500 hover:bg-yellow-600'],
                            'in_review' => ['label' => 'Mark Resolved',   'next' => 'resolved',  'color' => 'bg-green-600 hover:bg-green-700'],
                            'resolved'  => ['label' => 'Close Issue',     'next' => 'closed',    'color' => 'bg-gray-600 hover:bg-gray-700'],
                        ];
                    @endphp
                    @if(isset($transitions[$issue->status]))
                    @php $t = $transitions[$issue->status]; @endphp
                    <button wire:click="updateStatus('{{ $t['next'] }}')"
                        class="w-full py-2 text-white text-sm font-semibold rounded-lg transition-colors {{ $t['color'] }}">
                        {{ $t['label'] }}
                    </button>
                    @else
                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">Issue is closed</p>
                    @endif

                    @if($issue->status !== 'open')
                    <button wire:click="updateStatus('open')"
                        class="w-full py-2 border border-gray-300 text-gray-600 dark:text-gray-400 dark:text-gray-500 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        Reopen Issue
                    </button>
                    @endif
                </div>
            </div>
            @endif

            {{-- Back link --}}
            <a href="{{ route('issues.index') }}" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:text-gray-300 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Issues
            </a>
        </div>
    </div>
</div>
