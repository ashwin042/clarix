@php
    // The six statuses are Task::STATUSES, in the order work moves through
    // them. Cancelled is the one that is not a step forward, which is why it
    // sits apart from the rail below rather than at the end of it.
    $columns = [
        ['name' => 'Pending',         'tint' => 'bg-[#F1F2F6] text-[#5B6076]', 'dot' => 'bg-[#C8CCD6]', 'count' => 5],
        ['name' => 'On hold',         'tint' => 'bg-[#FFF4E8] text-[#B45309]', 'dot' => 'bg-[#FFB27A]', 'count' => 2],
        ['name' => 'In progress',     'tint' => 'bg-[#EEF0FF] text-indigo-700', 'dot' => 'bg-indigo-600', 'count' => 4],
        ['name' => 'Sent for review', 'tint' => 'bg-[#EEF6FF] text-[#1D6FB8]', 'dot' => 'bg-[#5AA9E6]', 'count' => 3],
        ['name' => 'Completed',       'tint' => 'bg-[#ECFDF3] text-[#0E7A55]', 'dot' => 'bg-emerald-500', 'count' => 4],
    ];

    $cards = [
        'Pending' => [
            ['ref' => 'CLX-1042', 'title' => 'Northwind — Q3 landing page copy', 'unit' => 'Content', 'credits' => 18],
            ['ref' => 'CLX-1041', 'title' => 'Aurora — brand guideline refresh', 'unit' => 'Design', 'credits' => 32],
        ],
        'On hold' => [
            ['ref' => 'CLX-1037', 'title' => 'Meridian — waiting on legal sign-off', 'unit' => 'Content', 'credits' => 12],
        ],
        'In progress' => [
            ['ref' => 'CLX-1036', 'title' => 'Northwind — pricing page rewrite', 'unit' => 'Content', 'credits' => 24],
            ['ref' => 'CLX-1033', 'title' => 'Aurora — launch email sequence', 'unit' => 'Content', 'credits' => 20],
        ],
        'Sent for review' => [
            ['ref' => 'CLX-1028', 'title' => 'Meridian — case study layout', 'unit' => 'Design', 'credits' => 28],
        ],
        'Completed' => [
            ['ref' => 'CLX-1019', 'title' => 'Northwind — social kit, August', 'unit' => 'Design', 'credits' => 14],
        ],
    ];

    $rail = [
        ['status' => 'Pending',         'copy' => 'Briefed and costed, waiting for someone to pick it up. Nothing is in this column by accident — a task lands here the moment it is created.'],
        ['status' => 'On hold',         'copy' => 'Blocked on something outside the team: a client answer, a legal review, an asset that has not arrived. Held work stops counting against throughput.'],
        ['status' => 'In progress',     'copy' => 'Assigned and moving. The assignee owns it, and the activity log records every change from here on.'],
        ['status' => 'Sent for review', 'copy' => 'Done, but not signed off. Review is its own column because "nearly finished" is where agency work quietly stalls.'],
        ['status' => 'Completed',       'copy' => 'Signed off and settled. The credit amount is debited once, at the moment the task lands here.'],
    ];
@endphp

