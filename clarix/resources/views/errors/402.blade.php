{{--
    Shown wherever a plan does not reach a feature.

    One page for every plan refusal, whether it came from the plan: middleware
    or from a component guarding itself against a direct Livewire call. Routing
    them all through the framework's own error view is what keeps a dozen call
    sites from inventing a dozen wordings.

    Standalone rather than rendered inside layouts.app: the sidebar calls
    auth()->user()->hasPermission() and friends unguarded, and running that
    inside the exception handler would risk turning a clean 402 into a 500.
    Unlike the suspended page this is not a dead end — only one feature is
    unavailable, so there is a way back to the rest of the app.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not included in your plan - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-slate-950 text-gray-900 dark:text-slate-200">

<div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 text-center">

            <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-5">
                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.9 5.4 5.6.2-4.4 3.5 1.6 5.4L12 14.4l-4.7 3.1 1.6-5.4L4.5 8.6l5.6-.2L12 3z"/>
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">Not included in your plan</h1>

            <p class="text-sm text-gray-600 dark:text-slate-400 mt-3 leading-relaxed">
                {{-- The specific sentence comes from the refusal; the fallback
                     covers a 402 raised from anywhere that did not set one. --}}
                {{ ($exception?->getMessage() ?: null) ?? "This feature isn't included in your current plan." }}
            </p>

            <p class="text-xs text-gray-500 dark:text-slate-500 mt-3">
                Contact your administrator to upgrade.
            </p>

            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-slate-800">
                <a href="{{ route('dashboard') }}"
                    class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                    Back to dashboard
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
