@props([
    'title',
    'description' => null,
])

{{--
    The shell every public marketing page renders inside.

    This used to be the top and bottom of home.blade.php. It carries only the
    CSS that more than one page needs — type roles, the nav, the window-chrome
    and float/rise motifs, and the footer gradient. Animation that belongs to a
    single page's mockups stays with that page, pushed onto the 'styles' stack
    below, so a page pays for nothing it does not draw.

    Marketing pages get Alpine from resources/js/marketing.js, never from
    app.js: authenticated pages load @livewireScripts, which ships its own
    Alpine, and a second instance on the same page makes Alpine throw.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:400,500,600|inter:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/marketing.js'])

    <style>
        /* ================================================================
           Type roles
           ================================================================ */
        /* In-page anchors (nav "Book a demo", the pricing CTAs) glide rather
           than jump. The header is static, so no scroll-margin is needed. */
        html { scroll-behavior: smooth; }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }

        .font-display { font-family: 'Fraunces', Georgia, 'Times New Roman', serif; }
        .font-mono-ui { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace; }

        .clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ================================================================
           Nav — checkbox-driven mobile menu, no JS
           ================================================================ */
        #nav-toggle:checked ~ .nav-panel { display: block; }
        #nav-toggle:checked ~ * .nav-icon-open  { display: none; }
        #nav-toggle:checked ~ * .nav-icon-close { display: block; }
        .nav-icon-close { display: none; }
        @media (min-width: 1024px) { .nav-panel { display: none !important; } }

        /* ================================================================
           Scene: glow, window chrome, 3D stacking
           ================================================================ */
        .scene-glow {
            background:
                radial-gradient(38% 46% at 50% 30%, rgba(99,102,241,.55) 0%, rgba(99,102,241,0) 70%),
                radial-gradient(32% 36% at 20% 44%, rgba(124,58,237,.40) 0%, rgba(124,58,237,0) 72%),
                radial-gradient(34% 40% at 80% 40%, rgba(255,178,122,.52) 0%, rgba(255,178,122,0) 72%),
                radial-gradient(52% 42% at 50% 66%, rgba(56,189,248,.22) 0%, rgba(56,189,248,0) 76%);
            filter: blur(64px);
        }

        .win-shadow {
            box-shadow:
                0 42px 84px -24px rgba(14,17,38,.30),
                0 12px 28px -12px rgba(14,17,38,.16),
                0 1px 0 0 rgba(255,255,255,.60) inset;
        }
        .win-shadow-dark {
            box-shadow:
                0 42px 84px -24px rgba(14,17,38,.42),
                0 12px 28px -12px rgba(14,17,38,.28);
        }

        @media (min-width: 1024px) {
            /* The float animation owns translateY on the wrapper, so the board's
               horizontal centering has to live here, on the inner element. */
            .tilt-terminal { transform: perspective(2200px) rotateY(7deg)  rotateX(3deg)  rotate(-5deg); }
            .tilt-board    { transform: translateX(-50%) perspective(2200px) rotateX(2deg) rotate(-1deg); }
            .tilt-thread   { transform: perspective(2200px) rotateY(-8deg) rotateX(3deg)  rotate(5deg); }
            .tilt-phone    { transform: perspective(2200px) rotateY(-5deg) rotate(4deg); }

            .float   { animation: float 7s ease-in-out infinite; }
            .float-2 { animation-delay: -2.3s; }
            .float-3 { animation-delay: -4.6s; }
            .float-4 { animation-delay: -1.2s; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }

        .rise   { animation: rise .8s cubic-bezier(.16,.84,.44,1) both; }
        .rise-2 { animation-delay: .10s; }
        .rise-3 { animation-delay: .18s; }
        .rise-4 { animation-delay: .28s; }
        @keyframes rise {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .scene-fade {
            background: linear-gradient(to bottom, rgba(252,252,253,0) 0%, rgba(252,252,253,.85) 55%, #FCFCFD 100%);
        }

        /* ================================================================
           Accordion — used by the homepage's 'Why Clarix' panel and by the
           shared FAQ component, so it lives here rather than with either.

           Height animates on grid-template-rows rather than max-height, so
           the panel opens to its real height with no guessed ceiling. The
           inner div is what actually clips.
           ================================================================ */
        .acc-panel {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows .34s cubic-bezier(.2,.8,.25,1);
        }
        .acc-panel > div { overflow: hidden; }
        .acc-panel--open { grid-template-rows: 1fr; }

        .acc-chev { transition: transform .28s ease; }
        .acc-chev--open { transform: rotate(180deg); }

        /* ================================================================
           Footer

           Diagonal indigo: washed out at the left edge, deepening across to
           the right. The radial pass is what lifts the left end — a plain
           linear ramp from a pale indigo reads as grey against white.
           ================================================================ */
        .site-footer {
            background:
                radial-gradient(115% 150% at 4% 46%, rgba(255,255,255,.17) 0%, rgba(255,255,255,0) 58%),
                linear-gradient(107deg, #6F69E7 0%, #574FE2 27%, #4136CE 60%, #2C2496 100%);
        }


        /* ================================================================
           Window 1 — terminal. 9s loop: type a prompt, then output lands.
           ================================================================ */
        .t-type {
            display: inline-block;
            overflow: hidden;
            white-space: nowrap;
            vertical-align: bottom;
            width: 20ch;
            animation: t-type 9s infinite;
        }
        @keyframes t-type {
            0%   { width: 0;    opacity: 1; animation-timing-function: steps(20, end); }
            20%  { width: 20ch; opacity: 1; }
            93%  { width: 20ch; opacity: 1; }
            97%  { width: 20ch; opacity: 0; }
            100% { width: 0;    opacity: 0; }
        }

        .t-out1 { animation: t-out1 9s infinite both; }
        @keyframes t-out1 {
            0%, 27%   { opacity: 0; transform: translateY(3px); }
            32%, 93%  { opacity: 1; transform: translateY(0); }
            97%, 100% { opacity: 0; transform: translateY(0); }
        }

        .t-out2 { animation: t-out2 9s infinite both; }
        @keyframes t-out2 {
            0%, 40%   { opacity: 0; transform: translateY(3px); }
            45%, 93%  { opacity: 1; transform: translateY(0); }
            97%, 100% { opacity: 0; transform: translateY(0); }
        }

        .caret { animation: blink 1.15s steps(1) infinite; }
        @keyframes blink { 0%, 50% { opacity: 1; } 50.01%, 100% { opacity: 0; } }

        /* ================================================================
           Reduced motion, for the motifs the shell itself owns. Each page
           holds its own loops at a resting frame in its own stack.
           ================================================================ */
        @media (prefers-reduced-motion: reduce) {
            .float, .rise, .caret,
            .t-type, .t-out1, .t-out2 { animation: none !important; }
            .acc-panel, .acc-chev { transition-duration: .01ms; }
            .rise    { opacity: 1; transform: none; }
            .t-type  { width: 20ch; opacity: 1; }
            .t-out1,
            .t-out2  { opacity: 1; transform: none; }
        }
    </style>

    @stack('styles')
</head>

<body class="bg-[#FCFCFD] font-sans text-[#0F1222] antialiased">

    <x-marketing.header />

    {{ $slot }}

    <x-marketing.footer />

</body>
</html>
