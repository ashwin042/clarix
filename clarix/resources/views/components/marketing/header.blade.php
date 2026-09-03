@php
    use App\Support\MarketingNav;

    $menus = MarketingNav::menus();
    $links = MarketingNav::links();
@endphp

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

            @foreach ($menus as $label => $menu)
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
                        @foreach ($menu['items'] as $item)
                            <a href="{{ MarketingNav::href($item) }}"
                               @if (MarketingNav::isExternal($item)) target="_blank" rel="noopener noreferrer" @endif
                               class="block rounded-xl px-3 py-2.5 transition hover:bg-black/[.035] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <span class="block text-[13.5px] font-semibold tracking-tight text-[#0F1222]">{{ $item['label'] }}</span>
                                @if ($item['description'] ?? null)
                                    <span class="mt-0.5 block text-[12.5px] leading-snug text-[#7A8092]">{{ $item['description'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @foreach ($links as $item)
                <a href="{{ MarketingNav::href($item) }}"
                   class="flex items-center rounded-full px-3.5 py-2 text-[14px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $item['label'] }}
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
                <a href="/home#schedule-demo"
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
            @foreach ($menus as $label => $menu)
                <span class="px-2 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-[.1em] text-[#A1A6B4]">{{ $label }}</span>
                @foreach ($menu['items'] as $item)
                    <a href="{{ MarketingNav::href($item) }}"
                       @if (MarketingNav::isExternal($item)) target="_blank" rel="noopener noreferrer" @endif
                       class="rounded-lg px-2 py-2 text-[14.5px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222]">{{ $item['label'] }}</a>
                @endforeach
            @endforeach

            <span class="mt-3 border-t border-black/[.06]"></span>
            @foreach ($links as $item)
                <a href="{{ MarketingNav::href($item) }}" class="mt-2 rounded-lg px-2 py-2.5 text-[15px] font-medium text-[#4A4F63] transition hover:bg-black/[.04] hover:text-[#0F1222]">{{ $item['label'] }}</a>
            @endforeach
            @guest
                <a href="/home#schedule-demo" class="mt-2 rounded-lg px-2 py-2.5 text-[15px] font-medium text-[#0F1222] transition hover:bg-black/[.04] sm:hidden">Book a demo</a>
            @endguest
        </nav>
    </div>
</header>