<x-marketing.layout
    title="Task Boards — Clarix"
    description="Plan and track agency work in one place. Six real statuses, filters by unit and client, and a credit cost attached to every task on the board."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:gap-14">

                <x-marketing.page-hero
                    eyebrow="Task Boards"
                    heading="Plan and track work in one place."
                    lede="Every client task on one board, in the column that says what is actually happening to it. No status meeting required to find out."
                />

                {{-- The homepage's board, opened up: all six statuses instead of
                     the four that fit in a floating hero window. --}}
                {{-- No overflow-hidden on this wrapper: the window's shadow
                     reaches past its box, and clipping it flattens the card.
                     The columns do their own scrolling further in. --}}
                <div class="rise rise-4">
                    <x-marketing.window chrome="clarix.app/tasks" class="mx-auto max-w-none">

                        <div class="flex items-center justify-between border-b border-black/[.05] px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="text-[13px] font-semibold">Tasks</span>
                                <span class="rounded-full bg-black/[.05] px-1.5 py-0.5 font-mono-ui text-[9px] text-[#5B6076]">18</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="flex items-center gap-1 rounded-md border border-black/[.07] bg-white px-2 py-1 text-[10px] text-[#5B6076]">
                                    All units
                                    <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 4.5 3 3 3-3"/></svg>
                                </span>
                                <span class="hidden rounded-md border border-black/[.07] px-2 py-1 text-[10px] text-[#5B6076] sm:inline">This week</span>
                                <span class="rounded-md bg-indigo-600 px-2.5 py-1 text-[10px] font-medium text-white">New task</span>
                            </div>
                        </div>

                        <div class="kanban-scroll overflow-x-auto">
                            <div class="flex min-w-[760px] gap-3 p-3">
                                @foreach ($columns as $col)
                                    <div class="w-[148px] shrink-0">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[9.5px] font-semibold {{ $col['tint'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $col['dot'] }}"></span>
                                                {{ $col['name'] }}
                                            </span>
                                            <span class="font-mono-ui text-[9px] text-[#A1A6B4]">{{ $col['count'] }}</span>
                                        </div>

                                        <div class="space-y-2">
                                            @foreach ($cards[$col['name']] as $card)
                                                <div class="rounded-lg border border-black/[.06] bg-white p-2.5 shadow-[0_2px_6px_-2px_rgba(14,17,38,.08)]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-mono-ui text-[8.5px] text-[#A1A6B4]">{{ $card['ref'] }}</span>
                                                        <span class="font-mono-ui text-[8.5px] text-[#5B6076]">{{ $card['credits'] }}c</span>
                                                    </div>
                                                    <p class="clamp-2 mt-1 text-[10.5px] font-medium leading-snug">{{ $card['title'] }}</p>
                                                    <div class="mt-2 flex items-center justify-between">
                                                        <span class="rounded bg-black/[.04] px-1.5 py-0.5 text-[8.5px] text-[#5B6076]">{{ $card['unit'] }}</span>
                                                        <span class="h-4 w-4 rounded-full bg-[#E4E6F0]"></span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-marketing.window>
                </div>
            </div>
        </div>
    </main>

    {{-- ======================= The six statuses ======================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Statuses"
            heading="Five columns forward, and one that isn't."
            lede="Clarix ships with the statuses agency work actually passes through, not a blank board you have to design first."
        />

        <div class="mt-14 space-y-px overflow-hidden rounded-2xl border border-black/[.07] bg-black/[.06]">
            @foreach ($rail as $i => $step)
                @php $col = collect($columns)->firstWhere('name', $step['status']); @endphp
                <div class="grid gap-3 bg-white px-6 py-5 sm:grid-cols-[200px_1fr] sm:items-baseline sm:gap-8">
                    <div class="flex items-center gap-2.5">
                        <span class="font-mono-ui text-[11px] text-[#A1A6B4]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-semibold {{ $col['tint'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $col['dot'] }}"></span>
                            {{ $step['status'] }}
                        </span>
                    </div>
                    <p class="text-[14px] leading-relaxed text-[#4A4F63]">{{ $step['copy'] }}</p>
                </div>
            @endforeach

            <div class="grid gap-3 bg-[#FAFAFC] px-6 py-5 sm:grid-cols-[200px_1fr] sm:items-baseline sm:gap-8">
                <div class="flex items-center gap-2.5">
                    <span class="font-mono-ui text-[11px] text-[#C8CCD6]">—</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-black/[.05] px-2.5 py-1 text-[12px] font-semibold text-[#7A8092]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#C8CCD6]"></span>
                        Cancelled
                    </span>
                </div>
                <p class="text-[14px] leading-relaxed text-[#7A8092]">
                    Off the board, kept in the record. Cancelled work still shows in the task's
                    history and never silently disappears from a client's view.
                </p>
            </div>
        </div>
    </x-marketing.section>

    {{-- ==================== Filters / organisation ===================== --}}
    <x-marketing.section>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="One board, many teams"
                    heading="Filter the board the way the agency is organised."
                    lede="Work is grouped into units, and units sit inside your organisation. Filter to one unit for a stand-up, or leave it open to see everything in flight."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['By unit', 'Content, Design, Development — whatever your units are called. A supervisor sees their unit; an admin sees all of them.'],
                        ['By assignee', 'Every task has an owner from the moment it leaves Pending, so "who has this?" is never a question.'],
                        ['By window', 'This week, this month, or a custom range, matched to how you actually bill.'],
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
            </x-slot:text>

            <x-slot:visual>
                {{-- The task detail behind a card: the same object the board
                     moves around, opened up. --}}
                <x-marketing.window chrome="clarix.app/tasks/1036">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span class="font-mono-ui text-[10px] text-[#A1A6B4]">CLX-1036</span>
                                <h3 class="mt-1 text-[15px] font-semibold tracking-tight">Northwind — pricing page rewrite</h3>
                            </div>
                            <span class="shrink-0 rounded-full bg-[#EEF0FF] px-2.5 py-1 text-[10px] font-semibold text-indigo-700">In progress</span>
                        </div>

                        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 border-y border-black/[.06] py-4">
                            @foreach ([
                                ['Unit', 'Content'],
                                ['Assignee', 'Writer, Content'],
                                ['Credits', '24'],
                                ['Due', 'Fri, 12 Sep'],
                            ] as [$k, $v])
                                <div>
                                    <dt class="text-[10px] font-medium uppercase tracking-[.08em] text-[#A1A6B4]">{{ $k }}</dt>
                                    <dd class="mt-1 text-[12.5px] font-medium text-[#0F1222]">{{ $v }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-4">
                            <span class="text-[10px] font-medium uppercase tracking-[.08em] text-[#A1A6B4]">Activity</span>
                            <ul class="mt-3 space-y-2.5">
                                @foreach ([
                                    ['bg-indigo-600', 'Moved to In progress', '2h ago'],
                                    ['bg-[#C8CCD6]', 'Brief attached — northwind-pricing.pdf', 'yesterday'],
                                    ['bg-[#C8CCD6]', 'Created from a client brief', '2 days ago'],
                                ] as [$dot, $text, $when])
                                    <li class="flex items-start gap-2.5">
                                        <span class="mt-[6px] h-1.5 w-1.5 shrink-0 rounded-full {{ $dot }}"></span>
                                        <span class="flex-1 text-[12px] leading-snug text-[#4A4F63]">{{ $text }}</span>
                                        <span class="shrink-0 font-mono-ui text-[10px] text-[#A1A6B4]">{{ $when }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Put the board where the work already is."
        lede="Every task, its unit, its owner and its cost — on one screen your team and your clients can both read."
    />

</x-marketing.layout>
