<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clarix — Project Management, Reimagined</title>
    <meta name="description" content="Clarix brings your tasks, team roles, credit tracking and financial reporting into one clean portal. No more spreadsheets. No more chaos.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; overflow-x: hidden; }

        @keyframes fadeInUp  { from { opacity:0; transform:translateY(28px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn    { from { opacity:0; } to { opacity:1; } }
        @keyframes scaleIn   { from { opacity:0; transform:scale(.93); } to { opacity:1; transform:scale(1); } }
        @keyframes floatA    { 0%,100%{ transform:translateY(0)   rotate(-4deg); } 50%{ transform:translateY(-10px) rotate(-4deg); } }
        @keyframes floatB    { 0%,100%{ transform:translateY(0)   rotate(3deg);  } 50%{ transform:translateY(-8px)  rotate(3deg);  } }

        .anim-fade-up  { animation: fadeInUp .7s  ease-out both; }
        .anim-fade-in  { animation: fadeIn   .6s  ease-out both; }
        .anim-scale-in { animation: scaleIn  .65s ease-out both; }
        .anim-d1 { animation-delay:.10s; }
        .anim-d2 { animation-delay:.22s; }
        .anim-d3 { animation-delay:.36s; }
        .anim-d4 { animation-delay:.50s; }
        .anim-d5 { animation-delay:.64s; }

        .card-float-a { animation: floatA 5.2s ease-in-out infinite; }
        .card-float-b { animation: floatB 5.8s ease-in-out .7s infinite; }

        .reveal { opacity:0; transform:translateY(22px); transition:opacity .55s ease, transform .55s ease; }
        .reveal.in { opacity:1; transform:translateY(0); }
        .reveal-d1 { transition-delay:.08s; }
        .reveal-d2 { transition-delay:.18s; }
        .reveal-d3 { transition-delay:.28s; }
        .reveal-d4 { transition-delay:.10s; }
        .reveal-d5 { transition-delay:.20s; }
        .reveal-d6 { transition-delay:.30s; }

        /* Navbar */
        .navbar {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(124,58,237,.08);
            transition: box-shadow .2s ease;
        }
        .navbar.scrolled { box-shadow: 0 2px 24px rgba(0,0,0,.07); }

        /* Hero */
        .hero-bg {
            background:
                radial-gradient(ellipse 85% 50% at 50% -5%, rgba(167,139,250,.38) 0%, transparent 65%),
                radial-gradient(ellipse 42% 38% at 82% 18%,  rgba(196,181,253,.28) 0%, transparent 60%),
                linear-gradient(180deg, #ede9fe 0%, #f5f3ff 50%, #faf9ff 80%, #fff 100%);
        }
        .dot-grid {
            background-image: radial-gradient(circle, rgba(139,92,246,.16) 1px, transparent 1px);
            background-size: 28px 28px;
        }
        .gradient-text {
            background: linear-gradient(135deg, #3b0764 5%, #7c3aed 45%, #a855f7 85%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            box-shadow: 0 4px 16px rgba(124,58,237,.35), 0 1px 3px rgba(124,58,237,.2);
            transition: box-shadow .18s ease, transform .15s ease;
        }
        .btn-primary:hover { box-shadow: 0 8px 28px rgba(124,58,237,.48); transform: translateY(-2px); }
        .btn-ghost { transition: background .15s, color .15s; }
        .btn-ghost:hover { background: rgba(124,58,237,.06); color: #6d28d9; }

        /* Mock cards */
        .mock-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.06);
            border: 1px solid rgba(139,92,246,.09);
        }

        /* Section label pill */
        .s-label {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(139,92,246,.07); border: 1px solid rgba(139,92,246,.14);
            border-radius: 999px; padding: 5px 14px;
            font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #6d28d9;
        }

        /* Why Clarix image cards */
        .why-card {
            position: relative; overflow: hidden; border-radius: 20px; height: 340px;
            transition: transform .3s ease, box-shadow .3s ease;
        }
        .why-card:hover { transform: translateY(-6px); box-shadow: 0 24px 60px rgba(0,0,0,.18); }
        .why-bg {
            position: absolute; inset: 0; background-size: cover; background-position: center;
            transition: transform .5s ease;
        }
        .why-card:hover .why-bg { transform: scale(1.06); }
        .why-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(160deg, rgba(91,33,182,.62) 0%, rgba(15,10,30,.76) 100%);
        }
        .why-glass {
            position: absolute; bottom: 0; left: 0; right: 0; padding: 22px 24px;
            background: rgba(255,255,255,.10);
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid rgba(255,255,255,.2);
        }

        /* Feature cards */
        .feat-card { transition: transform .2s, box-shadow .2s; }
        .feat-card:hover { transform: translateY(-4px); box-shadow: 0 20px 48px rgba(124,58,237,.12); }

        /* Pricing */
        .price-card { transition: transform .2s, box-shadow .2s; }
        .price-card:hover { transform: translateY(-5px); box-shadow: 0 20px 48px rgba(0,0,0,.1); }
        .price-featured {
            background: linear-gradient(150deg, #6d28d9 0%, #7c3aed 55%, #8b5cf6 100%);
            box-shadow: 0 24px 64px rgba(109,40,217,.38);
            transform: scale(1.03);
        }
        .price-featured:hover { transform: scale(1.03) translateY(-5px); }

        /* Testimonial cards */
        .testi-card {
            background: rgba(255,255,255,.62);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.55);
            box-shadow: 0 8px 32px rgba(109,40,217,.09);
            border-radius: 20px;
            transition: transform .2s, box-shadow .2s;
        }
        .testi-card:hover { transform: translateY(-4px); box-shadow: 0 18px 48px rgba(109,40,217,.15); }

        /* Footer */
        .site-footer { background: #1e1b4b; }
        .footer-link { color: rgba(255,255,255,.32); transition: color .15s ease; font-size: 13.5px; }
        .footer-link:hover { color: rgba(255,255,255,.72); }

        /* Mobile menu */
        #mobile-menu { display: none; }
        #mobile-menu.open { display: block; }
    </style>
</head>
<body class="text-gray-900 bg-white">

{{-- ══════════════════ NAVBAR ══════════════════ --}}
<nav id="navbar" class="navbar fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-6 sm:px-10">
        <div class="flex items-center justify-between h-[74px]">

            <a href="/" class="flex items-center gap-3 flex-shrink-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:linear-gradient(135deg,#7c3aed,#5b21b6)">
                    <span class="text-white font-black text-base leading-none select-none">C</span>
                </div>
                <span class="font-bold text-gray-900 text-[19px] tracking-tight">Clarix</span>
            </a>

            <div class="hidden md:flex items-center gap-8">
                <a href="#features"     class="text-[15px] text-gray-500 hover:text-violet-700 font-medium transition-colors">Features</a>
                <a href="#use-cases"    class="text-[15px] text-gray-500 hover:text-violet-700 font-medium transition-colors">Use Cases</a>
                <a href="#pricing"      class="text-[15px] text-gray-500 hover:text-violet-700 font-medium transition-colors">Pricing</a>
                <a href="#testimonials" class="text-[15px] text-gray-500 hover:text-violet-700 font-medium transition-colors">Testimonials</a>
            </div>

            <div class="hidden md:flex items-center">
                <a href="{{ route('login') }}"
                   class="btn-primary text-white text-[14.5px] font-semibold px-5 py-2.5 rounded-xl inline-flex items-center gap-2">
                    Get started free
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            </div>

            <button id="menu-btn" onclick="toggleMenu()"
                    class="md:hidden p-2.5 rounded-xl text-gray-500 hover:bg-violet-50 hover:text-violet-700 transition-colors">
                <svg id="icon-open"  class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg id="icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div id="mobile-menu" class="md:hidden border-t bg-white/96 backdrop-blur-lg" style="border-color:rgba(139,92,246,.1)">
        <div class="px-5 py-4 space-y-1">
            <a href="#features"     onclick="closeMobileMenu()" class="block text-sm text-gray-600 font-medium px-3 py-2.5 rounded-lg hover:bg-violet-50 hover:text-violet-700 transition-colors">Features</a>
            <a href="#use-cases"    onclick="closeMobileMenu()" class="block text-sm text-gray-600 font-medium px-3 py-2.5 rounded-lg hover:bg-violet-50 hover:text-violet-700 transition-colors">Use Cases</a>
            <a href="#pricing"      onclick="closeMobileMenu()" class="block text-sm text-gray-600 font-medium px-3 py-2.5 rounded-lg hover:bg-violet-50 hover:text-violet-700 transition-colors">Pricing</a>
            <a href="#testimonials" onclick="closeMobileMenu()" class="block text-sm text-gray-600 font-medium px-3 py-2.5 rounded-lg hover:bg-violet-50 hover:text-violet-700 transition-colors">Testimonials</a>
            <div class="pt-3">
                <a href="{{ route('login') }}" class="btn-primary block text-sm font-semibold text-white rounded-lg px-4 py-2.5 text-center">
                    Get started free →
                </a>
            </div>
        </div>
    </div>
</nav>

{{-- ══════════════════ HERO ══════════════════ --}}
<section class="hero-bg relative overflow-hidden" style="min-height:100vh; padding-top:74px;">
    <div class="dot-grid absolute inset-0 opacity-50 pointer-events-none"></div>
    <div class="absolute top-20 -left-40 w-96 h-96 rounded-full blur-3xl pointer-events-none"
         style="background:rgba(167,139,250,.18)"></div>
    <div class="absolute top-52 -right-24 w-72 h-72 rounded-full blur-3xl pointer-events-none"
         style="background:rgba(196,181,253,.20)"></div>

    <div class="relative max-w-6xl mx-auto px-5 sm:px-8 pt-20 pb-8 text-center">

        {{-- Pill badge --}}
        <div class="anim-fade-up inline-flex items-center gap-2 rounded-full px-4 py-1.5 mb-7"
             style="background:rgba(124,58,237,.08); border:1px solid rgba(124,58,237,.18);">
            <span>⚡</span>
            <span class="text-xs font-semibold text-violet-700 tracking-wide">Project Management, Reimagined</span>
        </div>

        {{-- Headline --}}
        <h1 class="anim-fade-up anim-d1 font-black text-[2.4rem] sm:text-[3.1rem] lg:text-[3.7rem] leading-[1.06] tracking-[-0.04em] text-gray-900 max-w-[720px] mx-auto mb-6">
            The smarter way to manage<br>
            <span class="gradient-text">projects, teams and payments</span>
        </h1>

        {{-- Subheading --}}
        <p class="anim-fade-up anim-d2 text-[1.05rem] sm:text-lg text-gray-500 leading-[1.72] max-w-[510px] mx-auto mb-10">
            Clarix brings your tasks, team roles, credit tracking and financial reporting into one clean portal. No more spreadsheets. No more chaos.
        </p>

        {{-- CTAs --}}
        <div class="anim-fade-up anim-d3 flex flex-col sm:flex-row items-center justify-center gap-3 mb-4">
            <a href="{{ route('login') }}"
               class="btn-primary text-white font-bold text-[15px] px-7 py-3.5 rounded-xl inline-flex items-center gap-2">
                Get started free
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
            <a href="#use-cases"
               class="btn-ghost text-gray-600 font-semibold text-[15px] px-7 py-3.5 rounded-xl inline-flex items-center gap-2 border border-gray-200 hover:border-violet-300">
                See how it works
            </a>
        </div>

        <p class="anim-fade-up anim-d4 text-xs text-gray-400 mb-14">No credit card required · Free to get started</p>

        {{-- Floating mock cards --}}
        <div class="anim-scale-in anim-d5 relative mx-auto" style="max-width:640px; height:290px;">

            {{-- Left: Credits card --}}
            <div class="mock-card card-float-a absolute w-[200px]"
                 style="left:10px; top:38px; z-index:2;">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background:#fef3c7">
                            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-gray-600">Credits Earned</span>
                    </div>
                    <div class="text-[1.4rem] font-black text-gray-900 tracking-tight mb-0.5">Rs 12,450</div>
                    <div class="flex items-center gap-1 mb-3">
                        <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        <span class="text-[10px] font-semibold text-emerald-600">+24% this month</span>
                    </div>
                    <div class="flex gap-1 items-end h-9">
                        @foreach([35,55,42,70,58,85,72] as $h)
                        <div class="flex-1 rounded-sm"
                             style="height:{{ $h }}%; background:linear-gradient(180deg,#a855f7,#7c3aed); opacity:{{ 0.35 + ($loop->index * 0.09) }}"></div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right: Active Tasks card --}}
            <div class="mock-card card-float-b absolute w-[280px]"
                 style="right:10px; top:0; z-index:3;">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-violet-500"></div>
                        <span class="text-xs font-semibold text-gray-700">Active Tasks</span>
                    </div>
                    <span class="text-[10px] font-semibold text-violet-600 bg-violet-50 px-2 py-0.5 rounded-full">4 tasks</span>
                </div>
                <div class="p-3 space-y-1.5">
                    @php
                    $tasks = [
                        ['Write Q4 strategy report', 'done',    'HC'],
                        ['Design onboarding flow',   'active',  'AM'],
                        ['Review content batch',     'active',  'SR'],
                        ['Update payment records',   'pending', 'JD'],
                    ];
                    $badge = [
                        'done'    => ['bg-emerald-100 text-emerald-700', 'bg-emerald-400', 'Done'],
                        'active'  => ['bg-blue-100 text-blue-700',       'bg-blue-400',    'Active'],
                        'pending' => ['bg-amber-100 text-amber-700',      'bg-amber-400',   'Pending'],
                    ];
                    @endphp
                    @foreach($tasks as $t)
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $badge[$t[1]][1] }}"></div>
                        <span class="flex-1 text-xs text-gray-700 truncate font-medium">{{ $t[0] }}</span>
                        <div class="w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-[7.5px] font-bold text-violet-600">{{ $t[2] }}</span>
                        </div>
                        <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $badge[$t[1]][0] }}">
                            {{ $badge[$t[1]][2] }}
                        </span>
                    </div>
                    @endforeach
                </div>
                <div class="px-4 pb-3 pt-1">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] text-gray-400 font-medium">Sprint progress</span>
                        <span class="text-[10px] font-bold text-violet-600">67%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full" style="width:67%; background:linear-gradient(90deg,#7c3aed,#a855f7)"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Wave divider --}}
    <div class="relative -mb-px">
        <svg viewBox="0 0 1440 60" xmlns="http://www.w3.org/2000/svg" class="w-full"
             preserveAspectRatio="none" style="height:60px; display:block;">
            <path d="M0 60L60 50C120 40 240 20 360 15C480 10 600 20 720 28C840 36 960 40 1080 36C1200 32 1320 20 1380 14L1440 8V60H0Z" fill="white"/>
        </svg>
    </div>
</section>

{{-- ══════════════════ WHY CLARIX / USE CASES ══════════════════ --}}
<section id="use-cases" class="bg-white py-20">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">

        <div class="text-center mb-14 reveal">
            <div class="s-label mb-5">✦ One Stop Solution</div>
            <h2 class="text-[2rem] sm:text-[2.4rem] font-black tracking-tight text-gray-900 mb-4 leading-tight">
                Built for how teams<br class="hidden sm:block"> actually work
            </h2>
            <p class="text-gray-500 text-base sm:text-lg max-w-xl mx-auto leading-relaxed">
                Stop juggling spreadsheets, emails, and disconnected tools. Clarix gives every role exactly what they need.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="why-card reveal reveal-d1">
                <div class="why-bg" style="background-image:url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&q=80&auto=format&fit=crop')"></div>
                <div class="why-overlay"></div>
                <div class="why-glass">
                    <h3 class="text-white font-bold text-[1.05rem] mb-2">Effortless Collaboration</h3>
                    <p class="text-sm leading-relaxed" style="color:rgba(255,255,255,.72)">Assign tasks, manage writers, and keep PMs in sync without back-and-forth emails.</p>
                </div>
            </div>

            <div class="why-card reveal reveal-d2">
                <div class="why-bg" style="background-image:url('https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&q=80&auto=format&fit=crop')"></div>
                <div class="why-overlay"></div>
                <div class="why-glass">
                    <h3 class="text-white font-bold text-[1.05rem] mb-2">Real-time Credit Tracking</h3>
                    <p class="text-sm leading-relaxed" style="color:rgba(255,255,255,.72)">Every completed task logs credits automatically. Know exactly who earned what and when.</p>
                </div>
            </div>

            <div class="why-card reveal reveal-d3">
                <div class="why-bg" style="background-image:url('https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=800&q=80&auto=format&fit=crop')"></div>
                <div class="why-overlay"></div>
                <div class="why-glass">
                    <h3 class="text-white font-bold text-[1.05rem] mb-2">Role-Based Access</h3>
                    <p class="text-sm leading-relaxed" style="color:rgba(255,255,255,.72)">Admins see everything. Project managers see their units. Team members see only their work.</p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════ FEATURES GRID ══════════════════ --}}
