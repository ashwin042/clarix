<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Account Deletion Requests</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Review and process user account deletion requests.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-5">
        <select wire:model.live="filterStatus" class="border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
        @if($requests->count())
            <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                <thead class="bg-gray-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Reason</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Requested</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Processed By</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                    @foreach($requests as $req)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $req->user?->name ?? 'Deleted User' }}</p>
                                    <p class="text-xs text-gray-400">{{ $req->user?->email ?? '—' }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ match($req->user?->role) { 'admin' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300', 'pm' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300', default => 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-300' } }}">
                                    {{ ucfirst($req->user?->role ?? '—') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-300 max-w-xs truncate">{{ $req->reason ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $sc = match($req->status) {
                                        'pending' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ ucfirst($req->status) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $req->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">
                                @if($req->processor)
                                    {{ $req->processor->name }}
                                    <p class="text-xs text-gray-400">{{ $req->processed_at->format('M d, Y') }}</p>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if($req->status === 'pending')
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="approve({{ $req->id }})"
                                            wire:confirm="Are you sure you want to approve this request? The user will be permanently deleted."
                                            class="px-3 py-1.5 text-xs font-medium text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors">
                                            Approve
                                        </button>
                                        <button wire:click="reject({{ $req->id }})"
                                            class="px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                            Reject
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($requests->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $requests->links() }}</div>
            @endif
        @else
            <div class="py-16 text-center">
                <div class="w-12 h-12 mx-auto bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-slate-400">No deletion requests found.</p>
            </div>
        @endif
    </div>
</div>
