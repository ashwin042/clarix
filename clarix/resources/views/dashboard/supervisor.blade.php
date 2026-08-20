<x-app-layout pageTitle="Dashboard">

    <div class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Welcome back, {{ auth()->user()->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Track and direct work across every unit.</p>
            </div>
            <div class="flex gap-3">
                {{-- Each button is drawn off the permission behind it rather
                     than off the role, so an agency that narrows the supervisor
                     in the Authorization panel gets a page that matches. --}}
                {{-- Points at the board rather than /tasks/create. That screen
                     files a task under the actor's own unit with the actor as
                     its PM, neither of which a supervisor has; the board's
                     modal is the one that asks which unit. --}}
                @if(auth()->user()->hasPermission('tasks.create'))
                <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Task
                </a>
                @endif
                <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    View Tasks
                </a>
                @if(auth()->user()->hasPermission('units.view'))
                <a href="{{ route('admin.units.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Units
                </a>
                @endif
            </div>
        </div>

        {{-- The PM's figures, counted across the agency — PmStats widens its
             own scope for this role. --}}
        <livewire:dashboard.pm-stats />

        {{-- Clock in / out. Compact form links through to the full page. --}}
        <div class="max-w-md">
            {{-- The widget refuses with its own 402, so an agency without ERP
                 must not be shown it at all. --}}
            @if(auth()->user()->planAllows('erp'))
                @livewire('attendance.clock-widget', ['compact' => true])
            @endif
        </div>

    </div>
</x-app-layout>
