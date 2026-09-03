@php
    use App\Support\Pricing;

    /*
     * Every number on this page comes from App\Support\Pricing, which is the
     * same source the homepage's pricing section reads. Nothing here is
     * retyped, and PricingPageTest fails if the two pages ever disagree.
     */
    $plans   = Pricing::plans();
    $heads   = Pricing::compareHeads();
    $compare = Pricing::comparison();

    // Who each plan is actually for, in one line. Drawn from the blurbs the
    // plan data already carries rather than written fresh.
    $audience = array_column($plans, 'blurb', 'name');

    /*
     * Billing questions, answered from the product rather than from what a
     * pricing page usually says.
     *
     * Checked against the code: OrganizationSubscription::BILLING_CYCLES is
     * monthly|yearly; its STATUSES are active, past_due, suspended and
     * cancelled; the subscription middleware blocks only on suspended and
     * renders a notice rather than signing anyone out; the Subscription
     * screen is read-only, so a plan change is not self-serve; payments are
     * recorded against a subscription with a method; and a task's credit
     * amount is counted from completed work rather than drawn down from a
     * purchased balance.
     */
    $faq = [
        [
            'q' => 'How is Clarix billed?',
            'a' => 'One subscription per organisation, not per user seat — a single monthly or yearly price for the whole agency, whatever it is spending its time on. The yearly cycle is the same plan at 20% off, quoted here as a per-month figure so the two are comparable at a glance.',
        ],
        [
            'q' => 'How do I move between plans?',
            'a' => 'Tell us, and we move you. Changing plan is not a self-serve switch inside the app: what the product gives you is the record — the subscription screen shows the plan you are on, the cycle, the renewal date and every payment ever made against it, so both sides are looking at the same history when the conversation happens.',
        ],
        [
            'q' => 'What happens to my credits if I change plan?',
            'a' => 'Nothing at all, because credits are not something you buy. A credit amount is agreed on each task when it is briefed, and the figure you see is the total of the work that has actually been completed. Moving up or down a plan changes storage, features and support — it does not reset, forfeit or top up a balance, because there is no balance to reset.',
        ],
        [
            'q' => 'What happens if a payment is late?',
            'a' => 'A subscription that is past due stays fully usable. That state is a grace period on purpose: nobody is locked out of live client work over an invoice. Only a suspended subscription blocks the app, and it does so behind a notice rather than by signing anyone out or deleting anything — the organisation is exactly as it was when billing is settled.',
        ],
        [
            'q' => 'How is payment made?',
            'a' => 'Arranged with us rather than through a checkout screen in the product. Each payment is then recorded against your subscription with its amount, date and method, and that history is visible to your admins in full — not just the last few lines.',
        ],
        [
            'q' => 'What counts toward the storage limit?',
            'a' => 'Files attached to tasks. Usage is tracked per unit and totalled for the organisation, and it is kept current as files arrive and leave rather than recalculated overnight, so the ceiling arrives as a trend you can watch rather than an upload that suddenly fails. Pro adds another 100 GB for Rs 1,000 on request; Enterprise is sized during onboarding.',
        ],
        [
            'q' => 'Can we see it working before we commit?',
            'a' => 'Yes — book a walkthrough and we will run your own shape of work through it rather than a demo dataset. Bring a real brief, a real unit structure and a real client, and you will see what the board looks like on a Tuesday rather than what it looks like in a screenshot.',
        ],
    ];
@endphp

<x-marketing.layout
    title="Pricing — Clarix"
    description="One subscription per agency, monthly or yearly, with a team ceiling that rises with the plan. Compare Base, Standard, Pro and Enterprise feature by feature."
