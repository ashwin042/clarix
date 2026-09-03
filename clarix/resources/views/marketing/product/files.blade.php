@php
    // Storage ceilings are the ones on the pricing table, not invented figures.
    $storage = [
        ['plan' => 'Base',       'limit' => '5 GB',   'note' => 'Small teams getting organised'],
        ['plan' => 'Standard',   'limit' => '50 GB',  'note' => 'Growing agencies with real client load'],
        ['plan' => 'Pro',        'limit' => '100 GB', 'note' => '+Rs 1,000 per extra 100 GB on request'],
        ['plan' => 'Enterprise', 'limit' => 'Custom', 'note' => 'Sized with you during onboarding'],
    ];

    $files = [
        ['name' => 'northwind-pricing-brief.pdf', 'size' => '820 KB', 'by' => 'Priya R.', 'when' => '2 days ago', 'kind' => 'pdf'],
        ['name' => 'pricing-page-v3.docx',        'size' => '1.2 MB', 'by' => 'Aman K.',  'when' => '2h ago',     'kind' => 'doc', 'latest' => true],
        ['name' => 'pricing-page-v2.docx',        'size' => '1.1 MB', 'by' => 'Aman K.',  'when' => 'yesterday',  'kind' => 'doc'],
        ['name' => 'hero-comparison.png',         'size' => '3.4 MB', 'by' => 'Dev S.',   'when' => 'yesterday',  'kind' => 'img'],
    ];

    $tints = [
        'pdf' => 'bg-[#FEF2F2] text-[#B91C1C]',
        'doc' => 'bg-[#EEF0FF] text-indigo-700',
        'img' => 'bg-[#ECFDF3] text-[#0E7A55]',
    ];
@endphp

<x-marketing.layout
    title="File Management — Clarix"
    description="Every file attached to the task it belongs to: briefs, drafts and deliverables, versioned in place and counted against your plan's storage."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">

                <x-marketing.page-hero
                    eyebrow="File Management"
                    heading="Keep every file attached to its task."
                    lede="The brief, the drafts and the final deliverable live on the task they belong to — not in a shared drive nobody has tidied since March."
                />

                <div class="rise rise-4">
                    <x-marketing.window chrome="clarix.app/tasks/1036">
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-4 border-b border-black/[.06] pb-3">
                                <div>
                                    <span class="font-mono-ui text-[10px] text-[#A1A6B4]">CLX-1036</span>
                                    <h3 class="mt-0.5 text-[13px] font-semibold tracking-tight">Northwind — pricing page rewrite</h3>
                                </div>
                                <span class="shrink-0 rounded-full bg-[#EEF0FF] px-2 py-0.5 text-[9px] font-semibold text-indigo-700">In progress</span>
                            </div>

                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-[11px] font-semibold">Files</span>
                                <span class="rounded-full bg-black/[.05] px-1.5 py-0.5 font-mono-ui text-[9px] text-[#5B6076]">4</span>
                            </div>

                            <div class="mt-2.5 space-y-1.5">
                                @foreach ($files as $file)
                                    <div class="flex items-center gap-2.5 rounded-lg border border-black/[.06] bg-white px-2.5 py-2 {{ ($file['latest'] ?? false) ? 'ring-1 ring-indigo-200' : '' }}">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[7.5px] font-bold uppercase {{ $tints[$file['kind']] }}">
                                            {{ $file['kind'] }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5">
                                                <span class="min-w-0 truncate text-[10.5px] font-medium">{{ $file['name'] }}</span>
                                                @if ($file['latest'] ?? false)
                                                    <span class="shrink-0 rounded bg-[#EEF0FF] px-1 py-px text-[8px] font-semibold text-indigo-700">latest</span>
                                                @endif
                                            </div>
                                            <span class="font-mono-ui text-[8.5px] text-[#A1A6B4]">{{ $file['by'] }} · {{ $file['when'] }}</span>
                                        </div>
                                        <span class="shrink-0 font-mono-ui text-[9px] text-[#A1A6B4]">{{ $file['size'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 flex items-center justify-between border-t border-black/[.06] pt-3">
                                <span class="font-mono-ui text-[9.5px] text-[#A1A6B4]">6.5 MB on this task</span>
                                <span class="rounded-md bg-indigo-600 px-2.5 py-1 text-[9.5px] font-medium text-white">Download all</span>
                            </div>
                        </div>
                    </x-marketing.window>
                </div>
            </div>
        </div>
    </main>

    {{-- ==================== Attached, not uploaded ===================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Where files live"
            heading="A folder tree forgets. A task doesn't."
            lede="Files in a drive are organised by whoever uploaded them. Files in Clarix are organised by the work — which is the only structure everyone already agrees on."
        />

        <div class="mt-14 grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['Versions stack in place', 'v2 does not overwrite v1, and neither of them becomes final-FINAL-v2b. The newest is marked, the history stays.'],
                ['Context comes with it', 'Open a file and the brief that asked for it, the thread that reviewed it and the person who owns it are all one click away.'],
                ['It leaves with the task', 'Cancel or complete the work and its files stay attached to that record, so an audit six months later still has the evidence.'],
            ] as [$label, $copy])
                <div class="rounded-2xl border border-black/[.07] bg-white p-7 shadow-[0_18px_34px_-24px_rgba(14,17,38,.34)]">
                    <h3 class="text-[15px] font-semibold tracking-tight">{{ $label }}</h3>
                    <p class="mt-2.5 text-[14px] leading-relaxed text-[#5B6076]">{{ $copy }}</p>
                </div>
            @endforeach
        </div>
    </x-marketing.section>

    {{-- ========================= Storage per plan ====================== --}}
    <x-marketing.section>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="Storage"
                    heading="You can see what you are using."
                    lede="Storage is tracked per unit and totalled for the organisation, so the number is visible before it becomes a problem rather than after an upload fails."
                    align="left"
                />

                <p class="mt-7 text-[14px] leading-relaxed text-[#5B6076]">
                    Admins get a storage breakdown by unit; Pro adds another 100&nbsp;GB for
                    Rs&nbsp;1,000 on request, and Enterprise is sized during onboarding.
                </p>

                <a href="/home#pricing"
                   class="mt-6 inline-flex items-center gap-1.5 text-[14px] font-semibold text-indigo-600 transition hover:text-indigo-700">
                    Compare plans
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                </a>
            </x-slot:text>

            <x-slot:visual>
                <div class="overflow-hidden rounded-2xl border border-black/[.07] bg-white shadow-[0_18px_34px_-24px_rgba(14,17,38,.34)]">
                    @foreach ($storage as $row)
                        <div class="flex items-baseline justify-between gap-4 border-b border-black/[.06] px-6 py-5 last:border-b-0">
                            <div>
                                <span class="block text-[14.5px] font-semibold tracking-tight">{{ $row['plan'] }}</span>
                                <span class="mt-0.5 block text-[12.5px] text-[#7A8092]">{{ $row['note'] }}</span>
                            </div>
                            <span class="shrink-0 font-mono-ui text-[15px] font-medium text-[#0F1222]">{{ $row['limit'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Stop hunting for the final version."
        lede="Briefs in, drafts through review, deliverables out — all on the task that paid for them."
    />

</x-marketing.layout>
