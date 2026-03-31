<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-200 transition-colors duration-300">

<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">

        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-xl mb-4">
                <span class="text-white font-bold text-lg">C</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clarix</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Task Management Platform</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm px-8 py-8">
            {{ $slot }}
        </div>

        <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-6">&copy; {{ date('Y') }} Clarix .Created By  <a href="https://codesnextdoor.com/" target="_blank">Code Next Door</a> .</p>
    </div>
</div>

@livewireScripts
</body>
</html>
