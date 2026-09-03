@php
    // What the client-facing view shows, against what stays with the team. The
    // split is the honest version of the pitch: the portal is a filtered view
    // of the same task, not a separate product with its own feature set.
    $visible = [
        'Which column every task is sitting in, right now',
        'Comments addressed to them, and their own replies',
        'Files marked as deliverables, at the latest version',
        'What has shipped this month, and what is queued behind it',
    ];

    $internal = [
        'Credit cost and the ledger behind it',
        'Who the task is assigned to, and their workload',
        'Internal notes, and review comments between the team',
        'Every other client on the board',
    ];
@endphp

<x-marketing.layout
    title="Client Portal — Clarix"
    description="Give clients a live view of delivery: the status of every task, the comments that concern them, and the files they are waiting on."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">

                <x-marketing.page-hero
                    eyebrow="Client Portal"
                    heading="Give clients a live view of delivery."
                    lede="Stop writing the status email. Clients open the same task you are working in and see where it stands, what changed, and what is waiting on them."
                />

                <div class="rise rise-4">
                    <x-marketing.window chrome="clarix.app/tasks/1028">
                        <div class="space-y-3.5 p-4">

                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-semibold">Meridian — case study layout</span>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-medium text-amber-700 ring-1 ring-amber-200">Sent for review</span>
                            </div>

                            <div class="flex gap-2.5">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#7C3AED] text-[8px] font-semibold text-white">PR</span>
                                <div class="min-w-0">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-[11px] font-semibold">Priya R.</span>
                                        <span class="text-[9px] text-[#A1A6B4]">Project manager · 3h</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] leading-relaxed text-[#4A4F63]">
                                        Layout is ready for your review — v3 is attached below.
                                    </p>
                                    <div class="mt-1.5 flex items-center gap-2 rounded-lg border border-black/[.07] bg-[#FAFAFC] px-2 py-1.5">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-indigo-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 4a2 2 0 0 1 2-2h5l5 5v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm7 0v3h3l-3-3Z"/></svg>
                                        <span class="min-w-0 flex-1 truncate text-[10px] font-medium">meridian-case-study-v3.pdf</span>
                                        <span class="font-mono-ui text-[9px] text-[#A1A6B4]">2.4 MB</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2.5">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0E7A55] text-[8px] font-semibold text-white">SM</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-[11px] font-semibold">Sara M.</span>
                                        <span class="text-[9px] text-[#A1A6B4]">Client · just now</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] leading-relaxed text-[#4A4F63]">
                                        Approved. Ship it.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 rounded-lg border border-black/[.07] bg-[#FAFAFC] px-2.5 py-2">
                                <span class="h-5 w-5 shrink-0 rounded-full bg-[#0E7A55]"></span>
                                <span class="flex-1 text-[10.5px] text-[#A1A6B4]">Write a reply…</span>
                                <span class="rounded-md bg-indigo-600 px-2 py-1 text-[9.5px] font-medium text-white">Send</span>
                            </div>
                        </div>
                    </x-marketing.window>
                </div>
            </div>
        </div>
    </main>

    {{-- ================== What they see / what they don't ============== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="One task, two views"
            heading="A filtered view, not a second system."
            lede="The portal is the same task record your team works in, with the internal half withheld. Nothing has to be copied across, so nothing can go out of date."
        />

        <div class="mt-14 grid gap-6 lg:grid-cols-2">

            <div class="rounded-2xl border border-black/[.07] bg-white p-7 shadow-[0_18px_34px_-24px_rgba(14,17,38,.34)]">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-[#ECFDF3]">
                        <svg class="h-4 w-4 text-[#0E7A55]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 10s2.7-5 8-5 8 5 8 5-2.7 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2"/></svg>
                    </span>
                    <h3 class="text-[15px] font-semibold tracking-tight">The client sees</h3>
                </div>

                <ul class="mt-5 space-y-3">
                    @foreach ($visible as $line)
                        <li class="flex gap-3 text-[14px] leading-relaxed text-[#4A4F63]">
                            <svg class="mt-[3px] h-4 w-4 shrink-0 text-[#0E7A55]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            {{ $line }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-black/[.07] bg-[#FAFAFC] p-7">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-black/[.05]">
                        <svg class="h-4 w-4 text-[#7A8092]" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="8.5" width="13" height="8" rx="2"/><path d="M6.5 8.5V6a3.5 3.5 0 1 1 7 0v2.5"/></svg>
                    </span>
                    <h3 class="text-[15px] font-semibold tracking-tight">Stays with the team</h3>
                </div>

                <ul class="mt-5 space-y-3">
                    @foreach ($internal as $line)
                        <li class="flex gap-3 text-[14px] leading-relaxed text-[#7A8092]">
                            <svg class="mt-[5px] h-3 w-3 shrink-0 text-[#C8CCD6]" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 6h8"/></svg>
                            {{ $line }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-marketing.section>

    {{-- ========================= The status email ====================== --}}
    <x-marketing.section>
        <x-marketing.split reverse>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="Fewer check-ins"
                    heading="The update writes itself, because it is the work."
                    lede="A status report is a copy of the board, made by hand, already stale by the time it is read. The portal removes the copy."
                    align="left"
                />

                <div class="mt-8 space-y-5">
                    @foreach ([
                        ['Every move is logged', 'Status changes, uploads and comments land in the task\'s activity trail with a timestamp. That trail is the report.'],
                        ['Replies land on the task', 'A client answer sits against the work it concerns instead of in an inbox, where the next person to pick the task up will never find it.'],
                        ['Sign-off has a place', 'Approval happens on the task that is being approved, so "who signed off on this, and when?" has an answer.'],
                    ] as [$label, $copy])
                        <div class="border-l-2 border-black/[.07] pl-5">
                            <span class="block text-[14.5px] font-semibold tracking-tight">{{ $label }}</span>
                            <span class="mt-1.5 block text-[14px] leading-relaxed text-[#5B6076]">{{ $copy }}</span>
                        </div>
                    @endforeach
                </div>
            </x-slot:text>

            <x-slot:visual>
                <x-marketing.window chrome="clarix.app/portal/northwind">
                    <div class="p-5">
                        <div class="flex items-baseline justify-between">
                            <h3 class="text-[14px] font-semibold tracking-tight">Northwind</h3>
                            <span class="font-mono-ui text-[10px] text-[#A1A6B4]">September</span>
                        </div>

                        <div class="mt-4 space-y-2.5">
                            @foreach ([
                                ['Pricing page rewrite', 'In progress', 'bg-[#EEF0FF] text-indigo-700', '62%'],
                                ['Q3 landing page copy', 'Pending', 'bg-[#F1F2F6] text-[#5B6076]', '0%'],
                                ['Social kit, August', 'Completed', 'bg-[#ECFDF3] text-[#0E7A55]', '100%'],
                                ['Launch email sequence', 'In progress', 'bg-[#EEF0FF] text-indigo-700', '35%'],
                            ] as [$title, $status, $tint, $pct])
                                <div class="rounded-lg border border-black/[.06] bg-white p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="min-w-0 truncate text-[12px] font-medium">{{ $title }}</span>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold {{ $tint }}">{{ $status }}</span>
                                    </div>
                                    <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-black/[.06]">
                                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ $pct }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-4 border-t border-black/[.06] pt-3 text-[11px] text-[#A1A6B4]">
                            Updated live — no export, no attachment.
                        </p>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Let clients watch the work, not chase it."
        lede="One link into the board they are paying for, scoped to what concerns them."
    />

</x-marketing.layout>
