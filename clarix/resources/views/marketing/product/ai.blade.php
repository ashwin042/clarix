@php
    $axokaiUrl = 'https://axokai.codesnextdoor.com/';

    // The AI surfaces that exist in the product today, named as they are named
    // in the app: Overview, Chatbot, Scheduled Tasks, MCP & Plugins, Calendar.
    $surfaces = [
        [
            'name' => 'Chatbot',
            'copy' => 'Ask about the board in plain language. It answers from your tasks, not from a general-purpose model guessing at your business.',
            'icon' => '<path d="M3.5 5.5A2 2 0 0 1 5.5 3.5h9a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H8l-4 3.5v-3.5H5.5a2 2 0 0 1-2-2v-6Z"/>',
        ],
        [
            'name' => 'Scheduled Tasks',
            'copy' => 'Recurring work that creates itself — the monthly report, the weekly social batch — briefed and costed the same way a manual task is.',
            'icon' => '<circle cx="10" cy="10" r="7.5"/><path d="M10 5.5V10l3 2"/>',
        ],
        [
            'name' => 'MCP & Plugins',
            'copy' => 'Connect the tools the work already lives in, and let automation reach them directly. Full access on the Pro plan.',
            'icon' => '<rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/><path d="M9 6h3.5a1.5 1.5 0 0 1 1.5 1.5V11"/>',
        ],
        [
            'name' => 'Calendar',
            'copy' => 'Deadlines, scheduled runs and delivery dates on one timeline, so a week that is already full looks full before you promise it.',
            'icon' => '<rect x="3" y="4.5" width="14" height="12" rx="2"/><path d="M3 8.5h14M7 3v3M13 3v3"/>',
        ],
    ];
@endphp

<x-marketing.layout
    title="AI Automation — Clarix"
    description="AXOKAI handles the busywork: it watches every brief, file and update as work moves, flags what has stalled, and keeps the ledger honest."
