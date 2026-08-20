<x-app-layout pageTitle="Dashboard">

    <div class="space-y-6">

        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Attendance, leave and payroll for the agency.</p>
        </div>

        {{-- Deliberately no task or credit figures.
             HR holds no permission over the work, so a stat card counting it
             would be showing them something they may not open. --}}

        {{-- Clock in / out. Compact form links through to the full page. --}}
        <div class="max-w-md">
            {{-- The widget refuses with its own 402, so an agency without ERP
                 must not be shown it at all. --}}
            @if(auth()->user()->planAllows('erp'))
                @livewire('attendance.clock-widget', ['compact' => true])
            @endif
        </div>

        @if(auth()->user()->planAllows('erp'))
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-3">
                <a href="{{ route('attendance.index') }}" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 dark:border-slate-700/60 text-sm text-gray-700 dark:text-slate-300 hover:border-indigo-300 dark:hover:border-indigo-500/40 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Attendance
                </a>
                <a href="{{ route('leave.index') }}" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 dark:border-slate-700/60 text-sm text-gray-700 dark:text-slate-300 hover:border-indigo-300 dark:hover:border-indigo-500/40 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Leave Requests
                </a>
                @if(auth()->user()->hasPermission('payroll.manage') || auth()->user()->hasPermission('payroll.view_own'))
                <a href="{{ auth()->user()->hasPermission('payroll.manage') ? route('payroll.manage') : route('payroll.index') }}" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 dark:border-slate-700/60 text-sm text-gray-700 dark:text-slate-300 hover:border-indigo-300 dark:hover:border-indigo-500/40 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Payroll
                </a>
                @endif
            </div>
        </div>
        @endif

    </div>
</x-app-layout>