<section id="features" class="py-20" style="background:linear-gradient(180deg,#f8f7ff 0%,#f0edff 100%)">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">

        <div class="text-center mb-14 reveal">
            <div class="s-label mb-5">✦ Everything Covered</div>
            <h2 class="text-[2rem] sm:text-[2.4rem] font-black tracking-tight text-gray-900 mb-4 leading-tight">
                Some more Clarix features
            </h2>
            <p class="text-gray-500 text-base sm:text-lg max-w-xl mx-auto leading-relaxed">
                Every tool your team needs — from task creation to final payout.
            </p>
        </div>

        @php
        $features = [
            [
                'title' => 'Smart Task Assignment',
                'desc'  => 'Create tasks with priorities, deadlines, credit values and assign to the right people instantly.',
                'color' => 'violet',
                'cls'   => 'bg-violet-50 text-violet-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
            ],
            [
                'title' => 'Credit & Payment System',
                'desc'  => 'Track credits per task, monitor earnings per writer, and manage payouts from one dashboard.',
                'color' => 'amber',
                'cls'   => 'bg-amber-50 text-amber-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
            ],
            [
                'title' => 'Financial Dashboard',
                'desc'  => 'Revenue vs credits charts, unit profitability, pending payments and net profit at a glance.',
                'color' => 'emerald',
                'cls'   => 'bg-emerald-50 text-emerald-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            ],
            [
                'title' => 'File Management',
                'desc'  => 'Project managers upload task files directly. Writers access only what is assigned to them.',
                'color' => 'blue',
                'cls'   => 'bg-blue-50 text-blue-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>',
            ],
            [
                'title' => 'Issue Reporting',
                'desc'  => 'Built-in issue tracker tied to tasks so nothing falls through the cracks.',
                'color' => 'rose',
                'cls'   => 'bg-rose-50 text-rose-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
            ],
            [
                'title' => 'Multi-Role Portal',
                'desc'  => 'One system, three experiences. Admin, Project Manager and Writer each get a tailored view.',
                'color' => 'purple',
                'cls'   => 'bg-purple-50 text-purple-600',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
            ],
        ];
        $fd = ['reveal-d4','reveal-d5','reveal-d6','reveal-d4','reveal-d5','reveal-d6'];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($features as $i => $f)
            <div class="feat-card reveal {{ $fd[$i] }} bg-white rounded-2xl p-6 shadow-sm group"
                 style="border:1px solid rgba(139,92,246,.09);">
                <div class="w-11 h-11 rounded-xl {{ $f['cls'] }} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $f['icon'] !!}</svg>
                </div>
                <h3 class="font-bold text-gray-900 mb-2 text-[15px]">{{ $f['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>

    </div>
</section>

{{-- ══════════════════ PRICING ══════════════════ --}}
<section id="pricing" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">

        <div class="text-center mb-14 reveal">
            <div class="s-label mb-5">Pay as you grow</div>
            <h2 class="text-[2rem] sm:text-[2.4rem] font-black tracking-tight text-gray-900 mb-4 leading-tight">
                Simple, transparent pricing
            </h2>
            <p class="text-gray-500 text-base sm:text-lg max-w-lg mx-auto leading-relaxed">
                Choose a plan that fits your needs. Upgrade anytime.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 items-center">

            {{-- Free --}}
            <div class="price-card reveal reveal-d1 bg-white rounded-2xl p-7 shadow-sm"
                 style="border:1px solid rgba(139,92,246,.12);">
                <div class="mb-6">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Free</span>
                    <div class="mt-2 flex items-end gap-1">
                        <span class="text-4xl font-black text-gray-900">Rs 0</span>
                        <span class="text-sm text-gray-400 mb-1.5">/month</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed">For individuals getting started.</p>
                </div>
                <a href="{{ route('login') }}"
                   class="w-full block text-center py-2.5 rounded-xl text-sm font-semibold text-violet-700 border-2 border-violet-200 hover:bg-violet-50 transition-colors mb-6">
                    Get started free
                </a>
                <ul class="space-y-2.5">
                    @foreach(['Up to 3 projects','5 team members','Basic task management','Email support'] as $feat)
                    <li class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Standard (featured) --}}
            <div class="price-card price-featured reveal reveal-d2 rounded-2xl p-7 relative overflow-hidden">
                <div class="absolute top-4 right-4 text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full"
                     style="background:rgba(255,255,255,.18); color:white; letter-spacing:.08em;">
                    Recommended
                </div>
                <div class="mb-6">
                    <span class="text-[10px] font-bold uppercase tracking-widest" style="color:rgba(221,214,254,.9)">Standard</span>
                    <div class="mt-2 flex items-end gap-1">
                        <span class="text-4xl font-black text-white">Rs 4,500</span>
                        <span class="text-sm mb-1.5" style="color:rgba(221,214,254,.8)">/month</span>
                    </div>
                    <p class="text-sm mt-2 leading-relaxed" style="color:rgba(221,214,254,.8)">For growing teams.</p>
                </div>
                <a href="{{ route('login') }}"
                   class="w-full block text-center py-2.5 rounded-xl text-sm font-semibold text-violet-700 bg-white hover:bg-violet-50 transition-colors mb-6 shadow-sm">
                    Get started →
                </a>
                <ul class="space-y-2.5">
                    @foreach(['Unlimited projects','Up to 25 team members','Credit tracking','Financial dashboard','File uploads up to 500MB','Priority support'] as $feat)
                    <li class="flex items-center gap-2 text-sm" style="color:rgba(255,255,255,.88)">
                        <svg class="w-4 h-4 flex-shrink-0" style="color:rgba(255,255,255,.55)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Premium --}}
            <div class="price-card reveal reveal-d3 bg-white rounded-2xl p-7 shadow-sm"
                 style="border:1px solid rgba(139,92,246,.12);">
                <div class="mb-6">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Premium</span>
                    <div class="mt-2 flex items-end gap-1">
                        <span class="text-3xl font-black text-gray-900">Rs 8,000</span>
                        <span class="text-sm text-gray-400 mb-1.5">/month</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed">For established businesses.</p>
                </div>
                <a href="{{ route('login') }}"
                   class="w-full block text-center py-2.5 rounded-xl text-sm font-semibold text-violet-700 border-2 border-violet-200 hover:bg-violet-50 transition-colors mb-6">
                    Get started
                </a>
                <ul class="space-y-2.5">
                    @foreach(['Everything in Standard','Unlimited team members','Advanced analytics','Custom roles','5GB file storage','Dedicated support'] as $feat)
                    <li class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Enterprise --}}
            <div class="price-card reveal reveal-d4 rounded-2xl p-7 shadow-sm"
                 style="border:1px solid rgba(139,92,246,.12); background:linear-gradient(160deg,#f8f7ff 0%,#fff 100%);">
                <div class="mb-6">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Enterprise</span>
                    <div class="mt-2">
                        <span class="text-3xl font-black text-gray-900">Custom</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed">For large organizations.</p>
                </div>
                <a href="mailto:hello@clarix.app"
                   class="w-full block text-center py-2.5 rounded-xl text-sm font-semibold text-violet-700 border-2 border-violet-200 hover:bg-violet-50 transition-colors mb-6">
                    Contact us
                </a>
                <ul class="space-y-2.5">
                    @foreach(['Everything in Premium','Custom integrations','SLA guarantee','On-premise option','Account manager'] as $feat)
                    <li class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</section>

