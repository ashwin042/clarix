@php
    use App\Support\CustomerStories;

    /*
     * The stories live in App\Support\CustomerStories, not here, so that the
     * rule this page rests on can be tested against the data rather than
     * against rendered markup: a story is illustrative unless it both sets
     * 'real' and names an approved company, and the marker below is driven by
     * that check rather than by a page-wide flag.
     *
     * Adding a story here does not require touching this file at all.
     */
    // Defaulted rather than assigned, so a test can render this page with a
    // story of its own and check how the marker behaves.
    $featured = $featured ?? CustomerStories::featured();
    $stories  = $stories ?? CustomerStories::others();

    // Whether the notice below describes a page of examples or a mixed one.
    // Derived from the stories actually in scope, so the wording cannot fall
    // out of step with what is printed underneath it.
    $allIllustrative = ! collect(array_merge([$featured], $stories))
        ->contains(fn (array $story) => CustomerStories::isReal($story));

    // Each shape maps onto a Solutions page that is real.
    $shapes = [
        ['label' => 'You run an agency',   'copy' => 'Several units, several clients, and a boundary between them.', 'route' => 'marketing.solutions.agencies'],
        ['label' => 'You work alone',      'copy' => 'One person, a handful of clients, and no time for process.',   'route' => 'marketing.solutions.freelancers'],
        ['label' => 'You run departments', 'copy' => 'Delivery at scale, with governance and an audit trail.',       'route' => 'marketing.solutions.enterprises'],
    ];
@endphp

