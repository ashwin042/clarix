@php
    /*
     * $articles comes from App\Services\TechNewsFeed, via BlogController.
     *
     * Everything in it belongs to somebody else. This page is a reading list,
     * not a publication: each card carries the headline, the snippet the API
     * itself returns, the publication's own name and the date, and the whole
     * card is a link out to the original. Clarix never renders article bodies
     * and never presents a headline as its own writing — the section head and
     * the per-card attribution both say so, and MarketingResourcesTest fails
     * if either goes missing.
     *
     * An empty $articles is the normal failure state, not an exception: the
     * feed swallows outages and returns nothing, and the fallback panel below
     * takes over.
     */

    // Every one of these is a page that exists today, which is the point of
    // the section: whatever the feed is doing, the product is documented.
    $meanwhile = [
        ['label' => 'Documentation',    'copy' => 'How each part of Clarix works, area by area.', 'route' => 'marketing.docs'],
        ['label' => 'Help Center',      'copy' => 'The questions that come up most, answered.',   'route' => 'marketing.help'],
    ];
@endphp

<x-marketing.layout
    title="Tech News — Clarix"
    description="A curated feed of technology headlines from around the web — every article linked to its original publication."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-14 pt-12 sm:pt-16 lg:pb-20">
            <x-marketing.page-hero
                eyebrow="Tech News"
                heading="What the industry is reading today."
                lede="The stories moving technology today — gathered from publishers around the world, and always credited to them."
                align="center"
            />
        </div>
    </main>

    {{-- ============================ The feed =========================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="The feed"
            heading="Tech news from around the world."
            lede="A curated selection of technology reporting from publishers worldwide, refreshed through the day. Every article is credited to the publication that wrote it, and opens at the original source."
        />

        @if ($articles)

            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    {{-- The whole card is the link. target/rel are on it rather
                         than on a nested "read more", so there is exactly one
                         destination and no nested interactive elements. --}}
                    <a href="{{ $article['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer nofollow"
                       class="group flex flex-col rounded-[18px] border border-[#E9E4D8] bg-[#FBFAF6] p-6 transition hover:border-[#C8C2AF] hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">

                        {{-- The monogram sits underneath the image rather than
                             beside it in a conditional: an image_url can pass
                             every check here and still 404, be hotlink-blocked,
                             or be an http url on an https page. onerror takes
                             the <img> out and the panel below it becomes the
                             card's art, so a thumbnail never fails to a broken
                             icon or an empty box. Fixed aspect ratio, so the
                             grid stays even whichever way it resolves. --}}
                        <div class="relative mb-5 aspect-[16/9] w-full overflow-hidden rounded-xl border border-[#E2DCCC] bg-[#F1ECDF]">
                            <span aria-hidden="true"
                                  class="absolute inset-0 flex items-center justify-center font-display text-[26px] tracking-[-0.02em] text-[#B9AF96]">
                                {{ $article['initials'] }}
                            </span>

                            @if ($article['image'])
                                <img src="{{ $article['image'] }}"
                                     alt=""
                                     loading="lazy"
                                     decoding="async"
                                     referrerpolicy="no-referrer"
                                     onerror="this.remove()"
                                     class="absolute inset-0 h-full w-full object-cover">
                            @endif
                        </div>

                        {{-- Attribution at reading size, because the
                             publication's name is the most important thing on
                             a card Clarix did not write. --}}
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0 truncate text-[13px] font-semibold tracking-tight text-[#17143A]">
                                {{ $article['source'] }}
                            </span>

                            @if ($article['published_at'])
                                <time datetime="{{ $article['published_at']->toIso8601String() }}"
                                      class="shrink-0 font-mono-ui text-[10px] uppercase tracking-[.08em] text-[#8A8FA0]">
                                    {{ $article['published_at']->diffForHumans(short: true) }}
                                </time>
                            @endif
                        </div>

                        <h3 class="mt-3.5 font-display text-[18px] font-normal leading-[1.28] tracking-[-0.015em] text-[#17143A]">
                            {{ $article['title'] }}
                        </h3>

                        @if ($article['description'])
                            <p class="mt-3 flex-1 text-[13.5px] leading-relaxed text-[#5B6076]">{{ $article['description'] }}</p>
                        @else
                            <div class="flex-1"></div>
                        @endif

                        <span class="mt-5 inline-flex items-center gap-1.5 border-t border-[#E2DCCC] pt-4 font-mono-ui text-[10.5px] text-[#8A8FA0]">
                            <span class="truncate">Read at {{ $article['domain'] ?: $article['source'] }}</span>
                            <svg class="h-3 w-3 shrink-0 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5"
                                 viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 3.5h6.5V10M12.5 3.5 3.5 12.5"/>
                            </svg>
                            <span class="sr-only">(opens the original article on {{ $article['domain'] ?: $article['source'] }}, in a new tab)</span>
                        </span>
                    </a>
                @endforeach
            </div>

            <p class="mt-10 text-center text-[12.5px] text-[#8A8FA0]">
                Headlines and summaries via <a href="https://newsdata.io" target="_blank" rel="noopener noreferrer"
                    class="font-medium text-[#5B6076] underline-offset-2 hover:underline">NewsData.io</a>.
                All articles are the property of their respective publications.
            </p>

        @else

            {{-- The API is down, rate-limited, or has no key in this
                 environment. The page keeps its shape rather than collapsing
                 into a stack trace, and the section below still gives the
                 reader somewhere to go. --}}
            <div class="mx-auto mt-14 max-w-xl rounded-[18px] border border-[#E9E4D8] bg-[#FBFAF6] px-7 py-10 text-center">
                <span class="font-mono-ui text-[10px] uppercase tracking-[.12em] text-[#8A8FA0]">Feed unavailable</span>

                <h3 class="mt-3.5 font-display text-[22px] font-normal leading-[1.2] tracking-[-0.015em] text-[#17143A]">
                    We couldn't load the news right now.
                </h3>

                <p class="mt-3 text-[14px] leading-relaxed text-[#5B6076]">
                    The feed comes from a third party, and it is not answering at the moment.
                    It refreshes on its own — try again in a few minutes.
                </p>
            </div>

        @endif
    </x-marketing.section>

    {{-- ========================= In the meantime ======================= --}}
    <x-marketing.section>
        <x-marketing.section-head
            eyebrow="From us, though"
            heading="What we have actually written."
            lede="The product documentation, the stories behind the kinds of team Clarix is built for, and the answers to the questions that come up first — all of it ours."
        />

        <div class="mt-14 grid gap-px overflow-hidden rounded-2xl border border-black/[.07] bg-black/[.06] sm:grid-cols-2">
            @foreach ($meanwhile as $item)
                <a href="{{ route($item['route']) }}"
                   class="group bg-white p-7 transition hover:bg-[#FAFAFC] focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600">
                    <span class="flex items-center gap-1.5 text-[15px] font-semibold tracking-tight">
                        {{ $item['label'] }}
                        <svg class="h-3.5 w-3.5 text-indigo-600 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                    </span>
                    <span class="mt-2 block text-[14px] leading-relaxed text-[#5B6076]">{{ $item['copy'] }}</span>
                </a>
            @endforeach
        </div>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Skip the reading and run a task through it."
        lede="Brief one piece of client work in Clarix and the rest of the argument makes itself."
    />

</x-marketing.layout>