{{-- ══════════════════ TESTIMONIALS ══════════════════ --}}
<section id="testimonials" class="py-20" style="background:linear-gradient(135deg,#ede9fe 0%,#f5f3ff 60%,#ede9fe 100%)">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">

        <div class="text-center mb-14 reveal">
            <h2 class="text-[2rem] sm:text-[2.4rem] font-black tracking-tight text-gray-900 mb-4 leading-tight">
                What our users say
            </h2>
            <p class="text-gray-500 text-base sm:text-lg max-w-lg mx-auto leading-relaxed">
                Real stories from teams that made the switch to Clarix.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            @php
            $testimonials = [
                [
                    'quote'    => 'Before Clarix, I had no idea which writer was working on what, or how much we owed them at month end. Now everything is transparent. Our payouts are accurate and our team actually trusts the numbers.',
                    'name'     => 'Aarav Sharma',
                    'role'     => 'Founder & CEO',
                    'company'  => 'ContentBridge',
                    'initials' => 'AS',
                    'bg'       => 'bg-violet-100',
                    'text'     => 'text-violet-700',
                ],
                [
                    'quote'    => 'The role-based access is what sold me. My writers only see their own tasks, I see my unit, and the admin sees everything. Zero confusion, zero overlap. It just works exactly as you would expect it to.',
                    'name'     => 'Priya Thapa',
                    'role'     => 'Project Manager',
                    'company'  => 'Digital Yak Studio',
                    'initials' => 'PT',
                    'bg'       => 'bg-blue-100',
                    'text'     => 'text-blue-700',
                ],
                [
                    'quote'    => 'We tracked sprint progress in a shared Google Sheet that was always outdated. Clarix replaced it overnight. Our delivery speed went up by 40% in the first month — the data was just always there.',
                    'name'     => 'Rahul Karki',
                    'role'     => 'Team Lead',
                    'company'  => 'WriteRight Media',
                    'initials' => 'RK',
                    'bg'       => 'bg-emerald-100',
                    'text'     => 'text-emerald-700',
                ],
            ];
            @endphp

            @foreach($testimonials as $i => $t)
            <div class="testi-card reveal reveal-d{{ $i + 1 }} p-7 flex flex-col">
                <div class="flex items-center gap-0.5 mb-5">
                    @for ($s = 0; $s < 5; $s++)
                    <svg class="w-4 h-4 fill-current text-amber-400" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @endfor
                </div>
                <p class="text-gray-700 text-sm leading-relaxed flex-1 mb-6">"{{ $t['quote'] }}"</p>
                <div class="flex items-center gap-3 pt-5" style="border-top:1px solid rgba(255,255,255,.55)">
                    <div class="w-9 h-9 rounded-full {{ $t['bg'] }} flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-bold {{ $t['text'] }}">{{ $t['initials'] }}</span>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-gray-900">{{ $t['name'] }}</div>
                        <div class="text-xs text-gray-500">{{ $t['role'] }} · {{ $t['company'] }}</div>
                    </div>
                </div>
            </div>
            @endforeach

        </div>
    </div>
