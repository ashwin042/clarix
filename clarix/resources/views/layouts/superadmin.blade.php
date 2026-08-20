<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($pageTitle ?? null) ? $pageTitle . ' - Clarix Platform' : 'Clarix Platform' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{--
    Deliberately unlike layouts/app.blade.php.

    A superadmin can see every agency's data at once, so the one thing this
    chrome has to do is make it impossible to mistake for an agency's own
    dashboard: amber instead of indigo, a permanent banner naming the context,
    no sidebar, and always dark. Nothing here is decorative — it is all there
    so that "which context am I in" is answerable at a glance.
--}}
<body class="font-sans antialiased bg-slate-950 text-slate-200 min-h-screen">

{{-- Context banner. Deliberately loud and always present. --}}
<div class="bg-amber-500 text-amber-950 text-center text-xs font-bold uppercase tracking-widest py-1.5">
    Platform administration &mdash; all organizations
</div>

<header class="border-b border-slate-800 bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16 gap-4">

            <div class="flex items-center gap-6 min-w-0">
                <a href="{{ route('superadmin.organizations.index') }}" class="flex items-center gap-2.5 shrink-0">
                    <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center">
                        <span class="text-amber-950 text-sm font-bold leading-none">C</span>
                    </div>
                    <span class="font-semibold text-white whitespace-nowrap">Clarix <span class="text-amber-400">Platform</span></span>
                </a>

                <nav class="hidden sm:flex items-center gap-1">
                    <a href="{{ route('superadmin.organizations.index') }}"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('superadmin.organizations.*') ? 'bg-amber-500 text-amber-950' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        Organizations
                    </a>
                    <a href="{{ route('superadmin.storage') }}"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('superadmin.storage') ? 'bg-amber-500 text-amber-950' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        Storage
                    </a>
                </nav>
            </div>

            <div x-data="{ open: false }" class="relative shrink-0">
                <button @click="open = !open"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 transition-colors">
                    <div class="w-7 h-7 rounded-full bg-amber-500/15 flex items-center justify-center">
                        <span class="text-xs font-semibold text-amber-400">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                    <span class="hidden md:inline text-[10px] font-bold uppercase tracking-wider text-amber-400 border border-amber-500/40 rounded px-1.5 py-0.5">Superadmin</span>
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" @click.outside="open = false" x-transition x-cloak
                    class="absolute right-0 mt-1 w-48 bg-slate-900 rounded-xl shadow-2xl border border-slate-700/60 py-1 z-50">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-400 hover:bg-red-900/20">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    {{ $slot }}
</main>

<x-toast />

@livewireScripts
</body>
</html>
