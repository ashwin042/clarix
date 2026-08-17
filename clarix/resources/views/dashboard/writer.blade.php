<x-app-layout pageTitle="My Tasks">

    <div class="space-y-6">

        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-slate-100">My Tasks</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Track and update your assigned work.</p>
        </div>

        <livewire:dashboard.writer-stats />

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