</section>

{{-- ══════════════════ FOOTER ══════════════════ --}}
<footer class="site-footer text-gray-400">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 pt-16 pb-8">

        <div class="grid grid-cols-2 md:grid-cols-5 gap-10 pb-12"
             style="border-bottom:1px solid rgba(255,255,255,.06)">

            <div class="col-span-2">
                <a href="/" class="flex items-center gap-2.5 mb-4">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background:linear-gradient(135deg,#7c3aed,#5b21b6)">
                        <span class="text-white font-black text-sm leading-none">C</span>
                    </div>
                    <span class="text-white font-bold text-[17px] tracking-tight">Clarix</span>
                </a>
                <p class="text-sm leading-relaxed max-w-[220px]"
                   style="color:rgba(255,255,255,.32)">
                    The smarter way to manage projects, teams and payments.
                </p>
            </div>

            @php
            $footerLinks = [
                'Product' => ['Features', 'Pricing', 'Changelog'],
                'Company' => ['About', 'Blog', 'Careers'],
                'Support' => ['Help Center', 'Contact', 'Privacy Policy'],
            ];
            @endphp

            @foreach($footerLinks as $heading => $links)
            <div>
                <h4 class="text-[10px] font-bold uppercase tracking-widest mb-4"
                    style="color:rgba(255,255,255,.38)">{{ $heading }}</h4>
                <ul class="space-y-2.5">
                    @foreach($links as $link)
                    <li>
                        <a href="#" class="footer-link">{{ $link }}</a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endforeach

        </div>

        <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-xs" style="color:rgba(255,255,255,.22)">© {{ date('Y') }} Clarix. All rights reserved.</p>
            <p class="text-xs" style="color:rgba(255,255,255,.14)">Built with Laravel &amp; Tailwind CSS.</p>
        </div>

    </div>
</footer>

<script>
function toggleMenu() {
    const menu  = document.getElementById('mobile-menu');
    const open  = document.getElementById('icon-open');
    const close = document.getElementById('icon-close');
    const isOpen = menu.classList.contains('open');
    menu.classList.toggle('open', !isOpen);
    open.classList.toggle('hidden', !isOpen);
    close.classList.toggle('hidden', isOpen);
}
function closeMobileMenu() {
    document.getElementById('mobile-menu').classList.remove('open');
    document.getElementById('icon-open').classList.remove('hidden');
    document.getElementById('icon-close').classList.add('hidden');
}
document.addEventListener('click', e => {
    if (!document.querySelector('nav').contains(e.target)) closeMobileMenu();
});
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const t = document.querySelector(a.getAttribute('href'));
        if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 8);
});
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
}, { threshold: 0.1, rootMargin: '0px 0px -36px 0px' });
document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
</script>
</body>
</html>
