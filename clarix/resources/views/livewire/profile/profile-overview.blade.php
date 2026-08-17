{{--
    The personal profile.

    Every section is the viewer's own. Nothing here takes a subject, so there
    is no "whose data is this" question for the markup to get wrong — see
    ProfileOverview for why that is structural rather than checked.

    A section the viewer may not read renders the withheld note instead of its
    data. The note states a fact rather than reporting an error, and it should
    never read like something has broken.

    The three ERP sections pass a reason, because two different layers can
    withhold them: the role's permissions, which an administrator can change,
    and the agency's plan, which they cannot. Tasks has only the one layer and
    so passes nothing.
--}}
<div class="space-y-6 max-w-5xl">

    {{-- ── Who you are ──────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <div class="flex flex-col sm:flex-row sm:items-start gap-5">

            <div class="flex-shrink-0 w-16 h-16 rounded-full bg-indigo-100 dark:bg-indigo-500/10 flex items-center justify-center">
                <span class="text-xl font-semibold text-indigo-600 dark:text-indigo-400">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">{{ $user->name }}</h1>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300">
                        {{ $user->role }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-slate-500">Email</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-slate-100 break-all">{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-slate-500">Unit</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-slate-100">{{ $user->unit?->name ?? 'Unassigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-slate-500">Joined</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-slate-100">{{ $user->created_at?->format('j M Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <a href="{{ route('settings') }}"
                class="flex-shrink-0 self-start inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit account settings
            </a>
        </div>
    </div>

    {{-- ── Tasks ────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between gap-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Tasks</h2>
            @if($canSeeTasks)
                <a href="{{ route('tasks.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                    All my tasks
                </a>
            @endif
        </div>

        @if($canSeeTasks)
            <div class="p-6">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-semibold text-gray-900 dark:text-slate-100">{{ $taskStats['total'] }}</span>
                    <span class="text-sm text-gray-500 dark:text-slate-400">Assigned to me</span>
                </div>

                <div class="mt-5 grid grid-cols-2 sm:grid-cols-5 gap-3">
                    @foreach($taskStatuses as $key => $label)
                        <div class="rounded-lg border border-gray-100 dark:border-slate-800 px-3 py-2.5">
                            <p class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $taskStats['breakdown'][$key] }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <x-profile-withheld />
        @endif
    </div>

    {{-- ── Attendance ───────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Attendance</h2>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ now()->format('F Y') }} so far.</p>
            </div>
            @if($canSeeAttendance)
                <a href="{{ route('attendance.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                    Full attendance history
                </a>
            @endif
        </div>

        @if($canSeeAttendance)
            <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach($attendanceStatuses as $key => $label)
                    <div class="rounded-lg border border-gray-100 dark:border-slate-800 px-3 py-2.5">
                        <p class="text-lg font-semibold text-gray-900 dark:text-slate-100">{{ $attendanceSummary[$key] }}</p>
                        <p class="text-xs text-gray-500 dark:text-slate-400">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <x-profile-withheld :reason="$erpReason" />
        @endif
    </div>

    {{-- ── Leave ────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Leave</h2>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Balances for {{ now()->year }}.</p>
            </div>
            @if($canSeeLeave)
                <a href="{{ route('leave.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                    Leave history &amp; requests
                </a>
            @endif
        </div>

        @if($canSeeLeave)
            @if($pendingLeave > 0)
                <p class="px-6 pt-4 text-sm text-gray-500 dark:text-slate-400">
                    {{ $pendingLeave }} {{ Str::plural('request', $pendingLeave) }} awaiting a decision.
                </p>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                        <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                            <th class="px-6 py-2.5 font-medium">Type</th>
                            <th class="px-6 py-2.5 font-medium text-right">Allowance</th>
                            <th class="px-6 py-2.5 font-medium text-right">Used</th>
                            <th class="px-6 py-2.5 font-medium text-right">Remaining</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse($leaveBalances as $balance)
                            <tr>
                                <td class="px-6 py-2.5 text-gray-900 dark:text-slate-100">{{ $balance['type']->name }}</td>
                                {{-- Null allowance is a category the agency does not track, which
                                     is not the same as an allowance of zero. --}}
                                <td class="px-6 py-2.5 text-right text-gray-600 dark:text-slate-300">{{ $balance['allowance'] ?? 'Untracked' }}</td>
                                <td class="px-6 py-2.5 text-right text-gray-600 dark:text-slate-300">{{ $balance['used'] }}</td>
                                <td class="px-6 py-2.5 text-right font-medium text-gray-900 dark:text-slate-100">{{ $balance['remaining'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-slate-400">
                                    Your agency has not set up any leave types yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <x-profile-withheld :reason="$erpReason" />
        @endif
    </div>

    {{-- ── Payroll ──────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Payroll</h2>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Your own record. Amounts are entered by your administrator.</p>
            </div>
            @if($canSeePayroll)
                <a href="{{ route('payroll.index') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">
                    Full payroll history
                </a>
            @endif
        </div>

        @if($canSeePayroll)
            @if($payrollPaidTotal > 0)
                <div class="px-6 pt-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Paid to date</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-slate-100">{{ number_format($payrollPaidTotal, 2) }}</p>
                </div>
            @endif

            <div class="overflow-x-auto {{ $payrollPaidTotal > 0 ? 'mt-5' : '' }}">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                        <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                            <th class="px-6 py-2.5 font-medium">Month</th>
                            <th class="px-6 py-2.5 font-medium text-right">Net</th>
                            <th class="px-6 py-2.5 font-medium">Status</th>
                            <th class="px-6 py-2.5 font-medium">Paid on</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse($payrollRecords as $record)
                            <tr>
                                <td class="px-6 py-2.5 text-gray-900 dark:text-slate-100">{{ $record->month->format('F Y') }}</td>
                                <td class="px-6 py-2.5 text-right font-medium text-gray-900 dark:text-slate-100">{{ number_format((float) $record->net_amount, 2) }}</td>
                                <td class="px-6 py-2.5"><x-payroll-status :status="$record->status" /></td>
                                <td class="px-6 py-2.5 text-gray-500 dark:text-slate-400">{{ $record->paid_at?->format('j M Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-slate-400">
                                    No payroll records yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <x-profile-withheld :reason="$erpReason" />
        @endif
    </div>

</div>
