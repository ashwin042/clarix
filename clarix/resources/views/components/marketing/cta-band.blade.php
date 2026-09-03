@props([
    'heading' => 'Run your next client project in Clarix.',
    'lede' => 'Brief a task, watch it move, and let the ledger keep the score. Set up takes an afternoon.',
])

{{--
    The closing block, identical on every page so the site ends the same way
    each time. It reuses the footer's own diagonal indigo rather than a flat
    fill, which is what lets it sit directly above the footer without the two
    reading as two separate slabs of the same colour.
--}}
<section class="site-footer relative overflow-hidden px-6 py-20 text-white sm:py-24">
    <div class="mx-auto max-w-3xl text-center">

        <h2 class="font-display text-[32px] font-normal leading-[1.08] tracking-[-0.02em] text-white sm:text-[44px]">
            {{ $heading }}
        </h2>

        <p class="mx-auto mt-5 max-w-xl text-[15px] leading-relaxed text-white/75 sm:text-base">
            {{ $lede }}
        </p>

        <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <a href="{{ route('login') }}"
               class="inline-flex w-full items-center justify-center rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-[#0F1222] transition hover:bg-white/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto">
                Get started
            </a>
            <a href="/home#schedule-demo"
               class="inline-flex w-full items-center justify-center rounded-full border border-white/35 px-6 py-3 text-[15px] font-semibold text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto">
                Book a demo
            </a>
        </div>
    </div>
</section>
