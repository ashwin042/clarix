@php
    // The agencies diagram collapsed: the outer band is clients rather than
    // units, and the layer above them is one person instead of a management
    // tier. Same picture, one scale down — which is the honest description of
    // what a solo practice is.
    $clients = [
        ['label' => 'Northwind', 'initials' => 'NW'],
        ['label' => 'Aurora',    'initials' => 'AU'],
        ['label' => 'Meridian',  'initials' => 'MD'],
    ];

    $you = [
        ['label' => 'You', 'accent' => true, 'icon' => '<circle cx="10" cy="7" r="3"/><path d="M4.5 16.5a5.5 5.5 0 0 1 11 0"/>'],
    ];

    // A month, the way the ledger already has it: one line per client, the
    // count of settled tasks and what they cost.
    $month = [
        ['client' => 'Northwind', 'tasks' => 6, 'credits' => 148],
        ['client' => 'Aurora',    'tasks' => 4, 'credits' => 96],
        ['client' => 'Meridian',  'tasks' => 3, 'credits' => 74],
    ];
@endphp

<x-marketing.layout
    title="For Freelancers — Clarix"
    description="Simplify solo project tracking: one board for every client, a live view that replaces the status email, and a ledger that turns a month of work into an invoice."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14">

                <x-marketing.page-hero
                    eyebrow="For Freelancers"
                    heading="Simplify solo project tracking."
                    lede="Three clients, eleven live tasks, and nobody to hand the tracking to. Clarix gives one person the board, the client view and the ledger an agency runs on — without a process built for a team of twenty."
                />

                <div class="rise rise-4">
                    <x-marketing.org-diagram
                        :outer="$clients"
                        :mid="$you"
                        outer-label="Clients"
                        mid-label="The practice"
                        core="Your studio"
                        sub="One board, one ledger, one invoice"
                    />
                </div>
            </div>
        </div>
    </main>

    {{-- ========================= What it replaces ====================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Solo, not informal"
            heading="Three habits you can stop keeping."
            lede="Working alone does not mean the admin disappears — it means there is nobody else to do it. Each of these is a job the board already does."
        />

        <div class="mt-14 grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['The Friday status email', 'Clients open the task and see the column it is sitting in, right now. Nothing to write from memory at six o\'clock on a Friday.'],
                ['The final-v3-FINAL folder', 'Briefs, drafts and deliverables attach to the task that asked for them and version in place, so the newest is always marked.'],
                ['The month-end reconstruction', 'Every completed task carries the credit cost it was briefed at. Invoicing is reading a report, not an archaeology exercise.'],
            ] as [$label, $copy])
                <div class="rounded-2xl border border-black/[.07] bg-white p-7 shadow-[0_18px_34px_-24px_rgba(14,17,38,.34)]">
                    <h3 class="text-[15px] font-semibold tracking-tight">{{ $label }}</h3>
                    <p class="mt-2.5 text-[14px] leading-relaxed text-[#5B6076]">{{ $copy }}</p>
                </div>
            @endforeach
        </div>
    </x-marketing.section>

    {{-- =========================== One board ========================== --}}
    <x-marketing.section>
        <x-marketing.split reverse>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="One board is enough"
                    heading="Every client, in one place you actually check."
                    lede="A board per client is a board you stop opening. Clarix keeps all of them on one screen and lets you narrow it when you want to, which is the version that survives a busy week."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Six statuses, no setup', 'Pending, On hold, In progress, Sent for review, Completed and Cancelled. Nothing to design before the first task goes in.'],
                        ['On hold earns its keep', 'The column for work blocked on a client answer. Held work stops counting against the week you are judging yourself on.'],
                        ['It scales when you do', 'Every plan carries room for more than one unit, so the first person you bring on is a new unit rather than a migration to a different product.'],
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

                <a href="{{ route('marketing.product.boards') }}"
                   class="mt-7 inline-flex items-center gap-1.5 text-[14px] font-semibold text-indigo-600 transition hover:text-indigo-700">
                    How the board works
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </a>
            </x-slot:text>

            <x-slot:visual>
                {{-- The month as the ledger already holds it: settled tasks per
                     client, which is the invoice before it is an invoice. --}}
                <x-marketing.window chrome="clarix.app/credits">
                    <div class="p-4">
                        <div class="flex items-center justify-between pb-3">
                            <span class="text-[12px] font-semibold">Settled in September</span>
                            <span class="rounded-md border border-black/[.07] px-2 py-0.5 font-mono-ui text-[9.5px] text-[#5B6076]">13 tasks</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($month as $row)
                                <div class="flex items-center gap-3 rounded-lg border border-black/[.06] px-3 py-2.5">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF0FF] font-mono-ui text-[9px] font-medium text-indigo-700">
                                        {{ strtoupper(substr($row['client'], 0, 2)) }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-medium">{{ $row['client'] }}</span>
                                        <span class="font-mono-ui text-[9.5px] text-[#A1A6B4]">{{ $row['tasks'] }} tasks completed</span>
                                    </div>
                                    <span class="shrink-0 font-mono-ui text-[12px] font-medium text-[#0F1222]">{{ $row['credits'] }}c</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3.5 flex items-center justify-between border-t border-black/[.06] pt-3.5">
                            <span class="font-mono-ui text-[10.5px] text-[#A1A6B4]">318 credits settled</span>
                            <span class="rounded-md bg-indigo-600 px-2.5 py-1 text-[10px] font-medium text-white">Export September</span>
                        </div>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>

        <p class="mt-14 text-center text-[13.5px] text-[#7A8092]">
            Base starts at Rs&nbsp;1,250 a month with 5&nbsp;GB of storage and room for five teams — the
            plan most solo practices start on.
            <a href="/home#pricing" class="font-medium text-indigo-600 underline-offset-2 hover:underline">Compare plans</a>
        </p>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Everything an agency runs on, sized for one."
        lede="One board, one client view, one ledger — and no standing meeting needed to keep any of them true."
    />

</x-marketing.layout>
