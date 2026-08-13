<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clarix, one portal for agency work</title>
    <meta name="description" content="Clarix is the portal where agencies run client work: brief a task, spend credits as it moves, keep every file attached, and give clients a live view.">

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
           Window 2 — task board. 11s loop:
             0.0–3.6s  pointer opens the unit filter, then closes it
             3.7–6.4s  CLX-1036 is dragged from In progress to Completed
           ================================================================ */
        .b-cursor { animation: b-cursor 11s infinite both; }
        @keyframes b-cursor {
            0%   { opacity: 0; transform: translate(54px, 34px) scale(1); }
            3%   { opacity: 1; transform: translate(54px, 34px) scale(1); }
            10%  { opacity: 1; transform: translate(0, 0) scale(1); }
            12%  { opacity: 1; transform: translate(0, 0) scale(.78); }
            15%  { opacity: 1; transform: translate(0, 0) scale(1); }
            24%  { opacity: 1; transform: translate(0, 0) scale(1); }
            26%  { opacity: 1; transform: translate(0, 0) scale(.78); }
            29%  { opacity: 1; transform: translate(0, 0) scale(1); }
            34%  { opacity: 0; transform: translate(44px, 28px) scale(1); }
            100% { opacity: 0; transform: translate(44px, 28px) scale(1); }
        }

        .b-menu { transform-origin: top center; pointer-events: none; animation: b-menu 11s infinite both; }
        @keyframes b-menu {
            0%, 12%   { opacity: 0; transform: translateY(-4px) scaleY(.94); }
            15%, 25%  { opacity: 1; transform: translateY(0)    scaleY(1); }
            28%, 100% { opacity: 0; transform: translateY(-4px) scaleY(.94); }
        }

        /* Column geometry: 4 columns, 12px grid padding, 12px gaps.
           Widths stay fluid so the flight lands correctly at any width. */
        .b-clone {
            position: absolute;
            /* 12px grid padding + 14px column header + 8px gap + 88px card + 8px gap */
            top: 130px;
            left: calc(24px + (100% - 60px) / 4);
            width: calc((100% - 60px) / 4);
            z-index: 20;
            animation: b-clone 11s infinite both;
        }
        @keyframes b-clone {
            0%, 34% {
                opacity: 0;
                transform: translate(0, 0) scale(1) rotate(0deg);
                box-shadow: 0 1px 2px rgba(16,18,34,.05);
            }
            37% {
                opacity: 1;
                transform: translate(0, 0) scale(1.06) rotate(2deg);
                box-shadow: 0 18px 32px -10px rgba(16,18,34,.38);
                animation-timing-function: cubic-bezier(.62,0,.30,1);
            }
            50% {
                opacity: 1;
                transform: translate(calc(200% + 24px), 0) scale(1.06) rotate(2deg);
                box-shadow: 0 18px 32px -10px rgba(16,18,34,.38);
            }
            54% {
                opacity: 1;
                transform: translate(calc(200% + 24px), 0) scale(1) rotate(0deg);
                box-shadow: 0 1px 2px rgba(16,18,34,.05);
            }
            58%, 100% {
                opacity: 0;
                transform: translate(calc(200% + 24px), 0) scale(1) rotate(0deg);
                box-shadow: 0 1px 2px rgba(16,18,34,.05);
            }
        }

        .b-src { animation: b-src 11s infinite both; }
        @keyframes b-src {
            0%, 33%  { opacity: 1; }
            37%, 92% { opacity: 0; }
            100%     { opacity: 1; }
        }

        .b-dest { animation: b-dest 11s infinite both; }
        @keyframes b-dest {
            0%, 53%  { opacity: 0; transform: scale(.96); }
            57%, 92% { opacity: 1; transform: scale(1); }
            100%     { opacity: 0; transform: scale(.96); }
        }

        /* ================================================================
           Window 3 — comment thread. 8s loop: typing dots, then a message.
           ================================================================ */
        .c-dots { animation: c-dots 8s infinite both; }
        @keyframes c-dots {
            0%, 6%    { opacity: 0; }
            10%, 38%  { opacity: 1; }
            42%, 100% { opacity: 0; }
        }

        .c-msg { animation: c-msg 8s infinite both; }
        @keyframes c-msg {
            0%, 42%   { opacity: 0; transform: translateY(4px); }
            48%, 90%  { opacity: 1; transform: translateY(0); }
            96%, 100% { opacity: 0; transform: translateY(4px); }
        }

        .c-dot { animation: c-bounce .9s ease-in-out infinite; }
        .c-dot:nth-child(2) { animation-delay: .15s; }
        .c-dot:nth-child(3) { animation-delay: .30s; }
        @keyframes c-bounce {
            0%, 60%, 100% { transform: translateY(0);    opacity: .40; }
            30%           { transform: translateY(-3px); opacity: 1; }
        }

        /* ================================================================
           Window 4 — phone. Same 8s clock as the thread, so the push
           notification lands just after the client's comment posts.
           ================================================================ */
        .p-notif { animation: p-notif 8s infinite both; }
        @keyframes p-notif {
            0%, 44%   { opacity: 0; margin-top: -40px; }
            52%, 92%  { opacity: 1; margin-top: 0; }
            98%, 100% { opacity: 0; margin-top: -40px; }
        }

        /* ================================================================
           Problem / Solution section — same card language as the hero
           windows, one step lighter since these sit flat on the page.
           ================================================================ */
        .card-shadow {
            box-shadow:
                0 18px 34px -16px rgba(14,17,38,.22),
                0 3px 10px -4px rgba(14,17,38,.10);
        }
        .toggle-shadow {
            box-shadow:
                0 1px 2px rgba(14,17,38,.10),
                0 4px 10px -4px rgba(14,17,38,.14);
        }

        /* ================================================================
           Platform arc — the rings dissolve downward, and the section
           floor ramps to the exact indigo the next section starts on, so
           the two blocks read as one surface.
           ================================================================ */
        /* ================================================================
           Pricing

           Written out here rather than as utilities: the cream/border
           palette and the sizing are one-offs, and mt-auto — which is what
           pins every CTA to its card's bottom edge — is not in the built
           stylesheet, so as a class it would silently do nothing.
           ================================================================ */
        .price-grid { align-items: stretch; }

        .price-card {
            position: relative;
            display: flex;
            flex-direction: column;
            border-radius: 18px;
            border: 1px solid #E9E4D8;
            background: #FBFAF6;
            padding: 24px 22px 22px;
        }
        /* Standard, the recommended tier: indigo-tinted edge and a lift. */
        .price-card--featured {
            border-color: #B9B2F0;
            background: #FCFBF9;
            box-shadow: 0 20px 44px -22px rgba(79, 70, 229, .45);
        }

        /* Fixed-height row so the plan names line up across all four cards
           whether or not a card carries the badge. */
        .price-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-height: 24px;
        }
        .price-name {
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6B7086;
        }
        .price-pill {
            border-radius: 999px;
            background: #4F46E5;
            padding: 3px 9px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: .03em;
            color: #fff;
            white-space: nowrap;
        }

        /* Baseline row so the struck monthly figure sits on the same line as
           the annual one it is being compared to, and wraps below it rather
           than overflowing on the narrowest cards. */
        .price-amount {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 9px;
            margin-top: 14px;
            font-size: 34px;
            line-height: 1.05;
            letter-spacing: -.02em;
            color: #17143A;
        }
        /* The monthly price the annual rate is measured against: small and
           grey, so the discounted figure is still the one read first. */
        .price-was {
            font-size: 15px;
            letter-spacing: 0;
            color: #9A9FB0;
            text-decoration: line-through;
        }
        .price-period {
            margin-top: 6px;
            font-size: 12.5px;
            color: #7A7F92;
        }
        .price-blurb {
            margin-top: 14px;
            font-size: 13px;
            line-height: 1.55;
            color: #4A4F63;
        }

        .price-rule {
            margin: 18px 0;
            height: 1px;
            background: #E6E1D4;
        }
        .price-card--featured .price-rule { background: #E2DEF6; }

        .price-features {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .price-features li {
            display: flex;
            gap: 9px;
            font-size: 12.5px;
            line-height: 1.5;
            color: #4A4F63;
        }
        /* Indented to the checked lines' text column (icon 14 + gap 9). */
        .price-features .price-lead {
            padding-left: 23px;
            font-weight: 600;
            color: #221E5C;
        }
        .price-check {
            width: 14px;
            height: 14px;
            flex: none;
            margin-top: 3px;
            color: #4F46E5;
        }

        /* The auto margin is what makes every button share a baseline: all
           the card's slack collects above it. */
        .price-cta {
            margin-top: auto;
            padding-top: 22px;
        }
        .price-btn {
            display: block;
            border-radius: 10px;
            background: #4F46E5;
            padding: 11px 14px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            transition: background-color .15s ease;
        }
        .price-btn:hover { background: #4338CA; }
        .price-btn:focus-visible {
            outline: 2px solid #4F46E5;
            outline-offset: 2px;
        }
        /* Enterprise: a different action, so a different weight of button. */
        .price-btn--dark { background: #221E5C; }
        .price-btn--dark:hover { background: #14113A; }
        .price-btn--dark:focus-visible { outline-color: #221E5C; }

        /* ================================================================
           Why Clarix — accordion + polaroid stack
           ================================================================ */

        /* Height animates on grid-template-rows rather than max-height, so
           the panel opens to its real height with no guessed ceiling. The
           inner div is what actually clips. */
        .acc-panel {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows .34s cubic-bezier(.2,.8,.25,1);
        }
        .acc-panel > div { overflow: hidden; }
        .acc-panel--open { grid-template-rows: 1fr; }

        .acc-chev { transition: transform .28s ease; }
        .acc-chev--open { transform: rotate(180deg); }

        /* This section is outside <main>, which is what clips stray transforms
           everywhere else on the page. A thrown card travels 620px sideways,
           so without this it would hand the page a horizontal scrollbar.
           clip rather than hidden: it must not become a scroll container. */
        .why-section {
            overflow-x: hidden;
            overflow-x: clip;
        }

        /* The stack sits in a fixed box so the section's height never moves as
           cards cycle; the cards themselves are absolutely placed and offset
           from it. The subtracted width is the deepest card's 45px offset, so
           the resting pile stays inside the column on narrow screens. */
        .photo-stack {
            position: relative;
            width: 100%;
            max-width: min(330px, calc(100% - 52px));
            aspect-ratio: 5 / 6;
        }
        .polaroid {
            position: absolute;
            inset: 0;
            border-radius: 3px;
            background: #fff;
            /* Wide bottom border: the caption strip that makes it a polaroid. */
            padding: 13px 13px 46px;
            box-shadow:
                0 26px 50px -20px rgba(14,17,38,.42),
                0 6px 14px -6px rgba(14,17,38,.22);
            transform-origin: 50% 62%;
            will-change: transform;
            -webkit-user-select: none;
            user-select: none;
            /* Vertical scrolling still belongs to the page; sideways is ours. */
            touch-action: pan-y;
        }
        .polaroid--front { cursor: grab; }
        .polaroid--front:active { cursor: grabbing; }
        .polaroid img {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 1px;
            background: #EDEBF4;
            object-fit: cover;
            -webkit-user-drag: none;
        }
        .polaroid figcaption {
            position: absolute;
            inset: auto 13px 15px;
            font-size: 11.5px;
            letter-spacing: .02em;
            color: #8A8FA0;
        }

        @media (prefers-reduced-motion: reduce) {
            .acc-panel, .acc-chev { transition-duration: .01ms; }
        }

        /* ================================================================
           Schedule a demo
           ================================================================ */
        .demo-card {
            border: 1px solid #E9E4D8;
            border-radius: 20px;
            background: #FBFAF6;
        }

        .demo-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #17143A;
        }
        .demo-req {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #6F66E0;
        }

        .demo-field {
            display: block;
            width: 100%;
            border: 1px solid #E4E1EC;
            /* Always 3px so the required accent below can only change the
               colour — switching the width here would shift the text. */
            border-left-width: 3px;
            border-radius: 10px;
            background: #fff;
            padding: 10px 13px;
            font-size: 14px;
            color: #17143A;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .demo-field::placeholder { color: #AEB2C0; }
        /* Empty — or not yet valid — required fields keep an indigo edge, so
           what is still outstanding shows before anyone hits Send. */
        .demo-field:required:invalid { border-left-color: #4F46E5; }
        .demo-field:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, .14);
        }

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

        select.demo-field {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 34px;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%237A8092' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><path d='M4 6.25 8 10.25 12 6.25'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 15px;
        }

        /* ================================================================
           Plan comparison

           One <table> carries both the plan headers and the rows, so the
           column geometry is solved once by the table layout and the header
           can't drift out of line with the values under it.
           ================================================================ */
        .cmp-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .cmp {
            width: 100%;
            /* Below this the columns would squash, so the wrapper scrolls
               instead. Roughly the point at which 'Dedicated manager' stops
               fitting on one line. */
            min-width: 780px;
            border-collapse: collapse;
            text-align: left;
        }
        .cmp th,
        .cmp td { padding: 16px; }

        /* Column rules. Applied to every cell of every row — header, group
           band and feature row alike — so each line runs the full height of
           the table rather than stopping at the body. Column 2 is where
           labels end and values begin, so it gets the darker one. */
        .cmp th:nth-child(2),
        .cmp td:nth-child(2) { border-left: 1px solid #C8C2AF; }
        .cmp th:nth-child(n+3),
        .cmp td:nth-child(n+3) { border-left: 1px solid #EDEAE0; }

        .cmp thead th {
            vertical-align: bottom;
            padding-bottom: 22px;
        }
        .cmp-title {
            font-size: 30px;
            font-weight: 400;
            line-height: 1.1;
            letter-spacing: -.02em;
            color: #17143A;
        }
        .cmp-plan {
            display: block;
            font-size: 15px;
            font-weight: 600;
            color: #221E5C;
        }
        .cmp-link {
            display: inline-block;
            margin-top: 7px;
            font-size: 12.5px;
            font-weight: 600;
            color: #4F46E5;
        }
        .cmp-link:hover { text-decoration: underline; }
        .cmp-link:focus-visible {
            outline: 2px solid #4F46E5;
            outline-offset: 2px;
            border-radius: 3px;
        }

        /* Group band. The row carries four empty cells rather than one
           colspan, so the column rules pass straight through the cream and
           the grid reads as continuous down the whole table. */
        .cmp-group th,
        .cmp-group td {
            background: #FBFAF6;
            border-top: 1px solid #E4DFD0;
            border-bottom: 1px solid #E4DFD0;
            padding-top: 11px;
            padding-bottom: 11px;
        }
        .cmp-group th {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #221E5C;
        }

        .cmp-row th,
        .cmp-row td { border-bottom: 1px solid #E7E3D8; }
        .cmp-feat {
            font-size: 13px;
            font-weight: 500;
            color: #26293A;
        }
        .cmp-val {
            font-size: 13px;
            font-weight: 500;
            color: #2E3244;
            text-align: center;
        }
        .cmp-yes { width: 17px; height: 17px; color: #4338CA; }
        /* Deliberately still light — "not included" should recede. The
           sr-only text beside each one carries the meaning regardless. */
        .cmp-no  { width: 14px; height: 14px; color: #B0B5C4; }

        /* Circled '?' — hover for the title, focusable so it isn't
           mouse-only, and labelled for screen readers. */
        .cmp-hint {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            margin-left: 6px;
            border: 1px solid #C3C7D4;
            border-radius: 999px;
            font-size: 9.5px;
            font-weight: 700;
            line-height: 1;
            color: #8A8FA3;
            cursor: help;
            vertical-align: 1px;
        }
        .cmp-hint:hover,
        .cmp-hint:focus-visible {
            border-color: #4F46E5;
            color: #4F46E5;
        }

        /* ================================================================
           AXOKAI cards

           Icon pinned to the top, copy pushed to the bottom, with real air
           between the two. Equal heights come from the grid — align-items
           defaults to stretch, restated here because it is load-bearing —
           and the min-height sets the floor so four short lines can't
           collapse the row into something squat.

           This lives here rather than in utilities because neither mt-auto
           nor items-stretch is in the built stylesheet; as classes they
           would silently do nothing.
           ================================================================ */
        .axokai-grid { align-items: stretch; }
        .axokai-card {
            display: flex;
            flex-direction: column;
            min-height: 260px;
        }
        .axokai-card > p {
            margin-top: auto;   /* bottom-aligns the copy */
            padding-top: 56px;  /* the floor on the gap under the icon */
        }

        /* ================================================================
           Ring diagram scale

           One variable drives the diagram box, the height of the space it
           occupies, the bleed, and the particle field, so those four can
           never drift out of register. The box is 1200x600 at scale 1; the
           ladder below keeps it inside the section's 24px padding at every
           breakpoint it is visible at (it is display:none under 768px):

               md  768 -> 696px wide in 720 available
               lg 1024 -> 936px wide in 976 available
               xl 1280 -> 1200px wide in 1232 available

           These live here rather than as Tailwind arbitrary values because
           they are derived numbers, and Tailwind only emits classes it can
           find as literal text at build time.
           ================================================================ */
        :root { --ring-scale: .58; }
        @media (min-width: 1024px) { :root { --ring-scale: .78; } }
        @media (min-width: 1280px) { :root { --ring-scale: 1; } }

        /* The stage reserves exactly the height the scaled box occupies, so
           the circle's centre lands on the section's bottom edge — the
           invariant the particle field downstairs is pinned to. */
        .ring-stage { height: calc(600px * var(--ring-scale)); }
        .ring-box {
            width: 1200px;
            height: 600px;
            transform-origin: top center;
            transform: translateX(-50%) scale(var(--ring-scale));
        }
        /* Same scale, but pinned by its centre rather than its top edge. */
        .ring-field { transform: translate(-50%, -50%) scale(var(--ring-scale)); }

        /* The bleed paints OVER the arcs and the orb (z-20) but under the
           node icons (z-30). At the waterline its alpha is 1, so the orb and
           the page behind it resolve to the identical colour — no seam. */
        /* Short and late: the filled bands carry the colour now, so the bleed
           only has to sink the last 200px into the block below. */
        .bleed {
            /* Scales with the diagram so the waterline keeps its proportion
               to the bands it is sinking. 480 = the original 320 x 1.5. */
            height: calc(480px * var(--ring-scale));
            background: linear-gradient(to bottom,
                rgba(79,70,229,0)    0%,
                rgba(79,70,229,.04) 58%,
                rgba(79,70,229,.22) 82%,
                rgba(79,70,229,.70) 94%,
                rgba(79,70,229,1)  100%);
        }

        /* Orbit backdrop on the AXOKAI block. One turn every 50s — slow enough
           to read as drift rather than spin. The wrapper carries the
           positioning transform, so this element's own transform is free to be
           nothing but the rotation. */
        .orbit {
            transform-origin: 50% 50%;
            animation: orbit 50s linear infinite;
        }
        @keyframes orbit {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        /* Every trail bottoms out inside the text column — $R3 just under the
           badge, $R2 at the paragraph, $R1 on the button — so z-order alone
           isn't enough to keep them out of the way: the button's fill is
           transparent, and dots showing through it read as sitting on top.
           This fades the field to a whisper behind the copy and back to full
           strength past it. Alpha here multiplies the dots' own opacity. */
        .orbit-field {
            -webkit-mask-image: radial-gradient(600px 330px at 50% 270px,
                rgba(0,0,0,.18) 0%, rgba(0,0,0,.45) 55%, #000 88%);
                    mask-image: radial-gradient(600px 330px at 50% 270px,
                rgba(0,0,0,.18) 0%, rgba(0,0,0,.45) 55%, #000 88%);
        }

        /* ================================================================
           Reduced motion: hold every loop at a sensible resting frame.
           ================================================================ */
        @media (prefers-reduced-motion: reduce) {
            .float, .rise, .caret, .c-dot,
            .t-type, .t-out1, .t-out2,
            .b-cursor, .b-menu, .b-clone, .b-src, .b-dest,
            .c-dots, .c-msg, .p-notif,
            .orbit { animation: none !important; }

            .rise    { opacity: 1; transform: none; }
            .t-type  { width: 20ch; opacity: 1; }
            .t-out1,
            .t-out2  { opacity: 1; transform: none; }
            .b-cursor,
            .b-menu,
            .b-clone,
            .b-dest  { opacity: 0; }
            .b-src   { opacity: 1; }
            .c-dots  { opacity: 0; }
            .c-msg   { opacity: 1; transform: none; }
            .p-notif { opacity: 1; margin-top: 0; }
        }
    </style>
</head>

<body class="bg-[#FCFCFD] font-sans text-[#0F1222] antialiased">

    @php
        // ---- Nav dropdowns ----------------------------------------------
        // [label, description, href]. A null description renders the row as a
        // plain bold line, which is why Resources sits in a narrower panel.
        // Every href is a placeholder except Pricing and AXOKAI; those pages
        // don't exist. An href starting http is treated as leaving the site and
        // renders with target="_blank" in both the desktop and mobile menus.
        $navMenus = [
            'Product' => ['width' => 'w-[332px]', 'items' => [
                ['Task Boards',            'Plan and track work in one place',    '#'],
                ['Client Portal',          'Give clients a live view of delivery', '#'],
                ['AI Automation (AXOKAI)', 'Let AI handle the busywork',           'https://axokai.codesnextdoor.com/'],
                ['File Management',        'Keep every file attached to its task', '#'],
                ['Credits & Billing',      'Track spend as work moves',            '#'],
            ]],
            'Solutions' => ['width' => 'w-[300px]', 'items' => [
                ['For Agencies',    'Manage multiple clients and teams',   '#'],
                ['For Freelancers', 'Simplify solo project tracking',      '#'],
                ['For Enterprises', 'Scale delivery across departments',   '#'],
            ]],
            'Resources' => ['width' => 'w-[210px]', 'items' => [
                ['Blog',             null, '#'],
                ['Documentation',    null, '#'],
                ['Customer Stories', null, '#'],
                ['Help Center',      null, '#'],
            ]],
        ];

        // Nav entries with no menu behind them.
        $navLinks = [['Pricing', '#pricing']];
    @endphp

    {{-- ============================ Header ============================ --}}
    <header class="relative z-50">
        <input type="checkbox" id="nav-toggle" class="peer sr-only" aria-label="Toggle navigation">

        <div class="mx-auto grid max-w-7xl grid-cols-[1fr_auto_1fr] items-center gap-4 px-6 py-5">

            {{-- logo --}}
            <a href="{{ url('/home') }}" class="flex items-center justify-self-start">
                <span class="text-[17px] font-semibold tracking-tight">Clarix</span>
            </a>

            {{-- centered links. One x-data for the whole bar so opening a
                 second menu closes the first by construction. --}}
            <nav class="hidden items-center lg:flex" aria-label="Main"
                 x-data="{ open: null }" @keydown.escape.window="open = null">

                @foreach ($navMenus as $label => $menu)
                    <div class="relative"
                         @mouseenter="open = '{{ $label }}'"
                         @mouseleave="open = null"
                         @click.outside="open === '{{ $label }}' && (open = null)">

                        <button type="button" aria-haspopup="true" aria-expanded="false"
                                @click="open = open === '{{ $label }}' ? null : '{{ $label }}'"
                                :aria-expanded="open === '{{ $label }}'"
                                class="flex items-center gap-1 rounded-full px-3.5 py-2 text-[14px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                :class="open === '{{ $label }}' && 'bg-black/[.04] text-[#0F1222]'">
                            {{ $label }}
                            <svg class="h-3 w-3 text-[#A1A6B4] transition-transform duration-200"
                                 :class="{ 'rotate-180': open === '{{ $label }}' }"
                                 viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 4.5 3 3 3-3"/></svg>
                        </button>

                        <div x-show="open === '{{ $label }}'" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1"
                             class="absolute left-1/2 top-full z-50 mt-1.5 -translate-x-1/2 rounded-2xl border border-black/[.07] bg-white p-2 shadow-[0_20px_44px_-18px_rgba(14,17,38,.34),0_2px_8px_-4px_rgba(14,17,38,.12)] {{ $menu['width'] }}">
                            @foreach ($menu['items'] as [$name, $desc, $href])
                                <a href="{{ $href }}" @if (str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif
                                   class="block rounded-xl px-3 py-2.5 transition hover:bg-black/[.035] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    <span class="block text-[13.5px] font-semibold tracking-tight text-[#0F1222]">{{ $name }}</span>
                                    @if ($desc)
                                        <span class="mt-0.5 block text-[12.5px] leading-snug text-[#7A8092]">{{ $desc }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @foreach ($navLinks as [$label, $href])
                    <a href="{{ $href }}"
                       class="flex items-center rounded-full px-3.5 py-2 text-[14px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
            <span class="lg:hidden"></span>

            {{-- login + hamburger --}}
            <div class="flex items-center gap-2 justify-self-end">
                {{-- /home is public, so a signed-in visitor can land here. They
                     get one way back into the app instead of a sign-in prompt
                     and a sales CTA neither of which applies to them. --}}
                @guest
                    <a href="{{ route('login') }}"
                       class="rounded-full bg-[#0F1222] px-4 py-2 text-[14px] font-medium text-white transition hover:bg-[#252a40] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Log in
                    </a>
                    {{-- Hidden on the narrowest screens, where it would crowd the
                         hamburger; the mobile panel carries it instead. --}}
                    <a href="#schedule-demo"
                       class="hidden rounded-full border border-[#0F1222]/[.20] bg-white px-4 py-2 text-[14px] font-medium text-[#0F1222] transition hover:border-[#0F1222]/45 hover:bg-black/[.02] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:block">
                        Book a demo
                    </a>
                @endguest

                @auth
                    <a href="{{ route('dashboard') }}"
                       class="rounded-full bg-[#0F1222] px-4 py-2 text-[14px] font-medium text-white transition hover:bg-[#252a40] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Dashboard
                    </a>
                @endauth
                <label for="nav-toggle"
                       class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full text-[#4A4F63] transition hover:bg-black/[.04] lg:hidden">
                    <svg class="nav-icon-open h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M3.5 6h13M3.5 10h13M3.5 14h13"/></svg>
                    <svg class="nav-icon-close h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="m5.5 5.5 9 9M14.5 5.5l-9 9"/></svg>
                    <span class="sr-only">Menu</span>
                </label>
            </div>
        </div>

        {{-- mobile panel --}}
        <div class="nav-panel hidden border-y border-black/[.06] bg-white px-6 py-3 lg:hidden">
            {{-- Flattened: every menu's items listed under its own heading,
                 since there is no room for hover panels here. --}}
            <nav class="flex flex-col" aria-label="Main, mobile">
                @foreach ($navMenus as $label => $menu)
                    <span class="px-2 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-[.1em] text-[#A1A6B4]">{{ $label }}</span>
                    @foreach ($menu['items'] as [$name, $desc, $href])
                        <a href="{{ $href }}" @if (str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif class="rounded-lg px-2 py-2 text-[14.5px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222]">{{ $name }}</a>
                    @endforeach
                @endforeach

                <span class="mt-3 border-t border-black/[.06]"></span>
                @foreach ($navLinks as [$label, $href])
                    <a href="{{ $href }}" class="mt-2 rounded-lg px-2 py-2.5 text-[15px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222]">{{ $label }}</a>
                @endforeach
                @guest
                    <a href="#schedule-demo" class="mt-2 rounded-lg px-2 py-2.5 text-[15px] font-medium text-[#0F1222] transition hover:bg-black/[.04] sm:hidden">Book a demo</a>
                @endguest
            </nav>
        </div>
    </header>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden pb-4">

        <div class="relative z-10 mx-auto max-w-3xl px-6 pt-10 text-center sm:pt-16">

            <h1 class="rise font-display text-[42px] font-normal leading-[1.02] tracking-[-0.025em] sm:text-6xl lg:text-[76px]">
                With clear process,<br class="hidden sm:block"> comes clean delivery.
            </h1>

            <p class="rise rise-2 mx-auto mt-6 max-w-xl text-[15px] leading-relaxed text-[#5B6076] sm:mt-7 sm:text-[17px]">
                Clarix is an AI-powered platform for agency work. Brief a task, let automation
                track it as it moves, keep every file attached, and give clients a live view of
                what's shipping.
            </p>

            <div class="rise rise-3 mt-8 flex flex-col items-center justify-center gap-3 sm:mt-10 sm:flex-row">
                <a href="{{ route('login') }}"
                   class="inline-flex w-full items-center justify-center rounded-full bg-indigo-600 px-6 py-3 text-[15px] font-semibold text-white shadow-[0_10px_24px_-8px_rgba(79,70,229,.7)] transition hover:bg-indigo-700 hover:shadow-[0_14px_30px_-8px_rgba(79,70,229,.8)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                    Get started
                </a>
                <a href="#schedule-demo"
                   class="inline-flex w-full items-center justify-center rounded-full border border-[#0F1222]/[.14] bg-white px-6 py-3 text-[15px] font-semibold text-[#0F1222] transition hover:border-[#0F1222]/25 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-auto">
                    Book a demo
                </a>
            </div>
        </div>

        {{-- ======================= Floating windows ===================== --}}
        <div class="rise rise-4 relative mt-14 px-6 sm:mt-20 lg:mt-24 lg:px-0">

            <div aria-hidden="true"
                 class="pointer-events-none absolute inset-x-[-10%] -top-40 h-[780px] scene-glow lg:inset-x-0"></div>

            <div class="relative mx-auto lg:h-[480px] lg:max-w-[1200px] lg:overflow-hidden">
                <div class="flex flex-col items-center gap-10 lg:block">

                    {{-- ------------------ 1. Task board ------------------ --}}
                    <div class="float w-full max-w-[560px] lg:absolute lg:left-1/2 lg:top-[16px] lg:z-30 lg:w-[600px] lg:max-w-none">
                        <div class="tilt-board overflow-hidden rounded-xl border border-black/[.07] bg-white win-shadow">

                            {{-- chrome --}}
                            <div class="flex items-center gap-3 border-b border-black/[.06] bg-[#F7F7F9] px-3.5 py-2.5">
                                <div class="flex gap-1.5">
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#FF5F57]"></span>
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#FEBC2E]"></span>
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#28C840]"></span>
                                </div>
                                <div class="mx-auto rounded-md bg-white px-3 py-[3px] font-mono-ui text-[10px] text-[#8A8F9E] ring-1 ring-black/[.05]">
                                    clarix.app/tasks
                                </div>
                            </div>

                            {{-- toolbar, with the animated unit filter --}}
                            <div class="relative z-30 flex items-center justify-between border-b border-black/[.05] px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-[13px] font-semibold">Tasks</span>
                                    <span class="rounded-full bg-black/[.05] px-1.5 py-0.5 font-mono-ui text-[9px] text-[#5B6076]">18</span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <span class="relative">
                                        <span class="flex items-center gap-1 rounded-md border border-black/[.07] bg-white px-2 py-1 text-[10px] text-[#5B6076]">
                                            All units
                                            <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 4.5 3 3 3-3"/></svg>
                                        </span>

                                        {{-- dropdown --}}
                                        <span class="b-menu absolute left-0 top-[calc(100%+5px)] z-40 block w-[112px] rounded-lg border border-black/[.08] bg-white p-1 shadow-[0_12px_28px_-8px_rgba(16,18,34,.28)]">
                                            <span class="flex items-center justify-between rounded-md bg-[#EEF0FF] px-2 py-1 text-[10px] font-medium text-indigo-700">
                                                All units
                                                <svg class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                            </span>
                                            <span class="block rounded-md px-2 py-1 text-[10px] text-[#4A4F63]">Northwind</span>
                                            <span class="block rounded-md px-2 py-1 text-[10px] text-[#4A4F63]">Aurora</span>
                                            <span class="block rounded-md px-2 py-1 text-[10px] text-[#4A4F63]">Meridian</span>
                                        </span>

                                        {{-- fake pointer --}}
                                        <svg class="b-cursor pointer-events-none absolute left-[30px] top-[15px] z-50 h-4 w-4 drop-shadow-[0_2px_3px_rgba(16,18,34,.35)]"
                                             viewBox="0 0 16 16" aria-hidden="true">
                                            <path d="M2 1.4 13 8.2l-4.7.6-2.2 4.3z" fill="#0F1222" stroke="#fff" stroke-width="1.1" stroke-linejoin="round"/>
                                        </svg>
                                    </span>

                                    <span class="rounded-md border border-black/[.07] px-2 py-1 text-[10px] text-[#5B6076]">This week</span>
                                    <span class="rounded-md bg-indigo-600 px-2.5 py-1 text-[10px] font-medium text-white">New task</span>
                                </div>
                            </div>

                            {{-- columns --}}
                            <div class="relative grid grid-cols-2 gap-3 bg-[#FAFAFC] p-3 sm:grid-cols-4">

                                {{-- Pending --}}
                                <div class="space-y-2">
                                    <div class="flex items-center gap-1.5 px-0.5 pb-0.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#94A3B8]"></span>
                                        <span class="text-[10px] font-semibold text-[#5B6076]">Pending</span>
                                        <span class="ml-auto font-mono-ui text-[9px] text-[#A1A6B4]">4</span>
                                    </div>

                                    <div class="h-[88px] overflow-hidden rounded-lg border border-black/[.06] bg-white p-2.5 shadow-[0_1px_2px_rgba(16,18,34,.05)]">
                                        <div class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1042</div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Landing page copy, Q3 refresh</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#EEF0FF] px-1.5 py-0.5 text-[9px] font-medium text-indigo-700">Northwind</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">8 cr</span>
                                        </div>
                                    </div>

                                    <div class="h-[88px] overflow-hidden rounded-lg border border-black/[.06] bg-white p-2.5 shadow-[0_1px_2px_rgba(16,18,34,.05)]">
                                        <div class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1039</div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Blog: the RFP checklist</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#FFF1E7] px-1.5 py-0.5 text-[9px] font-medium text-[#B45309]">Aurora</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">5 cr</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- In progress --}}
                                <div class="space-y-2">
                                    <div class="flex items-center gap-1.5 px-0.5 pb-0.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                        <span class="text-[10px] font-semibold text-[#5B6076]">In progress</span>
                                        <span class="ml-auto font-mono-ui text-[9px] text-[#A1A6B4]">3</span>
                                    </div>

                                    <div class="h-[88px] overflow-hidden rounded-lg border border-black/[.06] bg-white p-2.5 shadow-[0_1px_2px_rgba(16,18,34,.05)]">
                                        <div class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1031</div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Product tour script</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#EEF0FF] px-1.5 py-0.5 text-[9px] font-medium text-indigo-700">Northwind</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">6 cr</span>
                                        </div>
                                    </div>

                                    {{-- the card that moves --}}
                                    <div class="b-src h-[88px] overflow-hidden rounded-lg border border-indigo-200 bg-white p-2.5 shadow-[0_2px_8px_rgba(79,70,229,.10)]">
                                        <div class="flex items-center gap-1">
                                            <span class="h-1 w-1 rounded-full bg-[#EF4444]"></span>
                                            <span class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1036</span>
                                        </div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Case study, Meridian rollout</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#E9FBF3] px-1.5 py-0.5 text-[9px] font-medium text-[#0E7A55]">Meridian</span>
                                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[7px] font-semibold text-white">AK</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Ready for review --}}
                                <div class="space-y-2">
                                    <div class="flex items-center gap-1.5 px-0.5 pb-0.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        <span class="text-[10px] font-semibold text-[#5B6076]">Ready for review</span>
                                        <span class="ml-auto font-mono-ui text-[9px] text-[#A1A6B4]">2</span>
                                    </div>

                                    <div class="h-[88px] overflow-hidden rounded-lg border border-black/[.06] bg-white p-2.5 shadow-[0_1px_2px_rgba(16,18,34,.05)]">
                                        <div class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1028</div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Email sequence, 5 parts</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#FFF1E7] px-1.5 py-0.5 text-[9px] font-medium text-[#B45309]">Aurora</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">10 cr</span>
                                        </div>
                                    </div>

                                    <div class="h-[88px] overflow-hidden rounded-lg border border-black/[.06] bg-white p-2.5 shadow-[0_1px_2px_rgba(16,18,34,.05)]">
                                        <div class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1025</div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Pricing page rewrite</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#E9FBF3] px-1.5 py-0.5 text-[9px] font-medium text-[#0E7A55]">Meridian</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">7 cr</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Completed --}}
                                <div class="space-y-2">
                                    <div class="flex items-center gap-1.5 px-0.5 pb-0.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        <span class="text-[10px] font-semibold text-[#5B6076]">Completed</span>
                                        <span class="ml-auto font-mono-ui text-[9px] text-[#A1A6B4]">9</span>
                                    </div>

                                    <div class="h-[88px] overflow-hidden rounded-lg border border-black/[.06] bg-white p-2.5 opacity-90 shadow-[0_1px_2px_rgba(16,18,34,.05)]">
                                        <div class="flex items-center justify-between">
                                            <span class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1019</span>
                                            <svg class="h-3 w-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        </div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug text-[#5B6076] line-through decoration-[#C8CCD6]">Whitepaper, final edit</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#E9FBF3] px-1.5 py-0.5 text-[9px] font-medium text-[#0E7A55]">Meridian</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">14 cr</span>
                                        </div>
                                    </div>

                                    {{-- where the moved card lands --}}
                                    <div class="b-dest h-[88px] overflow-hidden rounded-lg border border-emerald-200 bg-white p-2.5 shadow-[0_2px_8px_rgba(16,163,110,.12)]">
                                        <div class="flex items-center justify-between">
                                            <span class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1036</span>
                                            <svg class="h-3 w-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        </div>
                                        <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug text-[#5B6076] line-through decoration-[#C8CCD6]">Case study, Meridian rollout</div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="rounded bg-[#E9FBF3] px-1.5 py-0.5 text-[9px] font-medium text-[#0E7A55]">Meridian</span>
                                            <span class="font-mono-ui text-[9px] text-[#5B6076]">12 cr</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- the card in flight (4-column layouts only) --}}
                                <div class="b-clone hidden h-[88px] overflow-hidden rounded-lg border border-indigo-300 bg-white p-2.5 sm:block" aria-hidden="true">
                                    <div class="flex items-center gap-1">
                                        <span class="h-1 w-1 rounded-full bg-[#EF4444]"></span>
                                        <span class="font-mono-ui text-[9px] text-[#A1A6B4]">CLX-1036</span>
                                    </div>
                                    <div class="clamp-2 mt-1 text-[11px] font-medium leading-snug">Case study, Meridian rollout</div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="rounded bg-[#E9FBF3] px-1.5 py-0.5 text-[9px] font-medium text-[#0E7A55]">Meridian</span>
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-indigo-600 text-[7px] font-semibold text-white">AK</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ------------------ 2. Terminal -------------------- --}}
                    <div class="float float-2 w-full max-w-[560px] lg:absolute lg:left-[20px] lg:top-[86px] lg:z-20 lg:w-[440px] lg:max-w-none">
                        <div class="tilt-terminal overflow-hidden rounded-xl border border-white/[.06] bg-[#12141F] win-shadow-dark">

                            <div class="flex items-center gap-3 border-b border-white/[.07] bg-[#191C29] px-3.5 py-2.5">
                                <div class="flex gap-1.5">
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#FF5F57]"></span>
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#FEBC2E]"></span>
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#28C840]"></span>
                                </div>
                                <div class="mx-auto font-mono-ui text-[10px] text-[#8A90A6]">clarix: claude</div>
                            </div>

                            <div class="space-y-[7px] p-4 font-mono-ui text-[10.5px] leading-[1.55]">
                                <div><span class="text-[#4ADE80]">➜</span> <span class="text-[#8A90A6]">clarix</span> <span class="text-[#E4E6F0]">claude</span></div>

                                <div class="pt-1 text-[#8A90A6]">&gt; the credit ledger charges revisions twice</div>

                                <div class="pt-1.5">
                                    <span class="text-indigo-400">●</span>
                                    <span class="text-[#E4E6F0]"> Read</span>
                                    <span class="text-[#8A90A6]">  app/Services/CreditLedger.php</span>
                                </div>
                                <div>
                                    <span class="text-indigo-400">●</span>
                                    <span class="text-[#E4E6F0]"> Grep</span>
                                    <span class="text-[#8A90A6]">  "revision" → 6 matches, 3 files</span>
                                </div>

                                <div class="py-1 pl-3 text-[#A8AEC4]">
                                    settleTask() writes a ledger entry for the revision
                                    <span class="text-[#E4E6F0]">and</span> for its parent task.
                                </div>

                                <div>
                                    <span class="text-indigo-400">●</span>
                                    <span class="text-[#E4E6F0]"> Edit</span>
                                    <span class="text-[#8A90A6]">  CreditLedger.php</span>
                                    <span class="text-[#4ADE80]">+6</span> <span class="text-[#F87171]">-2</span>
                                </div>

                                {{-- the line that types itself --}}
                                <div class="pt-2">
                                    <span class="text-[#8A90A6]">&gt;</span>
                                    <span class="t-type text-[#E4E6F0]">run the ledger tests</span><span class="caret ml-px inline-block h-[13px] w-[7px] translate-y-[2px] bg-[#E4E6F0]"></span>
                                </div>

                                <div class="t-out1">
                                    <span class="text-indigo-400">●</span>
                                    <span class="text-[#E4E6F0]"> Bash</span>
                                    <span class="text-[#8A90A6]">  php artisan test --filter=Ledger</span>
                                </div>

                                <div class="t-out2 pl-3">
                                    <span class="rounded bg-[#14532D] px-1.5 py-[1px] text-[#86EFAC]">PASS</span>
                                    <span class="text-[#8A90A6]"> 9 passed, 24 assertions</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ------------------ 3. Comment thread -------------- --}}
                    <div class="float float-3 w-full max-w-[560px] lg:absolute lg:right-[20px] lg:top-[100px] lg:z-20 lg:w-[382px] lg:max-w-none">
                        <div class="tilt-thread overflow-hidden rounded-xl border border-black/[.07] bg-white win-shadow">

                            <div class="flex items-center gap-3 border-b border-black/[.06] bg-[#F7F7F9] px-3.5 py-2.5">
                                <div class="flex gap-1.5">
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#FF5F57]"></span>
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#FEBC2E]"></span>
                                    <span class="h-[10px] w-[10px] rounded-full bg-[#28C840]"></span>
                                </div>
                                <div class="mx-auto truncate font-mono-ui text-[10px] text-[#8A8F9E]">CLX-1036 · Meridian rollout</div>
                            </div>

                            <div class="space-y-3.5 p-4">

                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-semibold">Comments</span>
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-medium text-amber-700 ring-1 ring-amber-200">Ready for review</span>
                                </div>

                                {{-- PM --}}
                                <div class="flex gap-2.5">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#7C3AED] text-[8px] font-semibold text-white">PR</span>
                                    <div class="min-w-0">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-[11px] font-semibold">Priya R.</span>
                                            <span class="text-[9px] text-[#A1A6B4]">Project manager · 2h</span>
                                        </div>
                                        <p class="mt-0.5 text-[11px] leading-relaxed text-[#4A4F63]">
                                            Move the metrics block above the client quote. Everything else is approved.
                                        </p>
                                    </div>
                                </div>

                                {{-- Writer --}}
                                <div class="flex gap-2.5">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[8px] font-semibold text-white">AK</span>
                                    <div class="min-w-0">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-[11px] font-semibold">Aman K.</span>
                                            <span class="text-[9px] text-[#A1A6B4]">Writer · 1h</span>
                                        </div>
                                        <p class="mt-0.5 text-[11px] leading-relaxed text-[#4A4F63]">
                                            Moved it and tightened the intro. Uploaded as v3.
                                        </p>
                                        <div class="mt-1.5 flex items-center gap-2 rounded-lg border border-black/[.07] bg-[#FAFAFC] px-2 py-1.5">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 4a2 2 0 0 1 2-2h5l5 5v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm7 0v3h3l-3-3Z"/></svg>
                                            <span class="min-w-0 flex-1 truncate text-[10px] font-medium">meridian-case-study-v3.docx</span>
                                            <span class="font-mono-ui text-[9px] text-[#A1A6B4]">1.2 MB</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Client — types, then posts --}}
                                <div class="flex gap-2.5">
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0E7A55] text-[8px] font-semibold text-white">SM</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-[11px] font-semibold">Sara M.</span>
                                            <span class="text-[9px] text-[#A1A6B4]">Client</span>
                                        </div>
                                        {{-- both states share one grid cell, so nothing reflows --}}
                                        <div class="mt-1 grid">
                                            <div class="c-dots col-start-1 row-start-1 flex w-fit items-center gap-1 rounded-full bg-[#F1F2F6] px-2 py-1.5">
                                                <span class="c-dot h-1 w-1 rounded-full bg-[#5B6076]"></span>
                                                <span class="c-dot h-1 w-1 rounded-full bg-[#5B6076]"></span>
                                                <span class="c-dot h-1 w-1 rounded-full bg-[#5B6076]"></span>
                                            </div>
                                            <p class="c-msg col-start-1 row-start-1 text-[11px] leading-relaxed text-[#4A4F63]">
                                                Reads much better. Good to publish.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- composer --}}
                                <div class="flex items-center gap-2 rounded-lg border border-black/[.08] px-2.5 py-2">
                                    <span class="flex-1 text-[10.5px] text-[#A1A6B4]">Write a comment…</span>
                                    <span class="flex h-5 w-5 items-center justify-center rounded-md bg-indigo-600">
                                        <svg class="h-3 w-3 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3.5 17 10 3 16.5l2.5-6.5L3 3.5Z"/></svg>
                                    </span>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ------------------ 4. Mobile credits -------------- --}}
                    <div class="float float-4 w-full max-w-[236px] lg:absolute lg:left-[60%] lg:top-[296px] lg:z-40 lg:w-[218px] lg:max-w-none">
                        <div class="tilt-phone overflow-hidden rounded-[26px] border border-black/[.08] bg-white p-1.5 win-shadow">
                            <div class="overflow-hidden rounded-[20px] bg-[#FAFAFC]">

                                {{-- status bar --}}
                                <div class="flex items-center justify-between px-3.5 pb-1 pt-2">
                                    <span class="font-mono-ui text-[8px] font-medium">9:41</span>
                                    <div class="flex items-center gap-[3px]">
                                        <span class="h-[6px] w-[2px] rounded-sm bg-[#0F1222]/70"></span>
                                        <span class="h-[8px] w-[2px] rounded-sm bg-[#0F1222]/70"></span>
                                        <span class="h-[10px] w-[2px] rounded-sm bg-[#0F1222]/70"></span>
                                        <span class="ml-1 h-[7px] w-[13px] rounded-[2px] border border-[#0F1222]/50"></span>
                                    </div>
                                </div>

                                {{-- balance --}}
                                <div class="bg-white px-3.5 pb-3.5 pt-2">
                                    <div class="text-[9px] font-medium text-[#A1A6B4]">Credit balance</div>
                                    <div class="mt-0.5 flex items-baseline gap-1.5">
                                        <span class="text-[26px] font-semibold leading-none tracking-tight">1,240</span>
                                        <span class="text-[9px] text-[#5B6076]">credits</span>
                                    </div>
                                    <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-[#EEF0FF]">
                                        <div class="h-full w-[62%] rounded-full bg-indigo-600"></div>
                                    </div>
                                    <div class="mt-1.5 flex items-center justify-between text-[8.5px]">
                                        <span class="text-[#5B6076]">62% of the July pack used</span>
                                        <span class="font-mono-ui text-[#0E7A55]">−86 this week</span>
                                    </div>
                                </div>

                                {{-- notifications --}}
                                <div class="border-t border-black/[.05] px-3.5 py-3">
                                    <div class="text-[9px] font-semibold text-[#5B6076]">Recent</div>

                                    <div class="mt-2 h-[92px] space-y-2 overflow-hidden">

                                        {{-- lands right after Sara's comment posts in the thread --}}
                                        <div class="p-notif flex h-[40px] items-start gap-2 overflow-hidden rounded-lg bg-[#EEF0FF] p-2">
                                            <span class="mt-[3px] h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-600"></span>
                                            <div class="min-w-0">
                                                <p class="truncate text-[9.5px] font-medium leading-snug">Sara M. commented on CLX-1036</p>
                                                <p class="font-mono-ui text-[8px] text-[#7A8092]">just now</p>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-2 px-2">
                                            <span class="mt-[3px] h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[9.5px] leading-snug text-[#4A4F63]">CLX-1019 completed</p>
                                                <p class="font-mono-ui text-[8px] text-[#A1A6B4]">−14 credits · 3h</p>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-2 px-2">
                                            <span class="mt-[3px] h-1.5 w-1.5 shrink-0 rounded-full bg-[#FFB27A]"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[9.5px] leading-snug text-[#4A4F63]">Northwind topped up</p>
                                                <p class="font-mono-ui text-[8px] text-[#A1A6B4]">+500 credits · yesterday</p>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-2 px-2">
                                            <span class="mt-[3px] h-1.5 w-1.5 shrink-0 rounded-full bg-[#C8CCD6]"></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[9.5px] leading-snug text-[#4A4F63]">CLX-1028 ready for review</p>
                                                <p class="font-mono-ui text-[8px] text-[#A1A6B4]">yesterday</p>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

                <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 z-50 hidden h-36 scene-fade lg:block"></div>
            </div>
        </div>
    </main>

    {{-- ==================== Problem / Solution ==================== --}}
    <section id="product" x-data="{ tab: 'problem' }"
             class="relative border-t border-black/[.05] bg-[#F5F4FA] px-6 py-20 sm:py-24">

        <div class="mx-auto max-w-5xl">

            {{-- toggle --}}
            <div class="flex justify-center">
                <div role="tablist" aria-label="Problem or solution"
                     class="relative inline-flex rounded-full bg-black/[.045] p-1 ring-1 ring-black/[.06]">

                    <span aria-hidden="true"
                          class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-full bg-white toggle-shadow transition-transform duration-300 ease-out motion-reduce:transition-none"
                          :class="tab === 'solution' ? 'translate-x-full' : 'translate-x-0'"></span>

                    <button type="button" role="tab" @click="tab = 'problem'"
                            :aria-selected="tab === 'problem'"
                            class="relative z-10 w-[124px] rounded-full py-2 text-[13px] font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-[140px]"
                            :class="tab === 'problem' ? 'text-[#0F1222]' : 'text-[#7A8092] hover:text-[#4A4F63]'">
                        Problem
                    </button>

                    <button type="button" role="tab" @click="tab = 'solution'"
                            :aria-selected="tab === 'solution'"
                            class="relative z-10 w-[124px] rounded-full py-2 text-[13px] font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-[140px]"
                            :class="tab === 'solution' ? 'text-[#0F1222]' : 'text-[#7A8092] hover:text-[#4A4F63]'">
                        Solution
                    </button>
                </div>
            </div>

            {{-- panels share one grid cell so the section doesn't lurch mid-swap --}}
            <div class="mt-12 grid md:min-h-[560px]">

                {{-- ---------------------- PROBLEM ---------------------- --}}
                <div role="tabpanel" x-show="tab === 'problem'"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-3"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="col-start-1 row-start-1">

                    <h2 class="text-center font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] sm:text-[44px]">
                        Work is scattered everywhere
                    </h2>
                    <p class="mx-auto mt-5 max-w-2xl text-center text-[15px] leading-relaxed text-[#5B6076] sm:text-base">
                        Briefs live in WhatsApp. Files live in Drive. Status lives in someone's head.
                        By the time a client asks "where's my project", nobody has a straight answer.
                    </p>

                    {{-- scattered cards. Positions and widths are both percentages of
                         the same box the SVG uses, so the curves stay attached. --}}
                    <div class="relative mx-auto mt-12 grid max-w-[860px] gap-3 sm:grid-cols-2 md:mt-14 md:block md:h-[330px]">

                        <svg class="pointer-events-none absolute inset-0 hidden h-full w-full md:block"
                             viewBox="0 0 860 330" preserveAspectRatio="none" fill="none" aria-hidden="true">
                            <g stroke="#C9C5DC" stroke-width="1.5" stroke-dasharray="5 5" stroke-linecap="round" vector-effect="non-scaling-stroke">
                                <path d="M215 44 C 265 44, 276 150, 313 158" vector-effect="non-scaling-stroke"/>
                                <path d="M250 235 C 286 235, 286 186, 313 177" vector-effect="non-scaling-stroke"/>
                                <path d="M645 40 C 600 40, 585 145, 547 154" vector-effect="non-scaling-stroke"/>
                                <path d="M619 240 C 585 240, 580 190, 547 179" vector-effect="non-scaling-stroke"/>
                            </g>
                        </svg>

                        {{-- Client / WhatsApp --}}
                        <div class="rounded-xl border border-black/[.06] bg-white p-3 card-shadow md:absolute md:left-0 md:top-[2%] md:w-[25%] md:-rotate-2">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[8px] bg-[#25D366]">
                                    <svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 0 0-6.9 12l-1 3.6 3.7-1A8 8 0 1 0 10 2Zm4.3 11c-.2.5-1 1-1.5 1.1-.4 0-.9.1-3-.8-2.5-1.1-4.1-3.7-4.2-3.9-.1-.2-1-1.3-1-2.5s.6-1.7.9-2c.2-.2.5-.3.6-.3h.5c.1 0 .3 0 .5.4l.7 1.6c0 .2.1.3 0 .5l-.3.4-.3.3c-.1.1-.2.3 0 .5.1.3.6 1.1 1.4 1.7 1 .8 1.7 1 2 1.2.2 0 .4 0 .5-.1l.7-.9c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3v1.3Z"/></svg>
                                </span>
                                <span class="text-[12px] font-semibold">Client</span>
                            </div>
                            <p class="mt-2 text-[11.5px] italic leading-snug text-[#5B6076]">"Where's my logo file?"</p>
                        </div>

                        {{-- Brief / email --}}
                        <div class="rounded-xl border border-black/[.06] bg-white p-3 card-shadow md:absolute md:left-[4%] md:top-[60%] md:w-[25%] md:rotate-1">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[8px] bg-white ring-1 ring-black/[.08]">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2" y="4.5" width="16" height="11" rx="2" fill="#fff" stroke="#EA4335" stroke-width="1.4"/><path d="m2.8 5.5 7.2 5.2 7.2-5.2" stroke="#EA4335" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <span class="truncate font-mono-ui text-[10.5px] font-medium">RE: Fwd: Fwd: brief</span>
                            </div>
                            <p class="mt-2 text-[11.5px] italic leading-snug text-[#5B6076]">Buried 14 replies deep</p>
                        </div>

                        {{-- Files / Drive --}}
                        <div class="rounded-xl border border-black/[.06] bg-white p-3 card-shadow md:absolute md:right-0 md:top-[1%] md:w-[25%] md:rotate-2">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[8px] bg-white ring-1 ring-black/[.08]">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" aria-hidden="true"><path d="M7.6 2.5h4.8L18 12h-4.8L7.6 2.5Z" fill="#FBBC04"/><path d="M2 12 7.6 2.5 12.4 12H2Z" fill="#4285F4"/><path d="M2 12h10.4l-2.4 4.2H4.4L2 12Z" fill="#0F9D58"/></svg>
                                </span>
                                <span class="text-[12px] font-semibold">Files</span>
                            </div>
                            <p class="mt-2 text-[11.5px] italic leading-snug text-[#5B6076]">"Which version is final?"</p>
                        </div>

                        {{-- Status / spreadsheet --}}
                        <div class="rounded-xl border border-black/[.06] bg-white p-3 card-shadow md:absolute md:right-[3%] md:top-[61%] md:w-[25%] md:-rotate-1">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[8px] bg-[#0F9D58]">
                                    <svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><rect x="4" y="3.5" width="12" height="13" rx="1.6"/><path d="M4 8h12M4 12h12M10 8v8.5"/></svg>
                                </span>
                                <span class="text-[12px] font-semibold">Status</span>
                            </div>
                            <p class="mt-2 text-[11.5px] italic leading-snug text-[#5B6076]">Last updated 9 days ago</p>
                        </div>

                        {{-- verdict --}}
                        <div class="mx-auto sm:col-span-2 md:absolute md:left-1/2 md:top-[44%] md:col-span-1 md:-translate-x-1/2">
                            <span class="inline-flex items-center gap-2 rounded-full bg-[#FEF2F2] px-4 py-2.5 text-[13px] font-semibold text-[#B42318] ring-1 ring-[#FCA5A5]">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm.9 4.4-.2 5.1h-1.4l-.2-5.1h1.8ZM10 15a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z" clip-rule="evenodd"/></svg>
                                No single source of truth
                            </span>
                        </div>
                    </div>
                </div>

                {{-- ---------------------- SOLUTION --------------------- --}}
                <div role="tabpanel" x-show="tab === 'solution'" x-cloak
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-3"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="col-start-1 row-start-1">

                    <h2 class="text-center font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] sm:text-[44px]">
                        One task, one thread, one truth
                    </h2>
                    <p class="mx-auto mt-5 max-w-2xl text-center text-[15px] leading-relaxed text-[#5B6076] sm:text-base">
                        Every brief, file, and update lives on the task. Clients see progress live.
                        Nobody has to ask.
                    </p>

                    <div class="mx-auto mt-12 max-w-[860px] md:mt-14">

                        {{-- verdict --}}
                        <div class="flex justify-center">
                            <span class="inline-flex items-center gap-2 rounded-full bg-[#ECFDF3] px-4 py-2.5 text-[13px] font-semibold text-[#0E7A55] ring-1 ring-[#8FE0BC]">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm4.2 5.8-5.3 5.9a.9.9 0 0 1-1.3 0L5.8 11.3a.9.9 0 1 1 1.3-1.2l1.5 1.6 4.3-4.7a.9.9 0 1 1 1.3 1.2Z" clip-rule="evenodd"/></svg>
                                Task delivered on time
                            </span>
                        </div>

                        {{-- stem + branch. Solid lines here, dashed on the problem side. --}}
                        <div class="mx-auto h-7 w-px bg-[#C9C5DC]"></div>
                        <div class="relative hidden h-8 md:block" aria-hidden="true">
                            <span class="absolute left-[12.5%] right-[12.5%] top-0 h-px bg-[#C9C5DC]"></span>
                            @foreach (['12.5', '37.5', '62.5', '87.5'] as $x)
                                <span class="absolute bottom-0 top-0 w-px -translate-x-1/2 bg-[#C9C5DC]" style="left: {{ $x }}%"></span>
                            @endforeach
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 md:mt-0 md:grid-cols-4">
                            @foreach ([
                                ['chip' => 'Done',      'title' => 'Brief received',      'line' => '12 credits allocated',   'who' => 'Clarix',   'initials' => 'C',  'tone' => 'system'],
                                ['chip' => 'Done',      'title' => 'Files attached',      'line' => '3 files on the task',    'who' => 'Aman K.',  'initials' => 'AK', 'tone' => 'writer'],
                                ['chip' => 'Done',      'title' => 'Client viewed status', 'line' => 'Sara M. · 2h ago',      'who' => 'Sara M.',  'initials' => 'SM', 'tone' => 'client'],
                                ['chip' => 'Delivered', 'title' => 'Marked complete',     'line' => '1 day ahead of deadline','who' => 'Priya R.', 'initials' => 'PR', 'tone' => 'pm'],
                            ] as $card)
                                <div class="rounded-xl border border-black/[.06] bg-white p-3.5 card-shadow">
                                    <div class="flex items-center justify-between">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#ECFDF3] ring-1 ring-[#B7EBD2]">
                                            <svg class="h-3.5 w-3.5 text-[#0E7A55]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        </span>
                                        <span class="rounded-full bg-[#ECFDF3] px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-[#0E7A55]">{{ $card['chip'] }}</span>
                                    </div>

                                    <p class="mt-3 text-[12.5px] font-semibold leading-snug">{{ $card['title'] }}</p>
                                    <p class="mt-1 text-[11.5px] leading-snug text-[#5B6076]">{{ $card['line'] }}</p>

                                    <div class="mt-3.5 flex items-center gap-1.5 border-t border-black/[.05] pt-2.5">
                                        {{-- the system mark is a squircle, people are round --}}
                                        <span @class([
                                            'flex h-5 w-5 shrink-0 items-center justify-center text-[7.5px] font-semibold text-white',
                                            'rounded-[6px] bg-indigo-600' => $card['tone'] === 'system',
                                            'rounded-full bg-indigo-600'  => $card['tone'] === 'writer',
                                            'rounded-full bg-[#0E7A55]'   => $card['tone'] === 'client',
                                            'rounded-full bg-[#7C3AED]'   => $card['tone'] === 'pm',
                                        ])>{{ $card['initials'] }}</span>
                                        <span class="truncate text-[10.5px] font-medium text-[#4A4F63]">{{ $card['who'] }}</span>
                                        @if ($card['tone'] === 'system')
                                            <span class="ml-auto rounded bg-black/[.05] px-1.5 py-0.5 font-mono-ui text-[8px] text-[#7A8092]">auto</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ================== Platform arc → AXOKAI ==================== --}}
    @php
        // ---- Arc geometry -------------------------------------------------
        // One 860x400 box, origin at the bottom centre. The rings are FILLED
        // half-donuts — outer arc over the top, line inward, inner arc back,
        // close along the baseline — so each band is a solid colour field, not
        // a stroked curve. Colour runs pale -> medium -> full brand inward.
        // Everything below is 1.5x the geometry this started at, so the whole
        // system — bands, node arcs, dashed runs, particle trails — grew by the
        // same factor and every ratio between them is untouched. $BOX_W is sized
        // to clear $R1 with margin and still sit inside the section's padding at
        // the widest breakpoint; --ring-scale in the stylesheet shrinks it from
        // there. The circle's centre stays at ($CX, $BOX_H): bottom edge, dead
        // centre, which is the invariant the particle field is pinned to.
        $BOX_W = 1200;
        $BOX_H = 600;
        $CX = $BOX_W / 2;
        $R1 = 562.5;   // outer edge of the pale band
        $R2 = 423.0;   // pale | medium boundary
        $R3 = 307.5;   // medium | core boundary, and the core's own radius

        $bandPath = fn (float $ro, float $ri) => sprintf(
            'M%2$s %1$d A%3$s %3$s 0 0 1 %4$s %1$d L%5$s %1$d A%6$s %6$s 0 0 0 %7$s %1$d Z',
            $BOX_H, $CX - $ro, $ro, $CX + $ro, $CX + $ri, $ri, $CX - $ri
        );
        $corePath = sprintf(
            'M%2$s %1$d A%3$s %3$s 0 0 1 %4$s %1$d Z',
            $BOX_H, $CX - $R3, $R3, $CX + $R3
        );

        // $lift = distance from the wrapper's bottom edge up to the icon's
        // centre (tile height/2 + gap + label height), so `bottom` lands the
        // *icon* in the band rather than the label beneath it.
        $onArc = function (float $deg, float $r, float $lift) use ($CX) {
            $rad = deg2rad($deg);
            return [
                'left'   => round($CX + $r * cos($rad), 2),
                'bottom' => round($r * sin($rad) - $lift, 2),
            ];
        };

        // Row 1 — pale band. Even 36° pitch, Agent on the apex. Lifts grew with
        // the tiles, not with the geometry: label height and gap are unchanged,
        // so each lift is (new tile height / 2) plus the old label+gap figure.
        $outerNodes = [
            ['icon' => 'desktop', 'label' => 'Desktop',      'deg' => 162.0, 'lift' => 50.0],
            ['icon' => 'slack',   'label' => 'Slack',        'deg' => 126.0, 'lift' => 50.0],
            ['icon' => 'agent',   'label' => 'Clarix Agent', 'deg' =>  90.0, 'lift' => 55.0, 'big' => true],
            ['icon' => 'portal',  'label' => 'Client Portal','deg' =>  54.0, 'lift' => 50.0],
        ];
        foreach ($outerNodes as $i => $n) {
            $outerNodes[$i] += $onArc($n['deg'], 498.0, $n['lift']);
        }

        // Row 2 — medium band.
        $midNodes = [
            ['icon' => 'automations', 'label' => 'Automations', 'deg' => 135.0, 'lift' => 45.0],
            ['icon' => 'reports',     'label' => 'Reports',     'deg' =>  45.0, 'lift' => 45.0],
        ];
        foreach ($midNodes as $i => $n) {
            $midNodes[$i] += $onArc($n['deg'], 367.5, $n['lift']);
        }

        // Row 3 — on the solid core, with dashed runs down to its label.
        $coreNodes = [
            ['icon' => 'briefs',   'label' => 'Briefs',   'deg' => 140.0, 'lift' => 41.0],
            ['icon' => 'delivery', 'label' => 'Delivery', 'deg' =>  40.0, 'lift' => 41.0],
        ];
        foreach ($coreNodes as $i => $n) {
            $coreNodes[$i] += $onArc($n['deg'], 262.5, $n['lift']);
        }

        // Dashed connectors: out from under each core label, in to the label.
        // Offsets are the originals scaled by the same 1.5.
        $coreLinks = [];
        foreach ($coreNodes as $n) {
            $x = $n['left'];
            $endX = $CX + ($x < $CX ? -51 : 51);
            $coreLinks[] = sprintf(
                'M%.1f %.1f C%.1f %.1f %.1f %.1f %.1f %.1f',
                $x, $BOX_H - 107,
                $x, $BOX_H - 87,
                ($x + $endX) / 2, $BOX_H - 75,
                $endX, $BOX_H - 75
            );
        }

        $glyphs = [
            'desktop'  => '<svg class="h-5 w-5 text-[#4A4F63]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="10" rx="1.8"/><path d="M7 17h6M10 13.5V17"/></svg>',
            'slack'    => '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="none"><rect x="8.6" y="2" width="2.8" height="7.4" rx="1.4" fill="#36C5F0"/><rect x="2" y="8.6" width="7.4" height="2.8" rx="1.4" fill="#2EB67D"/><rect x="8.6" y="10.6" width="2.8" height="7.4" rx="1.4" fill="#ECB22E"/><rect x="10.6" y="8.6" width="7.4" height="2.8" rx="1.4" fill="#E01E5A"/></svg>',
            'agent'    => '<svg class="h-6 w-6 text-indigo-600" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5c.3 3.4 2.6 5.7 6 6-3.4.3-5.7 2.6-6 6-.3-3.4-2.6-5.7-6-6 3.4-.3 5.7-2.6 6-6Z"/><path d="M18.6 14.4c.15 1.7 1.3 2.85 3 3-1.7.15-2.85 1.3-3 3-.15-1.7-1.3-2.85-3-3 1.7-.15 2.85-1.3 3-3Z" opacity=".55"/></svg>',
            'portal'   => '<svg class="h-5 w-5 text-[#4A4F63]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="2"/><path d="M2.5 7.5h15"/><circle cx="10" cy="11" r="1.6"/><path d="M7.4 14.6a3 3 0 0 1 5.2 0"/></svg>',
            'briefs'   => '<svg class="h-5 w-5 text-[#4A4F63]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 2.5h6.5L16 7v10.5H5V2.5Z"/><path d="M11 2.5V7h5M7.5 10.5h5M7.5 13.5h3.5"/></svg>',
            'delivery' => '<svg class="h-5 w-5 text-[#4A4F63]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m10 2.5 7 3.5v8l-7 3.5-7-3.5V6l7-3.5Z"/><path d="M3 6l7 3.5L17 6M10 9.5v8"/></svg>',
            'automations' => '<svg class="h-5 w-5 text-[#4A4F63]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 2.5 4.5 11H9l-.5 6.5L15.5 9H11l.5-6.5Z"/></svg>',
            'reports'     => '<svg class="h-5 w-5 text-[#4A4F63]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5h14"/><path d="M5.5 16.5V9M9.5 16.5V4.5M13.5 16.5v-5"/></svg>',
        ];

        $axokaiCards = [
            ['icon' => '<path d="M5 2.5h6.5L16 7v10.5H5V2.5Z"/><path d="M11 2.5V7h5M7.5 10.5h5M7.5 13.5h3.5"/>',
             'text' => 'Has access to every brief, file, and message tied to a task'],
            ['icon' => '<circle cx="10" cy="10" r="7.5"/><path d="M10 5.5V10l3 2"/>',
             'text' => 'Tracks credits and time as work actually moves'],
            ['icon' => '<path d="M10 2.8a4.7 4.7 0 0 1 4.7 4.7c0 4 1.6 5.4 1.6 5.4H3.7s1.6-1.4 1.6-5.4A4.7 4.7 0 0 1 10 2.8Z"/><path d="M8.4 15.9a1.8 1.8 0 0 0 3.2 0"/>',
             'text' => 'Flags stalled tasks before a client has to ask'],
            ['icon' => '<path d="M3 13.5 7.5 9l3 3L17 5.5"/><path d="M12.5 5.5H17V10"/>',
             'text' => 'Gets clearer with every project, so estimates improve over time'],
        ];

        // ---- Pricing ---------------------------------------------------
        // A feature line ending in ':' is a lead-in ("Everything in Pro,
        // plus:") rather than a feature, and renders without a checkmark.
        //
        // 'priceAnnual' is the same plan at 30% off, quoted as a per-month
        // figure because that is what the card compares against. A plan
        // without the key (Enterprise) ignores the billing toggle entirely.
        $plans = [
            [
                'name'  => 'Basic',
                'price' => 'Rs 2,500',
                'priceAnnual' => 'Rs 1,750',
                'period' => 'per month',
                'blurb' => 'For small teams getting organized',
                'cta'   => 'Get Started with Basic',
                'href'  => route('login'),
                'features' => [
                    'Up to 5 Teams/Units',
                    'Task boards & file attachments',
                    'Basic client view (read-only status)',
                    '5GB file storage',
                    'Email support',
                ],
            ],
            [
                'name'  => 'Standard',
                'price' => 'Rs 6,000',
                'priceAnnual' => 'Rs 4,200',
                'period' => 'per month',
                'blurb' => 'For growing agencies with real client load',
                'cta'   => 'Get Started with Standard',
                'href'  => route('login'),
                'popular' => true,
                'features' => [
                    'Up to 20 Teams/Units',
                    'Full client portal access',
                    'Custom order/task labels & workflows',
                    'AI chatbot integration',
                    '50GB file storage',
                    'Priority email support',
                    'Basic analytics dashboard',
                ],
            ],
            [
                'name'  => 'Pro',
                'price' => 'Rs 10,000',
                'priceAnnual' => 'Rs 7,000',
                'period' => 'per month',
                'blurb' => 'For agencies running multiple teams and clients',
                'cta'   => 'Get Started with Pro',
                'href'  => route('login'),
                'features' => [
                    'Up to 50 Teams/Units',
                    'Everything in Standard, plus:',
                    'AI automation, chatbot & custom integrations (Excel, Google Sheets, MCPs)',
                    'Advanced analytics & reporting',
                    'Role-based permissions',
                    '200GB file storage',
                    'Priority chat support',
                    'Custom branding on client portal',
                ],
            ],
            [
                'name'  => 'Enterprise',
                'price' => 'Let\'s talk',
                'period' => 'Custom pricing',
                'blurb' => 'For agencies at scale',
                'cta'   => 'Talk to Sales',
                'href'  => '#schedule-demo',
                'dark'  => true,
                'features' => [
                    'Unlimited Teams/Units',
                    'Everything in Pro, plus:',
                    'Dedicated account manager',
                    'Custom integrations & API access',
                    'SLA-backed uptime guarantee',
                    'Onboarding & migration support',
                ],
            ],
        ];

        // ---- Plan comparison -------------------------------------------
        // Column order matches $plans, so the table reads left to right in
        // the same order as the cards above it.
        $compareHeads = [
            ['name' => 'Basic',      'cta' => 'Get Started',       'href' => route('login')],
            ['name' => 'Standard',   'cta' => 'Get Started',       'href' => route('login')],
            ['name' => 'Pro',        'cta' => 'Talk to an expert', 'href' => '#schedule-demo'],
            ['name' => 'Enterprise', 'cta' => 'Talk to an expert', 'href' => '#schedule-demo'],
        ];

        // [label, [4 values], hint?]. A value of true renders a check, false
        // an X, and a string renders as-is. The optional third element is
        // tooltip copy for the '?' beside the label.
        $compare = [
            'Core' => [
                ['Teams/Units', ['5', '20', '50', 'Unlimited']],
                ['Task boards', [true, true, true, true]],
                ['File attachments', [true, true, true, true]],
                ['Custom task labels & workflows', [false, true, true, true],
                 'Define your own task statuses and the order work moves through them.'],
            ],
            'Client Access' => [
                ['Client portal', ['Read-only', 'Full access', 'Full access', 'Full access'],
                 'The view your clients sign in to for status, files and credits.'],
                ['Custom branding on portal', [false, false, true, true]],
            ],
            'Storage' => [
                ['File storage', ['5GB', '50GB', '200GB', 'Custom']],
            ],
            'Support' => [
                ['Support type', ['Email', 'Priority email', 'Priority chat', 'Dedicated manager']],
                ['SLA guarantee', [false, false, false, true],
                 'A contractual uptime commitment with agreed response times.'],
                ['Onboarding & migration support', [false, false, false, true]],
            ],
            'Advanced' => [
                ['Analytics dashboard', [false, 'Basic', 'Advanced', 'Advanced']],
                ['AI Integration', [false, 'Chatbot', 'Automation + Chatbot + Custom MCPs', 'Custom AI setup'],
                 'Chatbot answers client and team questions; automation and custom MCPs connect Clarix to Excel, Google Sheets and your own tools.'],
                ['Role-based permissions', [false, false, true, true],
                 'Control what each staff role can see and change.'],
                ['API access', [false, false, false, true],
                 'Programmatic access to your Clarix data for custom tooling.'],
                ['Custom integrations', [false, false, false, true]],
            ],
        ];

        // ---- Orbit backdrop ------------------------------------------------
        // The ring diagram above is drawn around a centre that lands exactly on
        // this block's top edge, so these trails reuse that centre and the
        // diagram's own radii verbatim. Each one continues a band edge from up
        // there: $R1 the pale band's outer edge, $R2 the pale|medium boundary,
        // $R3 the medium|core edge. The filled bands cover the upper half of
        // all three circles, these dots carry the lower half on into the solid
        // colour — only that bottom arc falls inside the section, which is all
        // that should show.
        //
        // Coordinates are polar around (0,0), the shared centre. The wrapper
        // pins that origin to the section's top centre and the svg spins about
        // it, so every ring turns together on the diagram's own axis.
        mt_srand(20260812);
        // Half-extent of the viewBox and of the box it is drawn into. $R1 plus
        // the widest glow is ~572, so 620 leaves the arcs room on every side.
        $FIELD = 620;

        // Counts hold the spacing near 24 units on all three, so the inner
        // trails don't read as denser just for having less ground to cover —
        // and they rose with the radii, so the trails kept their density as
        // the system grew rather than stretching thin. Dot sizes deliberately
        // did NOT scale: these are particles over the diagram, not part of it,
        // so they stay 2-5px on $R1 down to 1.6-3.4px on $R3.
        $rings = [];
        foreach ([
            ['r' => $R1, 'n' => 144, 'sz' => [10, 25], 'line' => 0.18, 'sw' => 1.1],
            ['r' => $R2, 'n' => 108, 'sz' => [9,  20], 'line' => 0.16, 'sw' => 1.0],
            ['r' => $R3, 'n' => 80,  'sz' => [8,  17], 'line' => 0.14, 'sw' => 0.9],
        ] as $band) {
            [$lo, $hi] = $band['sz'];
            $dots = [];

            for ($i = 0; $i < $band['n']; $i++) {
                $a = ($i / $band['n']) * M_PI * 2;   // exactly even, no jitter
                $r = mt_rand($lo, $hi) / 10;
                $t = ($r * 10 - $lo) / ($hi - $lo);  // 0 at the smallest, 1 at the largest

                $dots[] = [
                    'x' => round($band['r'] * cos($a), 1),
                    'y' => round($band['r'] * sin($a), 1),
                    'r' => $r,
                    'o' => round(0.28 + $t * 0.20, 2),
                ];
            }

            $rings[] = [
                'dots' => $dots,
                'line' => $band['line'],
                'sw'   => $band['sw'],
                'glow' => round($hi / 10 * 0.8, 2),   // top ~20% of sizes get a halo
            ];
        }

        // ---- Why Clarix -------------------------------------------------
        // The first entry is the one the accordion opens on, so it carries
        // the answer someone landing here needs before any of the others.
        $whyItems = [
            [
                'q' => 'How it works',
                'a' => 'Clarix turns every task into a live thread. Briefs, files, and client updates all sit in one place. Our AI tracks progress as work happens and flags anything stalling before a client has to ask.',
            ],
            [
                'q' => 'What makes it different?',
                'a' => 'Most task tools stop at a checklist. Clarix adds an AI layer on top: a chatbot your team and your clients can ask for status, automations that keep work moving without anyone chasing it, and custom connections into the tools you already run on, including Excel, Google Sheets, and your own MCP integrations.',
            ],
            [
                'q' => 'Why switch now?',
                'a' => 'Right now the brief is in WhatsApp, the file is in Drive, the approval is buried in email, and the only record of what was agreed is somebody\'s memory. Clarix replaces that with one connected system, so every task carries its own history and nothing depends on who remembers to forward what.',
            ],
        ];

        $whyStats = [
            ['figure' => '500+', 'label' => 'tasks completed monthly across active agencies'],
            ['figure' => '50+',  'label' => 'agencies using Clarix'],
        ];

        // Placeholder chips, not client logos — these name the kinds of teams
        // Clarix is built for, so nothing here claims a customer we can't show.
        $whyChips = ['Design studios', 'Creative agencies', 'Marketing teams'];

        // The polaroid stack. Order here is the starting order, front first;
        // the caption fills the polaroid's wide bottom border.
        $whyPhotos = [
            ['id' => 'photo-1552664730-d307ca884978', 'caption' => 'Sprint kickoff, Tuesday',
             'alt' => 'A team gathered around a table mapping work out on sticky notes'],
            ['id' => 'photo-1522071820081-009f0129c71c', 'caption' => 'Studio, deep work hours',
             'alt' => 'Colleagues working side by side on laptops at a long wooden table'],
            ['id' => 'photo-1531482615713-2afd69097998', 'caption' => 'Design review',
             'alt' => 'Two people reviewing work together on a desktop screen'],
            ['id' => 'photo-1517245386807-bb43f82c33c4', 'caption' => 'Client walkthrough',
             'alt' => 'Someone talking through a dashboard on a laptop in a meeting'],
        ];

        // ---- Schedule a demo --------------------------------------------
        $demoPoints = [
            'See how agencies cut delivery delays by keeping every brief, file, and update in one place',
            'Watch how our AI chatbot and automation handle routine status updates for you',
            'Experience the full task lifecycle, from brief to client sign-off, without leaving Clarix',
            'Discover why agencies switch from scattered WhatsApp, email, and Drive workflows to one connected system',
        ];

        // Rendered in this order. 'required' drives both the attribute and the
        // "Required" flag; everything else is optional and left unmarked.
        $demoFields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text',
             'required' => true, 'autocomplete' => 'name', 'placeholder' => 'Your name'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email',
             'required' => true, 'autocomplete' => 'email', 'placeholder' => 'james@clarix.com'],
            ['name' => 'company', 'label' => 'Company / Agency name', 'type' => 'text',
             'required' => true, 'autocomplete' => 'organization', 'placeholder' => 'Northwind Studio'],
            ['name' => 'company_size', 'label' => 'Company / Agency size', 'type' => 'select',
             'options' => ['1-5', '6-15', '16-30', '31-50', '50+']],
            ['name' => 'vat_pan', 'label' => 'VAT / PAN number (not mandatory)', 'type' => 'text',
             'placeholder' => '600123456'],
            ['name' => 'phone', 'label' => 'Phone number', 'type' => 'tel',
             'autocomplete' => 'tel', 'placeholder' => '+977 98XXXXXXXX'],
            ['name' => 'referral', 'label' => 'How did you hear about us?', 'type' => 'text',
             'placeholder' => 'A colleague, search, an event…'],
        ];

        // ---- Footer -----------------------------------------------------
        // Pricing is the only link with a destination that exists today.
        // The rest are placeholders waiting on pages still to be built.
        $footerNav = [
            'Product' => [
                ['Task Boards', '#'],
                ['Client Portal', '#'],
                ['AI Automation', '#'],
                ['File Management', '#'],
                ['Pricing', '#pricing'],
                ['Changelog', '#'],
            ],
            'Learn' => [
                ['Blog', '#'],
                ['Customer Stories', '#'],
                ['Documentation', '#'],
                ['Alternatives', '#'],
                ['Community', '#'],
            ],
            'Company' => [
                ['Legal', '#'],
                ['Privacy Policy', '#'],
                ['Security & Compliance', '#'],
                ['Careers', '#'],
                ['Status', '#'],
            ],
        ];

        // Brand glyphs on a 24x24 viewBox, filled rather than stroked.
        $footerSocial = [
            ['name' => 'LinkedIn', 'href' => '#', 'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0Z'],
            ['name' => 'X',        'href' => '#', 'path' => 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.41l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41Z'],
            ['name' => 'Facebook', 'href' => '#', 'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07Z'],
            ['name' => 'YouTube',  'href' => '#', 'path' => 'M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.5A3.02 3.02 0 0 0 .5 6.19C0 8.08 0 12 0 12s0 3.92.5 5.81a3.02 3.02 0 0 0 2.12 2.14c1.88.5 9.38.5 9.38.5s7.5 0 9.38-.5a3.02 3.02 0 0 0 2.12-2.14C24 15.92 24 12 24 12s0-3.92-.5-5.81ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z'],
            ['name' => 'Slack',    'href' => '#', 'path' => 'M5.04 15.17a2.53 2.53 0 0 1-2.52 2.52A2.53 2.53 0 0 1 0 15.17a2.53 2.53 0 0 1 2.52-2.52h2.52v2.52Zm1.27 0a2.53 2.53 0 0 1 2.52-2.52 2.53 2.53 0 0 1 2.52 2.52v6.31A2.53 2.53 0 0 1 8.83 24a2.53 2.53 0 0 1-2.52-2.52v-6.31ZM8.83 5.04a2.53 2.53 0 0 1-2.52-2.52A2.53 2.53 0 0 1 8.83 0a2.53 2.53 0 0 1 2.52 2.52v2.52H8.83Zm0 1.27a2.53 2.53 0 0 1 2.52 2.52 2.53 2.53 0 0 1-2.52 2.52H2.52A2.53 2.53 0 0 1 0 8.83a2.53 2.53 0 0 1 2.52-2.52h6.31ZM18.96 8.83a2.53 2.53 0 0 1 2.52-2.52A2.53 2.53 0 0 1 24 8.83a2.53 2.53 0 0 1-2.52 2.52h-2.52V8.83Zm-1.27 0a2.53 2.53 0 0 1-2.52 2.52 2.53 2.53 0 0 1-2.52-2.52V2.52A2.53 2.53 0 0 1 15.17 0a2.53 2.53 0 0 1 2.52 2.52v6.31ZM15.17 18.96a2.53 2.53 0 0 1 2.52 2.52A2.53 2.53 0 0 1 15.17 24a2.53 2.53 0 0 1-2.52-2.52v-2.52h2.52Zm0-1.27a2.53 2.53 0 0 1-2.52-2.52 2.53 2.53 0 0 1 2.52-2.52h6.31A2.53 2.53 0 0 1 24 15.17a2.53 2.53 0 0 1-2.52 2.52h-6.31Z'],
        ];
    @endphp

    {{-- ---- top half: white, with the colour rising out of the core ---- --}}
    <section class="relative z-20 bg-white px-6 pt-20 sm:pt-24">

        <h2 class="relative z-30 mx-auto max-w-3xl text-center font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] sm:text-[44px]">
            Meet the platform running your agency's work
        </h2>

        {{-- No z-index or transform on these wrappers: the arc layer (z-10),
             the bleed (z-20) and the node layer (z-30) all have to compete in
             the section's stacking context for the bleed to sit between them. --}}
        <div class="mt-16">

            {{-- arc layout, md and up. The 1200x600 box is drawn once and
                 scaled to fit narrower viewports so nothing has to reflow;
                 .ring-stage reserves exactly the height it lands on. --}}
            <div class="ring-stage relative mx-auto hidden md:block">

                {{-- ---- layer 1: the filled bands, painted under the bleed ---- --}}
                <div class="ring-box absolute left-1/2 top-0 z-10">
                    <svg class="absolute inset-0 h-full w-full" viewBox="0 0 {{ $BOX_W }} {{ $BOX_H }}" fill="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="clx-core" x1="0" y1="{{ $BOX_H - $R3 }}" x2="0" y2="{{ $BOX_H }}" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#6D64EA"/>
                                <stop offset="1" stop-color="#4F46E5"/>
                            </linearGradient>
                        </defs>
                        <path d="{{ $bandPath($R1, $R2) }}" fill="#EDEAFB"/>
                        <path d="{{ $bandPath($R2, $R3) }}" fill="#BEB6F2"/>
                        <path d="{{ $corePath }}" fill="url(#clx-core)"/>
                    </svg>
                </div>

                {{-- ---- layer 2: nodes + labels, painted over the bleed ---- --}}
                <div class="ring-box pointer-events-none absolute left-1/2 top-0 z-30">

                    {{-- dashed runs from the core icons down to the core label --}}
                    <svg class="absolute inset-0 h-full w-full" viewBox="0 0 {{ $BOX_W }} {{ $BOX_H }}" fill="none" aria-hidden="true">
                        <g stroke="#FFFFFF" stroke-opacity=".5" stroke-width="1.2" stroke-dasharray="3 3" stroke-linecap="round">
                            @foreach ($coreLinks as $d)
                                <path d="{{ $d }}"/>
                            @endforeach
                        </g>
                    </svg>

                    {{-- row 1, pale band --}}
                    @foreach ($outerNodes as $n)
                        <div class="absolute flex -translate-x-1/2 flex-col items-center"
                             style="left: {{ $n['left'] }}px; bottom: {{ $n['bottom'] }}px">
                            <span @class([
                                'flex items-center justify-center rounded-[14px] bg-white card-shadow ring-1 ring-black/[.07]',
                                'ring-indigo-200' => $n['big'] ?? false,
                                'h-14 w-14' => ! ($n['big'] ?? false),
                            ])
                                  @style(['width: 64px; height: 64px' => $n['big'] ?? false])>{!! $glyphs[$n['icon']] !!}</span>
                            <span @class([
                                'mt-2 whitespace-nowrap',
                                'text-[12px] font-semibold text-[#221E5C]' => $n['big'] ?? false,
                                'text-[11.5px] font-medium text-[#4A4F63]' => ! ($n['big'] ?? false),
                            ])>{{ $n['label'] }}</span>
                        </div>
                    @endforeach

                    {{-- row 2, medium band --}}
                    @foreach ($midNodes as $n)
                        <div class="absolute flex -translate-x-1/2 flex-col items-center"
                             style="left: {{ $n['left'] }}px; bottom: {{ $n['bottom'] }}px">
                            <span class="flex h-12 w-12 items-center justify-center rounded-[12px] bg-white card-shadow ring-1 ring-black/[.07]">
                                {!! $glyphs[$n['icon']] !!}
                            </span>
                            <span class="mt-2 whitespace-nowrap text-[11px] font-semibold text-[#37307A]">{{ $n['label'] }}</span>
                        </div>
                    @endforeach

                    {{-- row 3, on the solid core --}}
                    @foreach ($coreNodes as $n)
                        <div class="absolute flex -translate-x-1/2 flex-col items-center"
                             style="left: {{ $n['left'] }}px; bottom: {{ $n['bottom'] }}px">
                            <span class="flex h-11 w-11 items-center justify-center rounded-[11px] bg-white card-shadow">
                                {!! $glyphs[$n['icon']] !!}
                            </span>
                            <span class="mt-1.5 whitespace-nowrap text-[10.5px] font-semibold text-white">{{ $n['label'] }}</span>
                        </div>
                    @endforeach

                    {{-- the core's own label, sitting directly on the solid fill --}}
                    <div class="absolute left-1/2 -translate-x-1/2 text-center" style="bottom: 60px; width: 420px">
                        <div class="text-[12px] font-semibold text-white">Operational layer</div>
                        <div class="mt-1 text-[10px] leading-snug text-white/65">Credits, Files, Roles and Workflows</div>
                    </div>
                </div>
            </div>

            {{-- Stacked below md: the same pale -> medium -> core nesting,
                 squared off because arcs don't survive a phone's width. Band
                 backgrounds stay unpositioned so the bleed washes them; only
                 the content is lifted to z-30 to stay above it. --}}
            <div class="relative md:hidden">
                <div class="mx-auto max-w-sm rounded-t-[32px] bg-[#EDEAFB] px-3 pt-7">

                    <div class="relative z-30 grid grid-cols-4 gap-1.5">
                        @foreach ($outerNodes as $n)
                            <div class="flex flex-col items-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-[12px] bg-white card-shadow ring-1 ring-black/[.07]">{!! $glyphs[$n['icon']] !!}</span>
                                <span class="mt-1.5 text-center text-[9.5px] font-medium leading-tight text-[#4A4F63]">{{ $n['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-7 rounded-t-[26px] bg-[#BEB6F2] px-3 pt-6">

                        <div class="relative z-30 mx-auto grid max-w-[220px] grid-cols-2 gap-3">
                            @foreach ($midNodes as $n)
                                <div class="flex flex-col items-center">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-[12px] bg-white card-shadow">{!! $glyphs[$n['icon']] !!}</span>
                                    <span class="mt-1.5 text-[10px] font-semibold text-[#37307A]">{{ $n['label'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 rounded-t-[20px] bg-[#4F46E5] px-3 pb-10 pt-6">

                            <div class="relative z-30 mx-auto grid max-w-[200px] grid-cols-2 gap-3">
                                @foreach ($coreNodes as $n)
                                    <div class="flex flex-col items-center">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-[11px] bg-white card-shadow">{!! $glyphs[$n['icon']] !!}</span>
                                        <span class="mt-1.5 text-[10px] font-semibold text-white">{{ $n['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="relative z-30 mt-5 flex flex-col items-center">
                                <span class="h-5 w-px bg-white/40"></span>
                                <span class="mt-2.5 text-[12px] font-semibold text-white">Operational layer</span>
                                <span class="mt-1 text-center text-[10px] text-white/65">Credits, Files, Roles and Workflows</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- the rising colour, over the rings and the orb, under the labels --}}
        <div aria-hidden="true" class="bleed pointer-events-none absolute inset-x-0 bottom-0 z-20"></div>
    </section>

    {{-- ---- bottom half: solid brand, continuing the same surface ---- --}}
    {{-- pt clears the orb's overhang (58px at full scale) plus ~72px of air --}}
    <section class="relative overflow-hidden bg-[#4F46E5] px-6 pb-20 pt-[130px] sm:pb-24 sm:pt-[144px]">

        {{-- Orbit backdrop: the ring diagram's circle, carried on downward.
             The square wrapper's centre is pinned to this section's top edge —
             the very point the semicircle above is drawn around — and both
             share the horizontal centre of the viewport, so the two halves line
             up seamlessly. The wrapper holds the positioning transform (and the
             diagram's own scale, so the radii stay in register); the svg inside
             is left free to do nothing but rotate. z-0 keeps it under the text
             and the cards. --}}
        <div aria-hidden="true" class="orbit-field pointer-events-none absolute inset-0 z-0 overflow-hidden">
            {{-- The box size is an inline style, NOT a Tailwind arbitrary value:
                 it is derived from $FIELD, and Tailwind only emits classes it
                 can find as literal text when the CSS is built. A h-[…px] here
                 would silently resolve to nothing until someone reran the build,
                 collapsing this div to auto size — at which point the svg falls
                 back to its 300x150 default and the ring renders at a fraction
                 of its radius, jammed against the top edge. --}}
            <div class="ring-field absolute left-1/2 top-0"
                 style="width: {{ $FIELD * 2 }}px; height: {{ $FIELD * 2 }}px">
                <svg class="orbit" width="100%" height="100%"
                     viewBox="-{{ $FIELD }} -{{ $FIELD }} {{ $FIELD * 2 }} {{ $FIELD * 2 }}"
                     fill="none">
                    {{-- All three trails share this one rotating svg, so they
                         turn in lockstep the way the bands above sit fixed
                         relative to each other. --}}
                    @foreach ($rings as $ring)
                        @php $count = count($ring['dots']); @endphp

                        {{-- Neighbours only, closing back on the first dot: the
                             chain follows the circumference, never chords it. --}}
                        <g stroke="#C7D2FE" stroke-width="{{ $ring['sw'] }}" stroke-opacity="{{ $ring['line'] }}">
                            @foreach ($ring['dots'] as $i => $d)
                                @php $n = $ring['dots'][($i + 1) % $count]; @endphp
                                <line x1="{{ $d['x'] }}" y1="{{ $d['y'] }}"
                                      x2="{{ $n['x'] }}" y2="{{ $n['y'] }}"/>
                            @endforeach
                        </g>
                        @foreach ($ring['dots'] as $d)
                            @if ($d['r'] >= $ring['glow'])
                                <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="{{ round($d['r'] * 3.6, 1) }}"
                                        fill="#C7D2FE" fill-opacity="0.08"/>
                            @endif
                            <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="{{ $d['r'] }}"
                                    fill="#E0E7FF" fill-opacity="{{ $d['o'] }}"/>
                        @endforeach
                    @endforeach
                </svg>
            </div>
        </div>

        <div class="relative z-10 mx-auto max-w-5xl">

            <div class="flex justify-center">
                <span class="text-[17px] font-semibold tracking-tight text-white">Clarix</span>
            </div>

            <h2 class="mt-7 text-center font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] text-white sm:text-[44px]">
                Built on the power of AXOKAI
            </h2>

            <p class="mx-auto mt-5 max-w-2xl text-center text-[15px] leading-relaxed text-white/75 sm:text-base">
                The AI agent that never loses track. AXOKAI watches every brief, file, and update
                as work happens, so nothing slips through.
            </p>

            <div class="mt-8 flex justify-center">
                <a href="https://axokai.codesnextdoor.com/" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 rounded-full border border-white/35 px-5 py-2.5 text-[14px] font-semibold text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    Learn more about AXOKAI
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </a>
            </div>

            <div class="axokai-grid mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($axokaiCards as $card)
                    <div class="axokai-card rounded-2xl bg-white p-6 shadow-[0_18px_34px_-16px_rgba(14,17,38,.34)]">
                        <span class="flex h-9 w-9 items-center justify-center rounded-[10px] bg-[#EEF0FF]">
                            <svg class="h-[18px] w-[18px] text-indigo-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $card['icon'] !!}</svg>
                        </span>
                        <p class="text-[13.5px] leading-relaxed text-[#4A4F63]">{{ $card['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---- pricing ---- --}}
    <section id="pricing" x-data="{ billing: 'monthly' }"
             class="relative bg-white px-6 py-20 sm:py-24">
        <div class="mx-auto max-w-6xl">

            <h2 class="text-center font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] sm:text-[44px]">
                Simple pricing for growing agencies
            </h2>

            <p class="mx-auto mt-5 max-w-2xl text-center text-[15px] leading-relaxed text-[#4A4F63] sm:text-base">
                Pick the plan that fits your team size. Upgrade anytime as your agency grows.
            </p>

            {{-- Billing toggle. Both halves are the same fixed width so the
                 sliding pill stays a clean 50%, even though only one of them
                 carries the savings badge. --}}
            <div class="mt-9 flex justify-center">
                <div role="group" aria-label="Billing period"
                     class="relative inline-flex rounded-full bg-black/[.045] p-1 ring-1 ring-black/[.06]">

                    <span aria-hidden="true"
                          class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-full bg-indigo-600 shadow-[0_6px_16px_-8px_rgba(79,70,229,.9)] transition-transform duration-300 ease-out motion-reduce:transition-none"
                          :class="billing === 'annual' ? 'translate-x-full' : 'translate-x-0'"></span>

                    <button type="button" @click="billing = 'monthly'" aria-pressed="true"
                            :aria-pressed="billing === 'monthly'"
                            class="relative z-10 w-[150px] rounded-full py-2 text-[13px] font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-[168px]"
                            :class="billing === 'monthly' ? 'text-white' : 'text-[#7A8092] hover:text-[#4A4F63]'">
                        Monthly
                    </button>

                    <button type="button" @click="billing = 'annual'" aria-pressed="false"
                            :aria-pressed="billing === 'annual'"
                            class="relative z-10 flex w-[150px] items-center justify-center gap-2 rounded-full py-2 text-[13px] font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:w-[168px]"
                            :class="billing === 'annual' ? 'text-white' : 'text-[#7A8092] hover:text-[#4A4F63]'">
                        Annually
                        <span class="rounded-full px-2 py-[1px] text-[10.5px] font-semibold transition-colors"
                              :class="billing === 'annual' ? 'bg-white/20 text-white' : 'bg-[#EEF0FF] text-indigo-700'">
                            Save 30%
                        </span>
                    </button>
                </div>
            </div>

            <div class="price-grid mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($plans as $plan)
                    <div @class(['price-card', 'price-card--featured' => $plan['popular'] ?? false])>

                        <div class="price-head">
                            <span class="price-name">{{ $plan['name'] }}</span>
                            @if ($plan['popular'] ?? false)
                                <span class="price-pill">Most Popular</span>
                            @endif
                        </div>

                        {{-- Monthly is the default, so it is what renders
                             server-side; Alpine only swaps text from there. --}}
                        <div class="price-amount font-display">
                            @if (isset($plan['priceAnnual']))
                                <span x-text="billing === 'annual' ? '{{ $plan['priceAnnual'] }}' : '{{ $plan['price'] }}'">{{ $plan['price'] }}</span>
                                <span class="price-was" x-show="billing === 'annual'" x-cloak>{{ $plan['price'] }}</span>
                            @else
                                {{ $plan['price'] }}
                            @endif
                        </div>
                        <div class="price-period">
                            @if (isset($plan['priceAnnual']))
                                <span x-text="billing === 'annual' ? 'per month, billed annually' : '{{ $plan['period'] }}'">{{ $plan['period'] }}</span>
                            @else
                                {{ $plan['period'] }}
                            @endif
                        </div>
                        <p class="price-blurb">{{ $plan['blurb'] }}</p>

                        <div class="price-rule" aria-hidden="true"></div>

                        <ul class="price-features">
                            @foreach ($plan['features'] as $feature)
                                @if (str_ends_with($feature, ':'))
                                    <li class="price-lead">{{ $feature }}</li>
                                @else
                                    <li>
                                        <svg class="price-check" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                             stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M3 8.5 6.4 12 13 4.8"/>
                                        </svg>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>

                        <div class="price-cta">
                            <a href="{{ $plan['href'] }}"
                               @class(['price-btn', 'price-btn--dark' => $plan['dark'] ?? false])>{{ $plan['cta'] }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---- plan comparison ---- --}}
    <section id="compare" class="relative bg-white px-6 pb-20 sm:pb-24">
        <div class="mx-auto max-w-6xl">
            <div class="cmp-scroll">
                <table class="cmp">
                    <colgroup>
                        <col style="width: 30%">
                        <col span="4" style="width: 17.5%">
                    </colgroup>

                    <thead>
                        <tr>
                            <th scope="col">
                                <span class="cmp-title font-display">Compare plans</span>
                            </th>
                            @foreach ($compareHeads as $head)
                                <th scope="col" class="cmp-val">
                                    <span class="cmp-plan">{{ $head['name'] }}</span>
                                    <a class="cmp-link" href="{{ $head['href'] }}">{{ $head['cta'] }}</a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    {{-- One tbody per group, so the band's th genuinely heads
                         a row group and the empty cells keep the column grid
                         running through the cream. --}}
                    @foreach ($compare as $group => $rows)
                        <tbody>
                            <tr class="cmp-group">
                                <th scope="rowgroup">{{ $group }}</th>
                                @for ($i = 0; $i < 4; $i++)
                                    <td></td>
                                @endfor
                            </tr>

                            @foreach ($rows as $row)
                                {{-- the hint is optional, so it can't be destructured --}}
                                @php [$label, $values] = $row; $hint = $row[2] ?? null; @endphp
                                <tr class="cmp-row">
                                    <th scope="row" class="cmp-feat">
                                        {{ $label }}
                                        @if ($hint)
                                            <span class="cmp-hint" tabindex="0"
                                                  title="{{ $hint }}" aria-label="{{ $label }}: {{ $hint }}">?</span>
                                        @endif
                                    </th>
                                    @foreach ($values as $v)
                                        <td class="cmp-val">
                                            @if ($v === true)
                                                <svg class="cmp-yes" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 8.5 6.4 12 13 4.8"/>
                                                </svg>
                                                <span class="sr-only">Included</span>
                                            @elseif ($v === false)
                                                <svg class="cmp-no" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                                     stroke-width="2.1" stroke-linecap="round" aria-hidden="true">
                                                    <path d="M4 4l8 8M12 4l-8 8"/>
                                                </svg>
                                                <span class="sr-only">Not included</span>
                                            @else
                                                {{ $v }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>
    </section>

    {{-- ---- why Clarix ---- --}}
    <section id="why" class="why-section relative border-t border-black/[.05] bg-[#F5F4FA] px-6 py-20 sm:py-24">
        <div class="mx-auto grid max-w-6xl items-start gap-14 lg:grid-cols-2 lg:gap-16">

            {{-- ------------------------- left ------------------------- --}}
            <div x-data="{ open: 0 }">

                <span class="text-[12.5px] font-semibold uppercase tracking-[.08em] text-indigo-600">
                    Why agencies choose Clarix
                </span>

                <h2 class="mt-5 font-display text-[30px] font-normal leading-[1.14] tracking-[-0.02em] text-[#17143A] sm:text-[38px] lg:text-[40px]">
                    Every brief, every file, every update, tracked automatically so nothing falls through the cracks.
                </h2>

                {{-- Single-open accordion. The first panel ships open from the
                     server so it doesn't pop once Alpine takes over. --}}
                <div class="mt-10 divide-y divide-black/[.08] border-y border-black/[.08]">
                    @foreach ($whyItems as $i => $item)
                        <div>
                            <h3>
                                <button type="button"
                                        @click="open = open === {{ $i }} ? null : {{ $i }}"
                                        aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                        :aria-expanded="open === {{ $i }}"
                                        aria-controls="why-panel-{{ $i }}"
                                        class="flex w-full items-center justify-between gap-6 py-5 text-left text-[15.5px] font-semibold text-[#17143A] transition-colors hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    {{ $item['q'] }}
                                    {{-- Object form, not "expr && 'class'": the first item
                                         ships open from the server, and only the object
                                         form removes a class Alpine did not add itself. --}}
                                    <svg @class(['acc-chev h-4 w-4 shrink-0 text-[#7A8092]', 'acc-chev--open' => $i === 0])
                                         :class="{ 'acc-chev--open': open === {{ $i }} }"
                                         viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.9"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 6.25 8 10.25 12 6.25"/>
                                    </svg>
                                </button>
                            </h3>

                            <div id="why-panel-{{ $i }}"
                                 @class(['acc-panel', 'acc-panel--open' => $i === 0])
                                 :class="{ 'acc-panel--open': open === {{ $i }} }"
                                 aria-hidden="{{ $i === 0 ? 'false' : 'true' }}"
                                 :aria-hidden="open !== {{ $i }}">
                                <div>
                                    <p class="pb-6 pr-4 text-[14px] leading-relaxed text-[#4A4F63] sm:pr-10">
                                        {{ $item['a'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ------------------------ right ------------------------- --}}
            <div>

                <div class="grid grid-cols-2 gap-6 sm:gap-8">
                    @foreach ($whyStats as $stat)
                        <div @class(['border-l border-black/[.10] pl-6 sm:pl-8' => ! $loop->first])>
                            <div class="font-display text-[40px] leading-none tracking-[-0.02em] text-[#17143A] sm:text-[52px]">
                                {{ $stat['figure'] }}
                            </div>
                            <p class="mt-3 max-w-[200px] text-[13px] leading-relaxed text-[#4A4F63]">
                                {{ $stat['label'] }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10">
                    <span class="text-[11px] font-semibold uppercase tracking-[.1em] text-[#8A8FA0]">Built for</span>
                    <div class="mt-3.5 flex flex-wrap gap-2.5">
                        @foreach ($whyChips as $chip)
                            <span class="inline-flex items-center gap-2 rounded-xl border border-black/[.07] bg-white px-3.5 py-2 text-[12.5px] font-medium text-[#4A4F63] shadow-[0_1px_2px_rgba(14,17,38,.05)]">
                                <span class="h-4 w-4 rounded-[5px] bg-gradient-to-br from-indigo-400 to-indigo-600" aria-hidden="true"></span>
                                {{ $chip }}
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Photo stack. Move and release are bound to the window so a
                     throw survives the pointer leaving the card. The inline
                     transforms below mirror what photoStack() computes for the
                     same depth, so the pile is already stacked pre-hydration. --}}
                @php $tilt = [-1.2, 3.4, -3.8, 5.2]; @endphp
                <div class="mt-12"
                     x-data="photoStack({{ count($whyPhotos) }})"
                     @pointermove.window="move($event)"
                     @pointerup.window="end()"
                     @pointercancel.window="end()">

                    <div class="photo-stack">
                        @foreach ($whyPhotos as $i => $photo)
                            <figure class="polaroid"
                                    style="transform: translate3d({{ $i * 15 }}px, {{ $i * -13 }}px, 0) rotate({{ $tilt[$i % 4] }}deg) scale({{ round(1 - $i * 0.045, 3) }}); z-index: {{ count($whyPhotos) - $i }};"
                                    :class="{ 'polaroid--front': isFront({{ $i }}) }"
                                    :style="style({{ $i }})"
                                    @pointerdown="start($event, {{ $i }})">
                                <img src="https://images.unsplash.com/{{ $photo['id'] }}?auto=format&amp;fit=crop&amp;w=640&amp;h=760&amp;q=70"
                                     alt="{{ $photo['alt'] }}" width="640" height="760"
                                     loading="lazy" decoding="async" draggable="false">
                                <figcaption>{{ $photo['caption'] }}</figcaption>
                            </figure>
                        @endforeach
                    </div>

                    {{-- Drag is undiscoverable on its own, and unusable without a
                         pointer, so the same action gets a button and a hint. --}}
                    <div class="mt-7 flex items-center gap-3">
                        <button type="button" @click="advance(1)" aria-label="Show the next photo"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-black/[.09] bg-white text-[#4A4F63] shadow-[0_1px_2px_rgba(14,17,38,.06)] transition hover:border-black/20 hover:text-[#17143A] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.9"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 8h10M9.25 4.25 13 8l-3.75 3.75"/>
                            </svg>
                        </button>
                        <p class="text-[12.5px] text-[#7A8092]">Drag a photo aside to see the next one.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ---- schedule a demo ---- --}}
    {{-- id="schedule-demo" is the anchor for the navbar's "Book a demo", the
         hero CTA, the Enterprise card and both "Talk to an expert" buttons in
         the compare table. Renaming it means renaming all five together. --}}
    <section id="schedule-demo" class="relative border-t border-black/[.05] bg-white px-6 py-20 sm:py-24">
        {{-- No items-start here: the grid's default stretch is what makes the
             two columns share a height, and the cream card is a flex column so
             the slack lands in the gaps between its blocks rather than as dead
             space under the last one. --}}
        <div class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-2 lg:gap-12">

            {{-- ----------------- left: the pitch ------------------ --}}
            <div class="demo-card flex flex-col justify-between p-7 pb-10 sm:p-9 sm:pb-12">

                <div class="flex items-center">
                    <span class="text-[17px] font-semibold tracking-tight">Clarix</span>
                </div>

                {{-- Two stacked cards, the second pulled up and right so it
                     reads as floating over the first. Negative margin rather
                     than absolute positioning, so the block still sizes
                     itself and nothing overlaps the headline below. --}}
                <div class="mt-8">
                    <div class="rounded-2xl border border-black/[.07] bg-white p-4 shadow-[0_10px_26px_-14px_rgba(14,17,38,.30)]">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF0FF]">
                                <svg class="h-3.5 w-3.5 text-indigo-600" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 3.2a4.6 4.6 0 0 1 4.6 4.6c0 3.9 1.5 5.3 1.5 5.3H3.9s1.5-1.4 1.5-5.3A4.6 4.6 0 0 1 10 3.2Z"/>
                                    <path d="M8.5 15.7a1.7 1.7 0 0 0 3 0"/>
                                </svg>
                            </span>
                            <span class="text-[12.5px] font-semibold text-[#17143A]">New task assigned</span>
                            <span class="ml-auto text-[11px] text-[#9A9FB0]">2m ago</span>
                        </div>

                        <p class="mt-3 text-[13.5px] font-medium leading-snug text-[#17143A]">
                            Northwind, Q3 campaign landing page
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-md bg-[#EEF0FF] px-2 py-1 text-[10.5px] font-medium text-indigo-700">In Progress</span>
                            <span class="rounded-md bg-[#FDF0E4] px-2 py-1 text-[10.5px] font-medium text-[#9A5B12]">High priority</span>
                            <span class="rounded-md bg-black/[.04] px-2 py-1 text-[10.5px] font-medium text-[#6B7086]">8 credits</span>
                        </div>
                    </div>

                    <div class="relative -mt-3 ml-auto w-[82%] rounded-2xl border border-black/[.07] bg-white p-3.5 shadow-[0_18px_34px_-16px_rgba(14,17,38,.36)] sm:w-[72%]">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            <span class="text-[12px] font-semibold text-[#17143A]">Client viewed delivery</span>
                        </div>
                        <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-black/[.06]">
                            <div class="h-full w-[72%] rounded-full bg-emerald-500"></div>
                        </div>
                        <p class="mt-2 text-[11px] text-[#7A8092]">Delivery 72% complete</p>
                    </div>
                </div>

                <h2 class="mt-9 font-display text-[26px] font-normal leading-[1.1] tracking-[-0.02em] text-[#17143A] sm:text-[30px]">
                    See Clarix in action
                </h2>

                <ul class="mt-6 flex flex-col gap-4">
                    @foreach ($demoPoints as $point)
                        <li class="flex gap-3">
                            <span class="mt-[3px] flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-indigo-600">
                                <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                     stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 8.5 6.4 12 13 4.8"/>
                                </svg>
                            </span>
                            <span class="text-[13.5px] leading-relaxed text-[#4A4F63]">{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>

            </div>

            {{-- ------------------ right: the form ----------------- --}}
            <div class="lg:pt-3">

                <h2 class="font-display text-[30px] font-normal leading-[1.08] tracking-[-0.02em] text-[#17143A] sm:text-[38px]">
                    Schedule a demo
                </h2>

                {{-- Static for now: /home is a Route::view and there is no demo
                     endpoint yet. To wire it up, give the form a method and
                     action, add @csrf, and drop the x-data/@submit.prevent
                     that currently stops it submitting to itself over GET. --}}
                <form x-data @submit.prevent class="mt-8 flex flex-col gap-5">
                    @foreach ($demoFields as $field)
                        @php $required = $field['required'] ?? false; @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <label class="demo-label" for="demo-{{ $field['name'] }}">{{ $field['label'] }}</label>
                                @if ($required)
                                    <span class="demo-req" aria-hidden="true">Required</span>
                                @endif
                            </div>

                            <div class="mt-2">
                                @if ($field['type'] === 'select')
                                    <select class="demo-field" id="demo-{{ $field['name'] }}" name="{{ $field['name'] }}">
                                        <option value="">Select a size</option>
                                        @foreach ($field['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="demo-field"
                                           id="demo-{{ $field['name'] }}"
                                           name="{{ $field['name'] }}"
                                           type="{{ $field['type'] }}"
                                           @if (isset($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                                           @if (isset($field['autocomplete'])) autocomplete="{{ $field['autocomplete'] }}" @endif
                                           @required($required)>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <button type="submit"
                            class="mt-2 w-full rounded-[10px] bg-indigo-600 px-6 py-3 text-[14px] font-semibold text-white transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Send
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- ---- footer ---- --}}
    <footer class="site-footer relative z-10 text-white">
        <div class="mx-auto max-w-7xl px-6 py-14 sm:py-16">
            {{-- The dividers are the columns' own left borders, so they only
                 appear at lg where the four columns actually sit side by side. --}}
            <div class="grid gap-y-12 sm:grid-cols-2 sm:gap-x-10 lg:grid-cols-[1.5fr_1fr_1fr_1fr] lg:gap-x-0">

                <div class="lg:pr-12">
                    <span class="text-[19px] font-semibold tracking-tight text-white">Clarix</span>

                    <ul class="mt-6 flex flex-wrap gap-2.5">
                        @foreach ($footerSocial as $social)
                            <li>
                                <a href="{{ $social['href'] }}" aria-label="Clarix on {{ $social['name'] }}"
                                   class="flex h-9 w-9 items-center justify-center rounded-full bg-white/[.13] text-white transition hover:bg-white/[.26] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                    <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="{{ $social['path'] }}"/>
                                    </svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-8 text-[12.5px] text-white/70">
                        &copy; {{ date('Y') }} Clarix Tech. All rights reserved.
                    </p>
                    <p class="mt-1.5 text-[11.5px] text-white/55">
                        Built by Code Next Door
                    </p>
                </div>

                @foreach ($footerNav as $heading => $links)
                    <div class="lg:border-l lg:border-white/[.14] lg:pl-8 xl:pl-12">
                        <h2 class="text-[13px] font-semibold tracking-tight text-white">{{ $heading }}</h2>
                        <ul class="mt-5 flex flex-col gap-3.5">
                            @foreach ($links as [$label, $href])
                                <li>
                                    <a href="{{ $href }}"
                                       class="text-[13.5px] text-white/65 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </footer>

</body>
</html>
