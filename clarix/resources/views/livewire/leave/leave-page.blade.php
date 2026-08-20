<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Leave</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Request time off and follow where it got to.</p>
        </div>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('leave.types') }}"
                class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Manage leave types</a>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Request form. Open to everyone: asking for leave is not a granted
             capability. --}}
        <div class="lg:col-span-1 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Request leave</h2>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Type</label>
                    <select wire:model="leave_type_id"
                        class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Choose…</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @error('leave_type_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">From</label>
                        <input type="date" wire:model="start_date"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('start_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">To</label>
                        <input type="date" wire:model="end_date"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('end_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Reason <span class="text-gray-400">(optional)</span></label>
                    <textarea wire:model="reason" rows="3"
                        class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                @error('leave') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                <button type="button" wire:click="submit" wire:loading.attr="disabled"
                    class="w-full px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors disabled:opacity-60">
                    Submit request
                </button>
            </div>

            {{-- Balances, informational. Allowances are what an agency has set;
                 nothing here blocks a request that would exceed one. --}}
            @if(collect($balances)->contains(fn ($b) => $b['allowance'] !== null || $b['used'] > 0))
                <div class="mt-5 pt-4 border-t border-gray-200 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">This year</p>
                    <ul class="mt-2 space-y-1.5">
                        @foreach($balances as $balance)
                            @if($balance['allowance'] !== null || $balance['used'] > 0)
                                <li class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-slate-300">{{ $balance['type']->name }}</span>
                                    <span class="text-gray-900 dark:text-slate-100 font-medium">
                                        {{ $balance['used'] }}@if($balance['allowance'] !== null) / {{ $balance['allowance'] }}@endif
                                        <span class="text-xs font-normal text-gray-400">days</span>
                                    </span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Your own history, for whoever may read their own records. --}}
        @if($canViewOwn)
        <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-200 dark:border-slate-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Your requests</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                        <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                            <th class="px-5 py-2.5 font-medium">Type</th>
                            <th class="px-5 py-2.5 font-medium">Dates</th>
                            <th class="px-5 py-2.5 font-medium">Days</th>
                            <th class="px-5 py-2.5 font-medium">Status</th>
                            <th class="px-5 py-2.5 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse($mine as $request)
                            <tr>
                                <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $request->leaveType?->name ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">
                                    {{ $request->start_date->format('j M') }} – {{ $request->end_date->format('j M Y') }}
                                </td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $request->dayCount() }}</td>
                                <td class="px-5 py-2.5"><x-leave-status :status="$request->status" /></td>
                                <td class="px-5 py-2.5 text-right">
                                    @if($request->isPending())
                                        <button type="button" wire:click="withdraw({{ $request->id }})"
                                            class="text-xs font-medium text-gray-500 dark:text-slate-400 hover:underline">Withdraw</button>
                                    @elseif($request->reviewer)
                                        <span class="text-xs text-gray-400 dark:text-slate-500">by {{ $request->reviewer->name }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">No requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- The queue, only for whoever may look past their own requests. --}}
    @if($this->canViewTeam)
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">
                        {{ auth()->user()->isPm() ? "Your unit's requests" : 'Requests' }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Your own requests are decided by someone else.</p>
                </div>

                <div class="inline-flex rounded-lg border border-gray-200 dark:border-slate-700 p-0.5">
                    <button type="button" wire:click="$set('tab', 'pending')"
                        class="px-3 py-1.5 text-xs font-medium rounded-md {{ $tab === 'pending' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-slate-300' }}">Pending</button>
                    <button type="button" wire:click="$set('tab', 'decided')"
                        class="px-3 py-1.5 text-xs font-medium rounded-md {{ $tab !== 'pending' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-slate-300' }}">Decided</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                        <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                            <th class="px-5 py-2.5 font-medium">Person</th>
                            <th class="px-5 py-2.5 font-medium">Type</th>
                            <th class="px-5 py-2.5 font-medium">Dates</th>
                            <th class="px-5 py-2.5 font-medium">Days</th>
                            <th class="px-5 py-2.5 font-medium">Reason</th>
                            <th class="px-5 py-2.5 font-medium">Status</th>
                            @if($this->canManage)
                                <th class="px-5 py-2.5 font-medium text-right">Decision</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse($queue as $request)
                            <tr>
                                <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $request->user?->name ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $request->leaveType?->name ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">
                                    {{ $request->start_date->format('j M') }} – {{ $request->end_date->format('j M Y') }}
                                </td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $request->dayCount() }}</td>
                                <td class="px-5 py-2.5 text-gray-500 dark:text-slate-400 max-w-xs truncate">{{ $request->reason ?: '—' }}</td>
                                <td class="px-5 py-2.5"><x-leave-status :status="$request->status" /></td>
                                @if($this->canManage)
                                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                        @if($request->isPending())
                                            <button type="button" wire:click="approve({{ $request->id }})"
                                                class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline">Approve</button>
                                            <button type="button" wire:click="reject({{ $request->id }})"
                                                class="ml-3 text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">Reject</button>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-slate-500">
                                                {{ $request->reviewer?->name ? 'by '.$request->reviewer->name : '—' }}
                                            </span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $this->canManage ? 7 : 6 }}" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">
                                    {{ $tab === 'pending' ? 'Nothing waiting.' : 'Nothing decided yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
