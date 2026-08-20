{{--
    Shown in place of any org-facing page while the organization is suspended.

    Deliberately a dead end rather than a redirect loop: there is nothing the
    user can do in the app until billing is settled, so the page offers the two
    things they can still do — read the reason, and sign out.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription suspended - {{ config('app.name') }}</title>
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

            <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center mx-auto mb-5">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">Subscription suspended</h1>

            <p class="text-sm text-gray-600 dark:text-slate-400 mt-3 leading-relaxed">
                {{ $organization?->name ? $organization->name . '\'s' : 'Your organization\'s' }}
                subscription has expired. Please contact support to reactivate it.
            </p>

            @if($subscription?->next_renewal_at)
                <p class="text-xs text-gray-500 dark:text-slate-500 mt-3">
                    Renewal was due {{ $subscription->next_renewal_at->format('d M Y') }}.
                </p>
            @endif

            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-slate-800">
                <p class="text-xs text-gray-500 dark:text-slate-500">
                    You are still signed in as {{ auth()->user()?->email }}.
                </p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit"
                        class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
