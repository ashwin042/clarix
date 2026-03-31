<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ? $pageTitle . ' — ' . config('app.name') : config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js" defer></script>
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-200 transition-colors duration-300">

<div x-data="{ sidebarOpen: true, openMenu: null }" class="flex h-screen overflow-hidden">

    {{-- Sidebar --}}
    <aside
        :class="sidebarOpen ? 'w-64' : 'w-20'"
        class="relative flex flex-col flex-shrink-0 bg-white dark:bg-gray-950 border-r border-gray-200 dark:border-gray-800 transition-all duration-300 ease-in-out overflow-hidden"
    >
        {{-- Logo --}}
        <div class="flex items-center h-16 px-4 border-b border-gray-100 dark:border-gray-800 flex-shrink-0" :class="sidebarOpen ? '' : 'justify-center'">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                    <span class="text-white text-sm font-bold leading-none">C</span>
                </div>
                <span x-show="sidebarOpen" class="text-gray-900 dark:text-white font-semibold text-base whitespace-nowrap">Clarix</span>
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-2.5 py-4 space-y-0.5 overflow-y-auto overflow-x-hidden scrollbar-none">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
                class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="sidebarOpen" x-transition:enter="transition-opacity duration-150 delay-75" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="whitespace-nowrap">Dashboard</span>
            </a>

            {{-- Section: Management --}}
            <div x-show="sidebarOpen" class="pt-4 pb-1.5 px-3">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">Management</p>
            </div>
            <div x-show="!sidebarOpen" class="py-2">
                <div class="border-t border-gray-200 dark:border-gray-700 mx-2"></div>
            </div>

            {{-- Units --}}
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('units.view'))
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'units' ? null : 'units')"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="[openMenu === 'units' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white', !sidebarOpen ? 'justify-center' : '']">
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Units</span>
                    <svg x-show="sidebarOpen" class="flex-shrink-0 h-4 w-4 text-gray-400 transition-transform duration-200" :class="openMenu === 'units' ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div x-show="openMenu === 'units' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="mt-0.5 space-y-0.5">
                    <a href="{{ route('admin.units.index') }}" class="flex items-center pl-11 pr-3 py-1.5 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Manage Units</a>
                </div>
            </div>
            @endif

            {{-- Users --}}
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('users.view'))
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'users' ? null : 'users')"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="[openMenu === 'users' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white', !sidebarOpen ? 'justify-center' : '']">
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Users</span>
                    <svg x-show="sidebarOpen" class="flex-shrink-0 h-4 w-4 text-gray-400 transition-transform duration-200" :class="openMenu === 'users' ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div x-show="openMenu === 'users' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="mt-0.5 space-y-0.5">
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.admins.index') }}" class="flex items-center pl-11 pr-3 py-1.5 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Admins</a>
                        <a href="{{ route('admin.pms.index') }}" class="flex items-center pl-11 pr-3 py-1.5 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Project Managers</a>
                        <a href="{{ route('admin.writers.index') }}" class="flex items-center pl-11 pr-3 py-1.5 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Writers</a>
                    @else
                        <a href="{{ route('pm.users') }}" class="flex items-center pl-11 pr-3 py-1.5 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Manage Users</a>
                    @endif
                </div>
            </div>
            @endif

            {{-- Tasks --}}
            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('tasks.view'))
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'tasks' ? null : 'tasks')"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="[openMenu === 'tasks' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white', !sidebarOpen ? 'justify-center' : '']">
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Tasks</span>
                    <svg x-show="sidebarOpen" class="flex-shrink-0 h-4 w-4 text-gray-400 transition-transform duration-200" :class="openMenu === 'tasks' ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
                <div x-show="openMenu === 'tasks' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="mt-0.5 space-y-0.5">
                    <a href="{{ route('tasks.index') }}" class="flex items-center pl-11 pr-3 py-1.5 rounded-lg text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">All Tasks</a>
                </div>
            </div>
            @endif

            {{-- Issues --}}
            <a href="{{ auth()->user()->isAdmin() ? route('admin.issues.index') : route('issues.index') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('issues.*') || request()->routeIs('admin.issues.*') ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Issues</span>
            </a>

            @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('credits.view'))
            {{-- Section: Finance --}}
            <div x-show="sidebarOpen" class="pt-4 pb-1 px-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Finance</span>
            </div>
            <div x-show="!sidebarOpen" class="py-2 px-3"><div class="border-t border-gray-200 dark:border-gray-700"></div></div>

            {{-- Credit List --}}
            <a href="{{ route('credits.index') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('credits.index') ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Credit List</span>
            </a>
            @endif

            {{-- Section: System --}}
            <div x-show="sidebarOpen" class="pt-4 pb-1 px-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">System</span>
            </div>
            <div x-show="!sidebarOpen" class="py-2 px-3"><div class="border-t border-gray-200 dark:border-gray-700"></div></div>

            {{-- Authorization Panel (admin only) --}}
            @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.authorization') }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('admin.authorization') ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}"
                :class="sidebarOpen ? '' : 'justify-center'">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Authorization</span>
            </a>
            @endif

            {{-- Settings --}}
            <a href="#"
                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white"
                :class="sidebarOpen ? '' : 'justify-center'">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Settings</span>
            </a>

        </nav>

        {{-- User profile footer --}}
        <div class="border-t border-gray-200 dark:border-gray-800 p-3 flex-shrink-0">
            <div class="flex items-center gap-3 min-w-0" :class="sidebarOpen ? '' : 'justify-center'">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center">
                    <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                </div>
                <div x-show="sidebarOpen" class="min-w-0">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate whitespace-nowrap">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 capitalize whitespace-nowrap">{{ auth()->user()->role }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main content area --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        {{-- Top navigation bar --}}
        <header class="flex items-center justify-between h-16 px-6 bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                @isset($pageTitle)
                    <h1 class="text-base font-semibold text-gray-800 dark:text-gray-200">{{ $pageTitle }}</h1>
                @endisset
            </div>

            <div class="flex items-center gap-3">
                {{-- Dark mode toggle --}}
                <button
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    @click="dark = !dark; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                    class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    title="Toggle dark mode">
                    <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                {{-- Notification bell --}}
                @auth
                    <livewire:notification-bell />
                @endauth

                {{-- User dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center">
                            <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        </div>
                        <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-transition
                        class="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50">
                        <a href="{{ route('profile') }}"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Profile
                        </a>
                        <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>

<x-toast />

@livewireScripts
</body>
</html>