>

    {{-- ============================== Hero ============================= --}}
    {{-- The indigo band, borrowed from the homepage's AXOKAI section — this is
         that section's own page, so it keeps that section's surface. --}}
    <main class="relative overflow-hidden bg-[#4F46E5] px-6 pb-20 pt-14 sm:pb-24 sm:pt-20">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-0 -top-32 h-[560px] opacity-[.28]"
             style="background: radial-gradient(46% 50% at 50% 40%, #C7D2FE 0%, rgba(199,210,254,0) 70%);"></div>

        <div class="relative z-10 mx-auto max-w-6xl">

            <x-marketing.page-hero
                eyebrow="AI Automation"
                heading="Let AI handle the busywork."
                lede="AXOKAI sits inside the board with access to every brief, file and message tied to a task. It tracks what moved, flags what stalled, and keeps the credit ledger straight — so your team spends its hours on the work, not on reporting it."
                align="center"
                tone="light"
                primary="{{ $axokaiUrl }}"
                primary-label="Explore AXOKAI"
            />

            <div class="rise rise-4 mx-auto mt-14 max-w-[560px]">
                <x-marketing.window chrome="clarix: axokai" dark>
                    <div class="space-y-[7px] p-4 font-mono-ui text-[10.5px] leading-[1.55]">
                        <div><span class="text-[#4ADE80]">➜</span> <span class="text-[#8A90A6]">clarix</span> <span class="text-[#E4E6F0]">axokai</span></div>

                        <div class="pt-1 text-[#8A90A6]">&gt; what stalled this week?</div>

                        <div class="pt-1.5">
                            <span class="text-indigo-400">●</span>
                            <span class="text-[#E4E6F0]"> Scan</span>
                            <span class="text-[#8A90A6]">  18 open tasks, 3 units</span>
                        </div>

                        <div class="py-1 pl-3 text-[#A8AEC4]">
                            <span class="text-[#FCA5A5]">CLX-1037</span> has been
                            <span class="text-[#E4E6F0]">On hold</span> for 9 days — waiting on
                            Meridian legal since 25 Aug.
                        </div>
                        <div class="pb-1 pl-3 text-[#A8AEC4]">
                            <span class="text-[#FCD34D]">CLX-1028</span> sat in
                            <span class="text-[#E4E6F0]">Sent for review</span> past its due date.
                        </div>

                        <div>
                            <span class="text-indigo-400">●</span>
                            <span class="text-[#E4E6F0]"> Notify</span>
                            <span class="text-[#8A90A6]">  2 owners, 1 PM</span>
                        </div>

                        <div class="pt-2">
                            <span class="text-[#8A90A6]">&gt;</span>
                            <span class="t-type text-[#E4E6F0]">reconcile the ledger</span><span class="caret ml-px inline-block h-[13px] w-[7px] translate-y-[2px] bg-[#E4E6F0]"></span>
                        </div>

                        <div class="t-out1">
                            <span class="text-indigo-400">●</span>
                            <span class="text-[#E4E6F0]"> Ledger</span>
                            <span class="text-[#8A90A6]">  September, Northwind</span>
                        </div>

                        <div class="t-out2 pl-3">
                            <span class="rounded bg-[#14532D] px-1.5 py-[1px] text-[#86EFAC]">BALANCED</span>
                            <span class="text-[#8A90A6]"> 14 debits, 1,240 remaining</span>
                        </div>
                    </div>
                </x-marketing.window>
            </div>
        </div>
    </main>

    {{-- ========================= What it watches ======================= --}}
    <x-marketing.section>
        <x-marketing.section-head
            eyebrow="Context, not prompts"
            heading="It already knows what the task is."
            lede="A general assistant needs the whole story pasted in before it can help. AXOKAI is inside the record — the brief, the thread, the files and the ledger entry are what it reads from."
        />

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                'Has access to every brief, file, and message tied to a task',
                'Tracks credits and time as work actually moves',
                'Flags stalled tasks before a client has to ask',
                'Gets clearer with every project, so estimates improve over time',
            ] as $i => $text)
                <div class="rounded-2xl border border-black/[.07] bg-white p-6 shadow-[0_18px_34px_-24px_rgba(14,17,38,.34)]">
                    <span class="font-mono-ui text-[11px] text-[#A1A6B4]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <p class="mt-3 text-[13.5px] leading-relaxed text-[#4A4F63]">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </x-marketing.section>

    {{-- =========================== AI surfaces ========================= --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="In the product"
            heading="Four places automation shows up."
            lede="Not one chat box bolted to the side. Automation appears where the decision is being made."
        />

        <div class="mt-14 grid gap-px overflow-hidden rounded-2xl border border-black/[.07] bg-black/[.06] sm:grid-cols-2">
            @foreach ($surfaces as $surface)
                <div class="bg-white p-7">
                    <span class="flex h-9 w-9 items-center justify-center rounded-[10px] bg-[#EEF0FF]">
                        <svg class="h-[18px] w-[18px] text-indigo-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $surface['icon'] !!}</svg>
                    </span>
                    <h3 class="mt-4 text-[15px] font-semibold tracking-tight">{{ $surface['name'] }}</h3>
                    <p class="mt-2 text-[14px] leading-relaxed text-[#5B6076]">{{ $surface['copy'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-[13.5px] text-[#7A8092]">
            The AI Chatbot is included from the Standard plan; MCP &amp; Plugins with full
            automation access is a Pro feature.
            <a href="/home#pricing" class="font-medium text-indigo-600 underline-offset-2 hover:underline">Compare plans</a>
        </p>
    </x-marketing.section>

    {{-- ============================ Off to AXOKAI ====================== --}}
    <x-marketing.section width="max-w-4xl">
        <div class="rounded-3xl border border-black/[.07] bg-[#0F1222] px-8 py-12 text-center sm:px-14">
            <span class="block text-[12.5px] font-semibold uppercase tracking-[.08em] text-white/60">The engine</span>

            <h2 class="mt-4 font-display text-[30px] font-normal leading-[1.1] tracking-[-0.02em] text-white sm:text-[38px]">
                AXOKAI is a product of its own.
            </h2>

            <p class="mx-auto mt-5 max-w-xl text-[15px] leading-relaxed text-white/70">
                Clarix is where it runs against your client work. If you want the full picture of
                what the agent does and how it is built, it has its own site.
            </p>

            <a href="{{ $axokaiUrl }}" target="_blank" rel="noopener noreferrer"
               class="mt-8 inline-flex items-center gap-2 rounded-full border border-white/35 px-5 py-2.5 text-[14px] font-semibold text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                Learn more about AXOKAI
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
            </a>
        </div>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Give the busywork to the agent."
        lede="Briefs read, stalls flagged, credits reconciled — while your team does the work clients are actually paying for."
    />

</x-marketing.layout>
