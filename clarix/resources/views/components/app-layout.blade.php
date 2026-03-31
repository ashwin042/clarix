@props(['pageTitle' => null])

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
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">

<div x-data="{ sidebarOpen: true, openMenu: null }" class="flex h-screen overflow-hidden">

    {{-- Sidebar --}}
    <aside
        :class="sidebarOpen ? 'w-60' : 'w-16'"
        class="relative flex flex-col flex-shrink-0 bg-white border-r border-gray-200 transition-all duration-300 ease-in-out overflow-hidden"
    >
        {{-- Logo --}}
        <div class="flex items-center h-16 px-4 border-b border-gray-200 flex-shrink-0">
            <div :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'" class="transition-all duration-200 overflow-hidden">
                <span class="text-lg font-bold text-indigo-600 tracking-tight whitespace-nowrap">Clarix</span>
            </div>
            <div :class="sidebarOpen ? 'opacity-0 w-0' : 'opacity-100'" class="transition-all duration-200 overflow-hidden flex items-center justify-center w-full">
                <span class="text-lg font-bold text-indigo-600">C</span>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto overflow-x-hidden">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
                class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 {{ request()->routeIs('dashboard') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
            </a>

            {{-- Divider --}}
            <div x-show="sidebarOpen" class="pt-2 pb-1">
                <p class="px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Management</p>
            </div>

            {{-- Units --}}
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'units' ? null : 'units')"
                    class="group w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="openMenu === 'units' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Units</span>
                    <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200"
                        :class="openMenu === 'units' ? 'rotate-90' : ''"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div
                    x-show="openMenu === 'units' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5"
                >
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">View All Units</a>
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">Add Unit</a>
                </div>
            </div>

            {{-- Project Managers --}}
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'pms' ? null : 'pms')"
                    class="group w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="openMenu === 'pms' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Project Managers</span>
                    <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200"
                        :class="openMenu === 'pms' ? 'rotate-90' : ''"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div
                    x-show="openMenu === 'pms' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5"
                >
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">View PMs</a>
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">Add PM</a>
                </div>
            </div>

            {{-- Writers --}}
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'writers' ? null : 'writers')"
                    class="group w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="openMenu === 'writers' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Writers</span>
                    <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200"
                        :class="openMenu === 'writers' ? 'rotate-90' : ''"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div
                    x-show="openMenu === 'writers' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5"
                >
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">View Writers</a>
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">Add Writer</a>
                </div>
            </div>

            {{-- Tasks --}}
            <div>
                <button type="button"
                    @click="sidebarOpen && (openMenu = openMenu === 'tasks' ? null : 'tasks')"
                    class="group w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="openMenu === 'tasks' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span x-show="sidebarOpen" class="flex-1 text-left whitespace-nowrap">Tasks</span>
                    <svg x-show="sidebarOpen" xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5 text-gray-400 transition-transform duration-200"
                        :class="openMenu === 'tasks' ? 'rotate-90' : ''"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div
                    x-show="openMenu === 'tasks' && sidebarOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="mt-0.5 space-y-0.5"
                >
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">View Tasks</a>
                    <a href="#" class="flex items-center pl-10 pr-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors">Assign Tasks</a>
                </div>
            </div>

            {{-- Divider --}}
            <div x-show="sidebarOpen" class="pt-2 pb-1">
                <p class="px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Finance</p>
            </div>

            {{-- Credit List --}}
            <a href="#"
                class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Credit List</span>
            </a>

            {{-- Divider --}}
            <div x-show="sidebarOpen" class="pt-2 pb-1">
                <p class="px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-400">System</p>
            </div>

            {{-- Settings --}}
            <a href="#"
                class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Settings</span>
            </a>

        </nav>

        {{-- User info at bottom --}}
        <div class="border-t border-gray-200 p-3 flex-shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                    <span class="text-xs font-semibold text-indigo-600">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </span>
                </div>
                <div :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'" class="transition-all duration-200 overflow-hidden min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate whitespace-nowrap">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400 capitalize whitespace-nowrap">{{ auth()->user()->role }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main area --}}
    <div class="flex flex-col flex-1 overflow-hidden">

        <header class="flex items-center justify-between h-16 px-6 bg-white border-b border-gray-200 flex-shrink-0">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                @if($pageTitle)
                    <h1 class="text-base font-semibold text-gray-800">{{ $pageTitle }}</h1>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <button class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </button>

                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                        <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center">
                            <span class="text-xs font-semibold text-indigo-600">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        </div>
                        <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-transition
                        class="absolute right-0 mt-1 w-48 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50">
                        <a href="{{ route('profile') }}"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Profile
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
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

        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