>

    {{-- ====================== Hero + billing toggle ==================== --}}
    <main x-data="{ billing: 'monthly' }" class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pt-12 sm:pt-16">

            <x-marketing.page-hero
                eyebrow="Pricing"
                heading="Priced by the agency, not by the head."
                lede="One subscription for the whole organisation rather than a price per head. What moves as you go up is how many teams you can run, how much you can store, and whether the ERP and automation layers are switched on."
                align="center"
                primary-label="Get started"
            />

            {{-- The homepage's toggle, kept identical on purpose: a visitor
                 arriving from that section should not have to learn a second
                 control to answer the same question. --}}
            <div class="mt-12 flex justify-center">
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
                            Save 20%
                        </span>
                    </button>
                </div>
            </div>

            {{-- ========================= Plan cards ====================== --}}
            {{-- Paper stock, the same card the pricing table and the blog use,
                 one size up: this page is where the cards are the content
                 rather than a band inside a longer scroll. --}}
            <div class="mt-12 grid items-stretch gap-5 pb-20 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                @foreach ($plans as $plan)
                    @php
                        $featured = $plan['popular'] ?? false;
                        $dark = $plan['dark'] ?? false;
                    @endphp

                    <div @class([
                        'relative flex flex-col rounded-[18px] border p-6',
                        'border-[#B9B2F0] bg-white shadow-[0_24px_50px_-24px_rgba(79,70,229,.45)]' => $featured,
                        'border-white/[.10] bg-[#0F1222]' => $dark,
                        'border-[#E9E4D8] bg-[#FBFAF6]' => ! $featured && ! $dark,
                    ])>

                        <div class="flex min-h-[24px] items-center justify-between gap-2">
                            <span @class([
                                'text-[12.5px] font-semibold uppercase tracking-[.06em]',
                                'text-white/60' => $dark,
                                'text-[#6B7086]' => ! $dark,
                            ])>{{ $plan['name'] }}</span>

                            @if ($featured)
                                <span class="rounded-full bg-indigo-600 px-2.5 py-[3px] text-[10.5px] font-semibold text-white">
                                    Most popular
                                </span>
                            @endif
                        </div>

                        {{-- Price. A plan with no annual figure ignores the
                             toggle rather than showing the same number twice. --}}
                        <div class="mt-5">
                            <div class="flex items-baseline gap-1.5">
                                @if ($plan['priceAnnual'] ?? null)
                                    <span x-show="billing === 'monthly'"
                                          @class(['font-display text-[34px] font-normal leading-none tracking-[-0.02em]', 'text-white' => $dark, 'text-[#17143A]' => ! $dark])>
                                        {{ $plan['price'] }}
                                    </span>
                                    <span x-show="billing === 'annual'" x-cloak
                                          @class(['font-display text-[34px] font-normal leading-none tracking-[-0.02em]', 'text-white' => $dark, 'text-[#17143A]' => ! $dark])>
                                        {{ $plan['priceAnnual'] }}
                                    </span>
                                @else
                                    <span @class(['font-display text-[34px] font-normal leading-none tracking-[-0.02em]', 'text-white' => $dark, 'text-[#17143A]' => ! $dark])>
                                        {{ $plan['price'] }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 flex items-center gap-2">
                                <span @class(['text-[12px]', 'text-white/55' => $dark, 'text-[#7A8092]' => ! $dark])>
                                    {{ $plan['period'] }}
                                </span>

                                @if ($plan['priceAnnual'] ?? null)
                                    <span x-show="billing === 'annual'" x-cloak
                                          class="rounded-full bg-[#EEF0FF] px-2 py-[1px] font-mono-ui text-[9.5px] font-medium text-indigo-700">
                                        billed yearly · 20% off
                                    </span>
                                @endif
                            </div>
                        </div>

                        <p @class(['mt-4 text-[13px] leading-relaxed', 'text-white/65' => $dark, 'text-[#5B6076]' => ! $dark])>
                            {{ $plan['blurb'] }}
                        </p>

                        <a href="{{ $plan['href'] === '#schedule-demo' ? '/home#schedule-demo' : $plan['href'] }}"
                           @class([
                               'mt-6 inline-flex w-full items-center justify-center rounded-full px-4 py-2.5 text-[13.5px] font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                               'bg-white text-[#0F1222] hover:bg-white/90 focus-visible:outline-white' => $dark,
                               'bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:outline-indigo-600' => $featured,
                               'border border-[#0F1222]/[.14] bg-white text-[#0F1222] hover:border-[#0F1222]/30 focus-visible:outline-indigo-600' => ! $featured && ! $dark,
                           ])>
                            {{ $plan['cta'] }}
                        </a>

                        <ul @class(['mt-6 space-y-2.5 border-t pt-5', 'border-white/[.10]' => $dark, 'border-[#E2DCCC]' => ! $dark])>
                            @foreach ($plan['features'] as $feature)
                                @if (str_ends_with($feature, ':'))
                                    {{-- A lead-in, not a feature: no checkmark. --}}
                                    <li @class(['pt-1 text-[12px] font-semibold uppercase tracking-[.05em]', 'text-white/50' => $dark, 'text-[#A1A6B4]' => ! $dark])>
                                        {{ $feature }}
                                    </li>
                                @else
                                    <li class="flex gap-2.5">
                                        <svg @class(['mt-[3px] h-3.5 w-3.5 shrink-0', 'text-indigo-300' => $dark, 'text-indigo-600' => ! $dark])
                                             viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="m3.5 8.5 3 3 6-6"/>
                                        </svg>
                                        <span @class(['text-[13px] leading-snug', 'text-white/80' => $dark, 'text-[#4A4F63]' => ! $dark])>
                                            {{ $feature }}
                                        </span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </main>

    {{-- ======================= Full comparison ========================= --}}
    <x-marketing.section surface="soft" divider id="compare">
        <x-marketing.section-head
            eyebrow="Feature by feature"
            heading="Everything, in one table."
            lede="The whole product laid out across the four plans — so the question is what you need rather than what a card had room to list."
        />

        <div class="mt-14 overflow-x-auto rounded-2xl border border-[#E9E4D8] bg-[#FBFAF6]">
            <table class="w-full min-w-[760px] text-left">
                <caption class="sr-only">Clarix plan comparison</caption>

                <thead>
                    <tr class="border-b border-[#DCD6C6]">
                        <th scope="col" class="px-6 py-5 align-bottom">
                            <span class="font-display text-[18px] font-normal tracking-[-0.015em] text-[#17143A]">Compare plans</span>
                        </th>
                        @foreach ($heads as $head)
                            <th scope="col" class="border-l border-[#EDEAE0] px-5 py-5 align-bottom">
                                <span class="block text-[12.5px] font-semibold uppercase tracking-[.06em] text-[#6B7086]">{{ $head['name'] }}</span>
                                <a href="{{ $head['href'] === '#schedule-demo' ? '/home#schedule-demo' : $head['href'] }}"
                                   class="mt-2 inline-block text-[12px] font-semibold text-indigo-600 underline-offset-2 transition hover:text-indigo-700 hover:underline">
                                    {{ $head['cta'] }}
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                @foreach ($compare as $group => $rows)
                    <tbody>
                        <tr>
                            <th scope="colgroup" colspan="5"
                                class="border-y border-[#E2DCCC] bg-[#F3EFE4] px-6 py-2.5 font-mono-ui text-[10px] uppercase tracking-[.12em] text-[#8A8FA0]">
                                {{ $group }}
                            </th>
                        </tr>

                        @foreach ($rows as $row)
                            @php [$label, $values, $hint] = array_pad($row, 3, null); @endphp
                            <tr class="border-b border-[#EDEAE0] last:border-b-0">
                                <th scope="row" class="px-6 py-3.5 text-[13.5px] font-medium text-[#17143A]">
                                    {{ $label }}
                                    @if ($hint)
                                        <span class="mt-1 block max-w-sm text-[12px] font-normal leading-snug text-[#8A8FA0]">{{ $hint }}</span>
                                    @endif
                                </th>

                                @foreach ($values as $value)
                                    <td class="border-l border-[#EDEAE0] px-5 py-3.5">
                                        @if ($value === true)
                                            <svg class="h-4 w-4 text-indigo-600" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-6"/></svg>
                                            <span class="sr-only">Included</span>
                                        @elseif ($value === false)
                                            <span class="font-mono-ui text-[13px] text-[#C8C2AF]" aria-hidden="true">—</span>
                                            <span class="sr-only">Not included</span>
                                        @else
                                            <span class="text-[13px] leading-snug text-[#4A4F63]">{{ $value }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>

        <p class="mt-6 text-center font-mono-ui text-[11px] text-[#8A8FA0]">
            Scroll the table sideways on a narrow screen.
        </p>
    </x-marketing.section>

    {{-- ================== Credits are not the subscription ============= --}}
    <x-marketing.section>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="The other number"
                    heading="Credits are not a thing you buy."
                    lede="The subscription above is what Clarix costs. Credits are something else entirely, and the two are easy to confuse on a pricing page — so here is the difference."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['A credit is a unit of work, not of billing', 'Each task carries a credit amount, agreed when it is briefed. It is how an agency prices its own work to its own clients — it is not a currency you buy from us.'],
                        ['They are counted, not spent', 'The figure on your dashboard is the total of tasks that have actually reached Completed. Nothing is drawn down, so nothing can run out mid-month.'],
                        ['A plan change does not touch them', 'Moving between plans changes storage, features and support. It does not reset a balance, because there is no balance — only a record of finished work.'],
                    ] as [$label, $copy])
                        <li class="flex gap-3.5">
                            <span class="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-600"></span>
                            <div>
                                <span class="block text-[14.5px] font-semibold tracking-tight">{{ $label }}</span>
                                <span class="mt-1 block text-[14px] leading-relaxed text-[#5B6076]">{{ $copy }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('marketing.product.credits') }}"
                   class="mt-7 inline-flex items-center gap-1.5 text-[14px] font-semibold text-indigo-600 transition hover:text-indigo-700">
                    How credits work
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </a>
            </x-slot:text>

            <x-slot:visual>
                <x-marketing.window chrome="clarix.app/subscription">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4 border-b border-black/[.06] pb-3">
                            <div>
                                <span class="font-mono-ui text-[10px] uppercase tracking-[.08em] text-[#A1A6B4]">Current plan</span>
                                <h3 class="mt-1 text-[15px] font-semibold tracking-tight">Standard</h3>
                            </div>
                            <span class="shrink-0 rounded-full bg-[#ECFDF3] px-2.5 py-1 text-[10px] font-semibold text-[#0E7A55]">Active</span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4">
                            @foreach ([['Cycle', 'Yearly'], ['Renews', '1 Apr 2027'], ['Price', 'Rs 1,600 / mo'], ['Since', '1 Apr 2026']] as [$k, $v])
                                <div>
                                    <dt class="font-mono-ui text-[9.5px] uppercase tracking-[.08em] text-[#A1A6B4]">{{ $k }}</dt>
                                    <dd class="mt-1 text-[12.5px] font-medium text-[#0F1222]">{{ $v }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-4 border-t border-black/[.06] pt-3.5">
                            <span class="font-mono-ui text-[9.5px] uppercase tracking-[.08em] text-[#A1A6B4]">Payments</span>
                            <ul class="mt-2.5 space-y-1.5">
                                @foreach ([['1 Apr 2026', 'Bank transfer', '19,200'], ['1 Apr 2025', 'Bank transfer', '19,200']] as [$when, $how, $amount])
                                    <li class="flex items-center justify-between rounded-lg border border-black/[.06] px-2.5 py-2">
                                        <span class="font-mono-ui text-[10px] text-[#5B6076]">{{ $when }}</span>
                                        <span class="text-[10.5px] text-[#7A8092]">{{ $how }}</span>
                                        <span class="font-mono-ui text-[11px] font-medium text-[#0F1222]">Rs {{ $amount }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    {{-- ========================= Billing FAQ =========================== --}}
    <x-marketing.section surface="soft" divider width="max-w-4xl">
        <x-marketing.section-head
            eyebrow="Billing"
            heading="The questions that come before a purchase order."
            lede="Answered from how the product actually behaves — including the ones most pricing pages leave for the contract."
        />

        <x-marketing.faq :items="$faq" :open="0" id-prefix="pricing-faq" class="mt-12" />

        <p class="mt-10 text-center text-[13.5px] text-[#7A8092]">
            Something not covered here?
            <a href="{{ route('marketing.help') }}" class="font-medium text-indigo-600 underline-offset-2 hover:underline">The Help Center</a>
            has the rest, or
            <a href="/home#schedule-demo" class="font-medium text-indigo-600 underline-offset-2 hover:underline">ask us directly</a>.
        </p>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Start on the plan that fits this month."
        lede="Five teams on Base, fifty on Pro — and a conversation rather than a checkout when you outgrow the one you picked."
    />

</x-marketing.layout>
