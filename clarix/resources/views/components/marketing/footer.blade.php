@php
    use App\Support\MarketingNav;
@endphp

<footer class="site-footer relative z-10 text-white">
    <div class="mx-auto max-w-7xl px-6 py-14 sm:py-16">
        {{-- The dividers are the columns' own left borders, so they only
             appear at lg where the four columns actually sit side by side. --}}
        <div class="grid gap-y-12 sm:grid-cols-2 sm:gap-x-10 lg:grid-cols-[1.5fr_1fr_1fr_1fr] lg:gap-x-0">

            <div class="lg:pr-12">
                <span class="text-[19px] font-semibold tracking-tight text-white">Clarix</span>

                <ul class="mt-6 flex flex-wrap gap-2.5">
                    @foreach (MarketingNav::social() as $social)
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
                    Built by
                    {{-- Off-site, so the same target/rel convention the nav
                         uses for the AXOKAI link applies here. --}}
                    <a href="https://codesnextdoor.com/" target="_blank" rel="noopener noreferrer"
                       class="font-medium text-white/75 underline-offset-2 transition hover:text-white hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Code Next Door
                    </a>
                </p>
            </div>

            @foreach (MarketingNav::footer() as $heading => $items)
                <div class="lg:border-l lg:border-white/[.14] lg:pl-8 xl:pl-12">
                    <h2 class="text-[13px] font-semibold tracking-tight text-white">{{ $heading }}</h2>
                    <ul class="mt-5 flex flex-col gap-3.5">
                        @foreach ($items as $item)
                            <li>
                                <a href="{{ MarketingNav::href($item) }}"
                                   @if (MarketingNav::isExternal($item)) target="_blank" rel="noopener noreferrer" @endif
                                   class="text-[13.5px] text-white/65 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</footer>
