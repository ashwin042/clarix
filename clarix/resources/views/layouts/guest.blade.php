<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=geist:400,500,600,700|geist-mono:400,500&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <meta name="color-scheme" content="light">
    <style>
        /* Auth screens only. Every rule is scoped under .auth-* so nothing here reaches the rest of the app. */
        /* Auth pages are light-only: keeps checkbox, autofill and scrollbars light even if the app set a theme class on <html>. */
        :root:root { color-scheme: light; }
        :root {
            --auth-bg: #fafafa;
            --auth-text: #18181b;
            --auth-muted: #52525b;
            --auth-faint: #71717a;
            --auth-line: #d4d4d8;
            --auth-field: #ffffff;
            --auth-accent: #4f46e5;
            --auth-accent-hover: #4338ca;
            --auth-accent-text: #4338ca;
            --auth-ring: rgba(79, 70, 229, .22);
            --auth-danger: #b91c1c;
            --auth-danger-bg: #fef2f2;
            --auth-ok: #15803d;
            --auth-ok-bg: #f0fdf4;
            --auth-ease: cubic-bezier(.23, 1, .32, 1);
        }

        .auth-body { font-family: 'Geist', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; background: var(--auth-bg); color: var(--auth-text); }
        .auth-mono { font-family: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, monospace; }

        /* ---- brand panel (dark by design) ---- */
        .auth-panel { position: relative; isolation: isolate; overflow: hidden; background: #09090b; color: #fafafa; }
        .auth-panel::before { /* indigo glow, top-left */
            content: ''; position: absolute; z-index: -2; inset: -20% auto auto -15%; width: 70%; aspect-ratio: 1;
            background: radial-gradient(closest-side, rgba(99, 102, 241, .34), transparent);
        }
        .auth-panel::after { /* dot grid fading out toward the bottom */
            content: ''; position: absolute; z-index: -1; inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, .16) 1px, transparent 1px);
            background-size: 22px 22px;
            -webkit-mask-image: radial-gradient(ellipse 80% 70% at 30% 20%, #000 20%, transparent 75%);
            mask-image: radial-gradient(ellipse 80% 70% at 30% 20%, #000 20%, transparent 75%);
        }
        .auth-arcs { position: absolute; z-index: -1; right: -18%; bottom: -22%; width: 78%; aspect-ratio: 1; color: rgba(165, 180, 252, .22); pointer-events: none;
            -webkit-mask-image: radial-gradient(closest-side, #000 55%, transparent 100%); mask-image: radial-gradient(closest-side, #000 55%, transparent 100%); }
        .auth-feature-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 10px; color: #c7d2fe; background: rgba(99, 102, 241, .14); box-shadow: inset 0 0 0 1px rgba(165, 180, 252, .22); }

        /* ---- logo mark ---- */
        .auth-mark { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: #4f46e5; color: #fff; font-weight: 700; font-size: 17px; box-shadow: inset 0 1px 0 rgba(255, 255, 255, .25), 0 1px 2px rgba(49, 46, 129, .4); }

        /* ---- form pieces ---- */
        .auth-field { display: block; }
        .auth-label-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
        .auth-label { font-size: 13px; font-weight: 500; color: var(--auth-text); }
        .auth-control { position: relative; }
        .auth-input {
            display: block; width: 100%; height: 44px; padding: 0 14px; font: inherit; font-size: 15px; color: var(--auth-text);
            background-color: var(--auth-field); border: 1px solid var(--auth-line); border-radius: 10px; box-shadow: none;
            transition: border-color 160ms var(--auth-ease), box-shadow 160ms var(--auth-ease), background-color 160ms var(--auth-ease);
        }
        .auth-input.has-toggle { padding-right: 48px; }
        .auth-input::placeholder { color: var(--auth-faint); }
        @media (hover: hover) and (pointer: fine) { .auth-input:hover:not(:disabled):not(:focus) { border-color: var(--auth-faint); } }
        .auth-input:focus { outline: none; border-color: var(--auth-accent); box-shadow: 0 0 0 4px var(--auth-ring); }
        .auth-input.is-invalid { border-color: var(--auth-danger); }
        .auth-input.is-invalid:focus { box-shadow: 0 0 0 4px color-mix(in srgb, var(--auth-danger) 22%, transparent); }
        .auth-input:disabled { opacity: .55; cursor: not-allowed; background-color: color-mix(in srgb, var(--auth-line) 25%, var(--auth-field)); }
        .auth-input:-webkit-autofill { -webkit-text-fill-color: var(--auth-text); -webkit-box-shadow: 0 0 0 1000px var(--auth-field) inset; }

        /* app.css restyles every <input> with !important when the app theme is dark. These rules out-rank it so the
           auth form never follows that theme. They repeat the control states above at higher specificity. */
        .auth-body input.auth-input, .auth-body .auth-check input { background-color: var(--auth-field) !important; border-color: var(--auth-line) !important; color: var(--auth-text) !important; }
        .auth-body input.auth-input::placeholder { color: var(--auth-faint) !important; }
        .auth-body input.auth-input:focus, .auth-body .auth-check input:focus { border-color: var(--auth-accent) !important; }
        .auth-body input.auth-input.is-invalid { border-color: var(--auth-danger) !important; }
        .auth-body input.auth-input:disabled { background-color: color-mix(in srgb, var(--auth-line) 25%, var(--auth-field)) !important; color: var(--auth-faint) !important; }
        .auth-body .auth-check input:checked { background-color: var(--auth-accent) !important; border-color: var(--auth-accent) !important; }
        @media (hover: hover) and (pointer: fine) { .auth-body input.auth-input:hover:not(:disabled):not(:focus) { border-color: var(--auth-faint) !important; } }

        .auth-toggle { position: absolute; top: 0; right: 0; display: grid; place-items: center; width: 44px; height: 44px; border-radius: 10px; color: var(--auth-faint); background: transparent; transition: color 160ms var(--auth-ease); }
        .auth-toggle:hover { color: var(--auth-text); }
        .auth-toggle:focus-visible { outline: 2px solid var(--auth-accent); outline-offset: -2px; }

        .auth-error { margin-top: 8px; font-size: 13px; line-height: 1.4; color: var(--auth-danger); }
        .auth-error li + li { margin-top: 2px; }
        .auth-error-wrap { list-style: none; padding: 0; }

        .auth-link { font-size: 13px; font-weight: 500; color: var(--auth-accent-text); border-radius: 6px; text-decoration: none; transition: color 160ms var(--auth-ease); }
        .auth-link:hover { text-decoration: underline; text-underline-offset: 3px; }
        .auth-link:focus-visible { outline: 2px solid var(--auth-accent); outline-offset: 3px; }

        .auth-check { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; color: var(--auth-muted); }
        .auth-check input { width: 18px; height: 18px; margin: 0; flex: none; border-radius: 5px; border: 1px solid var(--auth-line); background-color: var(--auth-field); color: var(--auth-accent); transition: box-shadow 160ms var(--auth-ease), border-color 160ms var(--auth-ease); }
        .auth-check input:focus { outline: none; box-shadow: 0 0 0 4px var(--auth-ring); border-color: var(--auth-accent); }
        .auth-check input:checked { background-color: var(--auth-accent); border-color: var(--auth-accent); }

        .auth-btn {
            position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 44px; padding: 0 18px;
            font: inherit; font-size: 15px; font-weight: 600; color: #fff; background: var(--auth-accent); border: 0; border-radius: 10px; cursor: pointer;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .22), 0 1px 2px rgba(49, 46, 129, .35);
            transition: background-color 160ms var(--auth-ease), transform 160ms var(--auth-ease), opacity 160ms var(--auth-ease);
        }
        @media (hover: hover) and (pointer: fine) { .auth-btn:hover:not(:disabled) { background: var(--auth-accent-hover); } }
        .auth-btn:active:not(:disabled) { transform: scale(.98); }
        .auth-btn:focus-visible { outline: 2px solid var(--auth-accent); outline-offset: 3px; }
        .auth-btn:disabled { cursor: progress; opacity: .8; }
        .auth-spinner { width: 16px; height: 16px; animation: auth-spin 700ms linear infinite; }
        @keyframes auth-spin { to { transform: rotate(360deg); } }

        .auth-notice { display: flex; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 14px; line-height: 1.45; color: var(--auth-ok); background: var(--auth-ok-bg); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--auth-ok) 28%, transparent); }

        /* ---- entrance: one short stagger on load ---- */
        @media (prefers-reduced-motion: no-preference) {
            .auth-rise { opacity: 0; transform: translateY(10px); animation: auth-rise 520ms var(--auth-ease) forwards; animation-delay: calc(var(--i, 0) * 60ms); }
            @keyframes auth-rise { to { opacity: 1; transform: none; } }
        }
        @media (prefers-reduced-motion: reduce) { .auth-spinner { animation-duration: 1.6s; } }
    </style>
</head>
<body class="auth-body antialiased transition-colors duration-300">

<div class="min-h-[100dvh] grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">

    {{-- Brand panel: desktop only. Mobile gets the compact mark above the form instead. --}}
    <aside class="auth-panel hidden lg:flex flex-col justify-between p-12 xl:p-16" aria-label="About Clarix">
        <svg class="auth-arcs" viewBox="0 0 400 400" fill="none" stroke="currentColor" stroke-width="1" aria-hidden="true">
            <circle cx="200" cy="200" r="60"/>
            <circle cx="200" cy="200" r="110"/>
            <circle cx="200" cy="200" r="160"/>
            <circle cx="200" cy="200" r="199" stroke-dasharray="2 6"/>
            <path d="M200 0v400M0 200h400" stroke-opacity=".45"/>
            <circle cx="310" cy="122" r="4" fill="currentColor" stroke="none"/>
            <circle cx="96" cy="262" r="4" fill="currentColor" stroke="none"/>
        </svg>

        <div class="auth-rise flex items-center gap-3" style="--i:0">
            <span class="auth-mark" aria-hidden="true">C</span>
            <span class="text-lg font-semibold tracking-tight">Clarix</span>
        </div>

        <div class="max-w-lg">
            <h2 class="auth-rise text-4xl xl:text-5xl font-semibold tracking-tighter leading-[1.05] text-balance" style="--i:1">
                Every task, every team, one clear view.
            </h2>
            <p class="auth-rise mt-5 text-base leading-relaxed text-zinc-400 max-w-[46ch]" style="--i:2">
                Plan work, track progress and keep attendance in a single workspace built for teams that ship.
            </p>

            <ul class="mt-10 space-y-5">
                <li class="auth-rise flex gap-4" style="--i:3">
                    <span class="auth-feature-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M15 4v16"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-100">Boards with files and status</p>
                        <p class="mt-0.5 text-sm text-zinc-400">Move tasks across stages and keep every attachment on the task.</p>
                    </div>
                </li>
                <li class="auth-rise flex gap-4" style="--i:4">
                    <span class="auth-feature-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-100">Attendance in local time</p>
                        <p class="mt-0.5 text-sm text-zinc-400">Clock in and out with times recorded in Nepal time.</p>
                    </div>
                </li>
                <li class="auth-rise flex gap-4" style="--i:5">
                    <span class="auth-feature-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-100">Access by role</p>
                        <p class="mt-0.5 text-sm text-zinc-400">Admins, supervisors, HR and staff each see what they need.</p>
                    </div>
                </li>
            </ul>
        </div>

        <p class="auth-mono text-xs text-zinc-500">clarixtech.online</p>
    </aside>

    {{-- Form column --}}
    <main class="flex flex-col min-h-[100dvh] lg:min-h-0 px-5 sm:px-8">
        <div class="flex-1 flex items-center justify-center py-10">
            <div class="w-full max-w-[400px]">

                <div class="auth-rise lg:hidden flex items-center gap-3 mb-10" style="--i:0">
                    <span class="auth-mark" aria-hidden="true">C</span>
                    <span class="text-lg font-semibold tracking-tight">Clarix</span>
                </div>

                {{ $slot }}
            </div>
        </div>

        <footer class="py-6 text-center text-xs" style="color: var(--auth-faint)">
            &copy; {{ date('Y') }} Clarix. Created By <a class="auth-link !text-xs" href="https://codesnextdoor.com/" target="_blank" rel="noopener">Code Next Door</a>
        </footer>
    </main>
</div>

@livewireScripts
</body>
</html>