<x-marketing.layout
    title="Customer Stories — Clarix"
    description="Illustrative examples of how agencies, solo practices and in-house teams set Clarix up: units, roles, plans and the client view."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[560px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-10 pt-12 sm:pt-16">
            <x-marketing.page-hero
                eyebrow="Customer Stories"
                heading="How agencies actually set Clarix up."
                lede="Not testimonials — configurations. What a fourteen-person studio, a solo practice and a five-department team each do with units, roles and the ledger."
                align="center"
            />

            {{-- Said out loud, not buried in a comment. These are samples, and
                 a visitor is entitled to know that before reading a quote. --}}
            <div class="mx-auto mt-10 flex max-w-2xl items-start gap-3 rounded-2xl border border-[#E9E4D8] bg-[#FBFAF6] px-5 py-4">
                <span class="mt-[3px] font-mono-ui text-[9.5px] uppercase tracking-[.1em] text-[#B45309]">Illustrative</span>
                <p class="text-[13px] leading-relaxed text-[#5B6076]">
                    @if ($allIllustrative)
                        The studios on this page are invented, and so are the words attributed to them.
                        Clarix is new, and we would rather show the shapes of team it is built for than
                        put sentences in a real customer's mouth. Real stories will replace these, with
                        names attached, as they are recorded.
                    @else
                        Every story marked <em class="not-italic font-medium text-[#B45309]">illustrative</em>
                        is invented, and so are the words attributed to it — they show the shapes of team
                        Clarix is built for. Anything without that mark is a real customer, named and
                        quoted with their agreement.
                    @endif
                </p>
            </div>
        </div>
    </main>

    {{-- ========================= Featured story ======================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Featured"
            heading="{{ $featured['studio'] }}"
            lede="{{ $featured['shape'] }}"
        />

        <div class="mt-14 grid items-center gap-10 lg:grid-cols-2 lg:gap-16">

            <div>
                <h3 class="font-display text-[24px] font-normal leading-[1.2] tracking-[-0.018em] text-[#17143A] sm:text-[28px]">
                    {{ $featured['heading'] }}
                </h3>

                <p class="mt-5 text-[14.5px] leading-relaxed text-[#4A4F63]">{{ $featured['body'] }}</p>

                <blockquote class="mt-7 border-l-2 border-indigo-600 pl-5">
                    <p class="font-display text-[18px] font-normal leading-[1.35] tracking-[-0.01em] text-[#17143A]">
                        &ldquo;{{ $featured['quote'] }}&rdquo;
                    </p>
                    <footer class="mt-2.5 font-mono-ui text-[10.5px] uppercase tracking-[.08em] text-[#8A8FA0]">
                        {{ $featured['byline'] }}, {{ $featured['studio'] }}@if (CustomerStories::isIllustrative($featured)) &middot; illustrative @endif
                    </footer>
                </blockquote>

                <dl class="mt-8 space-y-3 border-t border-black/[.08] pt-6">
                    @foreach ($featured['setup'] as [$k, $v])
                        <div class="flex gap-4">
                            <dt class="w-[104px] shrink-0 font-mono-ui text-[10px] uppercase tracking-[.08em] text-[#A1A6B4]">{{ $k }}</dt>
                            <dd class="text-[13.5px] text-[#4A4F63]">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <x-marketing.window chrome="clarix.app/units">
                <div class="p-4">
                    <div class="flex items-center justify-between pb-3">
                        <span class="text-[12px] font-semibold">{{ $featured['studio'] }}</span>
                        <span class="rounded-md border border-black/[.07] px-2 py-0.5 font-mono-ui text-[9.5px] text-[#5B6076]">4 units</span>
                    </div>

                    <div class="space-y-2">
                        @foreach ($featured['units'] as $unit)
                            <div class="flex items-center gap-3 rounded-lg border border-black/[.06] px-3 py-2.5">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-600"></span>
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate text-[12px] font-medium">{{ $unit['name'] }}</span>
                                    <span class="font-mono-ui text-[9.5px] text-[#A1A6B4]">{{ $unit['people'] }} people</span>
                                </div>
                                <span class="shrink-0 rounded-full bg-[#EEF0FF] px-2 py-0.5 font-mono-ui text-[9.5px] font-medium text-indigo-700">
                                    {{ $unit['flight'] }} in flight
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3.5 flex items-center justify-between border-t border-black/[.06] pt-3.5">
                        <span class="font-mono-ui text-[10px] text-[#A1A6B4]">11 clients &middot; Pro plan</span>
                        <span class="font-mono-ui text-[10px] text-[#A1A6B4]">18 tasks open</span>
                    </div>
                </div>
            </x-marketing.window>
        </div>
    </x-marketing.section>

    {{-- =========================== More stories ======================== --}}
    <x-marketing.section>
        <x-marketing.section-head
            eyebrow="Three more shapes"
            heading="The same product, configured three ways."
            lede="A solo practice, an in-house group and a small content agency each use a different quarter of Clarix. None of them had to be talked out of the rest of it."
        />

        <div class="mt-14 grid gap-5 lg:grid-cols-3">
            @foreach ($stories as $story)
                <article class="flex flex-col rounded-[18px] border border-[#E9E4D8] bg-[#FBFAF6] p-7">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 class="font-display text-[19px] font-normal leading-[1.2] tracking-[-0.015em] text-[#17143A]">
                            {{ $story['studio'] }}
                        </h3>
                        <span class="shrink-0 rounded-full border border-[#DCD6C6] bg-white px-2.5 py-0.5 font-mono-ui text-[9.5px] text-[#5B6076]">{{ $story['plan'] }}</span>
                    </div>

                    <span class="mt-2 block font-mono-ui text-[10px] uppercase tracking-[.08em] text-[#A1A6B4]">{{ $story['shape'] }}</span>

                    <p class="mt-4 flex-1 text-[13.5px] leading-relaxed text-[#5B6076]">{{ $story['body'] }}</p>

                    <blockquote class="mt-6 border-t border-[#E2DCCC] pt-5">
                        <p class="text-[14px] font-medium leading-relaxed text-[#17143A]">&ldquo;{{ $story['quote'] }}&rdquo;</p>
                        <footer class="mt-2 font-mono-ui text-[9.5px] uppercase tracking-[.08em] text-[#8A8FA0]">
                            {{ $story['byline'] }}@if (CustomerStories::isIllustrative($story)) &middot; illustrative @endif
                        </footer>
                    </blockquote>
                </article>
            @endforeach
        </div>
    </x-marketing.section>

    {{-- ========================= Which one is you ====================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Find your shape"
            heading="Which of these is closest to you?"
            lede="Each one has a page that goes into how Clarix is set up for it, and what changes when you outgrow that shape."
        />

        <div class="mt-14 grid gap-px overflow-hidden rounded-2xl border border-black/[.07] bg-black/[.06] sm:grid-cols-3">
            @foreach ($shapes as $shape)
                <a href="{{ route($shape['route']) }}"
                   class="group bg-white p-7 transition hover:bg-[#FAFAFC] focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600">
                    <span class="flex items-center gap-1.5 text-[15px] font-semibold tracking-tight">
                        {{ $shape['label'] }}
                        <svg class="h-3.5 w-3.5 text-indigo-600 transition-transform group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                    </span>
                    <span class="mt-2 block text-[14px] leading-relaxed text-[#5B6076]">{{ $shape['copy'] }}</span>
                </a>
            @endforeach
        </div>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Be the first story worth telling."
        lede="Set Clarix up the way one of these shapes does it, and we will come and ask you how it went."
    />

</x-marketing.layout>
