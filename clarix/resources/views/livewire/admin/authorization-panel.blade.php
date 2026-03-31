<div class="space-y-6" wire:loading.class="opacity-60">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Authorization Panel</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500 mt-0.5">Control what each role can see and do. Changes apply instantly.</p>
        </div>
        <div class="flex items-center gap-2">
            <div wire:loading class="flex items-center gap-1.5 text-xs text-indigo-600 font-medium">
                <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Saving…
            </div>
            <div class="px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded-lg text-xs font-semibold text-indigo-700 flex items-center gap-1.5">
                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                Admin — Full Access (not configurable)
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-6 text-xs text-gray-500 dark:text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-5 py-3 shadow-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">Legend:</span>
        <span class="flex items-center gap-1.5">
            <span class="inline-flex w-5 h-5 rounded bg-indigo-600 items-center justify-center">
                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
            Allowed
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-5 h-5 rounded border-2 border-gray-300 bg-white dark:bg-gray-800"></span>
            Denied
        </span>
        <span class="text-gray-400 dark:text-gray-500">|</span>
        <span>Clicking a checkbox saves instantly — no confirmation needed.</span>
        <span class="ml-auto text-rose-500 font-medium">Hard rules enforced regardless: PM unit-scoping, writer identity masking, writer upload restriction.</span>
    </div>

    {{-- Permission Matrix per Role --}}
    @foreach($roles as $role)
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

        {{-- Role Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg {{ $role === 'pm' ? 'bg-blue-100' : 'bg-amber-100' }} flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {{ $role === 'pm' ? 'text-blue-600' : 'text-amber-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        @if($role === 'pm')
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        @endif
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $roleLabels[$role] }}</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500">
                        {{ collect($matrix[$role] ?? [])->filter()->count() }} of {{ collect($matrix[$role] ?? [])->count() }} permissions enabled
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="grantAll('{{ $role }}')"
                    wire:confirm="Grant all permissions to {{ $roleLabels[$role] }}?"
                    class="px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                    Grant All
                </button>
                <button wire:click="revokeAll('{{ $role }}')"
                    wire:confirm="Revoke all permissions from {{ $roleLabels[$role] }}?"
                    class="px-3 py-1.5 text-xs font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition-colors">
                    Revoke All
                </button>
            </div>
        </div>

        {{-- Modules --}}
        @foreach($modules as $moduleKey => $moduleLabel)
            @if(!empty($modulePermissions[$moduleKey]))
            <div class="border-b border-gray-100 dark:border-gray-700 last:border-0">

                {{-- Module label row --}}
                <div class="flex items-center gap-2 px-6 py-2.5 bg-gray-50 dark:bg-gray-900/50">
                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400"></div>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ $moduleLabel }}</span>
                </div>

                {{-- Permission rows --}}
                <div class="divide-y divide-gray-50">
                    @foreach($modulePermissions[$moduleKey] as $permName => $permData)
                    <div wire:key="{{ $role }}-{{ $permName }}"
                        class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:bg-gray-900/70 transition-colors group">

                        <div class="flex items-center gap-3 min-w-0">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $permData['label'] }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 dark:text-gray-400 dark:text-gray-500 font-mono bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $permName }}</span>
                        </div>

                        {{-- Toggle --}}
                        <button
                            wire:click="toggle('{{ $role }}', '{{ $permName }}')"
                            class="relative flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded-md transition-transform active:scale-95"
                            title="{{ ($matrix[$role][$permName] ?? false) ? 'Click to deny' : 'Click to allow' }}">

                            @if($matrix[$role][$permName] ?? false)
                                {{-- Allowed state --}}
                                <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-600 shadow-sm shadow-indigo-200 group-hover:bg-indigo-700 transition-colors">
                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>
                            @else
                                {{-- Denied state --}}
                                <span class="flex items-center justify-center w-7 h-7 rounded-lg border-2 border-gray-300 bg-white dark:bg-gray-800 group-hover:border-indigo-400 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </span>
                            @endif
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach

    </div>
    @endforeach

    {{-- Hard Rules Notice --}}
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div class="flex gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <h4 class="text-sm font-semibold text-amber-800">Hard Business Rules (cannot be overridden by permissions)</h4>
                <ul class="mt-2 space-y-1 text-xs text-amber-700 list-disc list-inside">
                    <li><strong>PM unit-scoping</strong> — PMs can only see and manage tasks within their own unit.</li>
                    <li><strong>Writer task isolation</strong> — Writers can only see tasks they are assigned to.</li>
                    <li><strong>Writer identity masking</strong> — PMs see "Writer" instead of real writer names.</li>
                    <li><strong>Writer file restriction</strong> — Writers cannot upload files regardless of permission.</li>
                </ul>
            </div>
        </div>
    </div>

</div>
