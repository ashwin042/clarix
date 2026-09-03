@php
    // A task carries a credit_amount, and the debit is written when the task
    // settles — not when it is briefed. That is the whole idea of this page.
    $ledger = [
        ['ref' => 'CLX-1019', 'title' => 'Social kit, August',   'client' => 'Northwind', 'delta' => -14,  'when' => '3h ago',    'kind' => 'debit'],
        ['ref' => '—',        'title' => 'Top-up, September',    'client' => 'Northwind', 'delta' => 500,  'when' => 'yesterday', 'kind' => 'credit'],
        ['ref' => 'CLX-1004', 'title' => 'Brand guideline pass', 'client' => 'Aurora',    'delta' => -32,  'when' => 'Mon',       'kind' => 'debit'],
        ['ref' => 'CLX-0998', 'title' => 'Newsletter, week 35',  'client' => 'Meridian',  'delta' => -12,  'when' => 'Mon',       'kind' => 'debit'],
    ];
@endphp

<x-marketing.layout
    title="Credits & Billing — Clarix"
    description="Track spend as work moves. Every task carries a credit cost, the ledger records the debit when it settles, and the balance is visible to everyone who needs it."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-16">

                <x-marketing.page-hero
                    eyebrow="Credits & Billing"
                    heading="Track spend as work moves."
                    lede="Every task carries a credit cost from the moment it is briefed. The ledger records the debit when the work lands in Completed — so the balance always matches what has actually shipped."
                />

                {{-- The homepage's credit card, brought up to desktop scale and
                     set beside the ledger the balance is drawn from. --}}
                <div class="rise rise-4 grid gap-4 sm:grid-cols-[minmax(0,.85fr)_minmax(0,1fr)] sm:items-start">

                    <div class="rounded-2xl border border-black/[.07] bg-white p-5 win-shadow">
                        <div class="text-[11px] font-medium text-[#A1A6B4]">Credit balance</div>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-[38px] font-semibold leading-none tracking-tight">1,240</span>
                            <span class="text-[11px] text-[#5B6076]">credits</span>
                        </div>

                        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-[#EEF0FF]">
                            <div class="h-full w-[62%] rounded-full bg-indigo-600"></div>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[10.5px]">
                            <span class="text-[#5B6076]">62% of the September pack used</span>
                            <span class="font-mono-ui text-[#0E7A55]">−86 this week</span>
                        </div>

                        <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-black/[.06] pt-4">
                            <div>
                                <dt class="text-[9.5px] font-medium uppercase tracking-[.08em] text-[#A1A6B4]">Committed</dt>
                                <dd class="mt-1 font-mono-ui text-[14px] font-medium">318</dd>
                            </div>
                            <div>
                                <dt class="text-[9.5px] font-medium uppercase tracking-[.08em] text-[#A1A6B4]">Settled</dt>
                                <dd class="mt-1 font-mono-ui text-[14px] font-medium">2,042</dd>
                            </div>
                        </dl>
                    </div>

                    <x-marketing.window chrome="clarix.app/credits">
                        <div class="p-3.5">
                            <div class="flex items-center justify-between pb-2.5">
                                <span class="text-[11px] font-semibold">Ledger</span>
                                <span class="rounded-md border border-black/[.07] px-2 py-0.5 text-[9px] text-[#5B6076]">September</span>
                            </div>

                            <div class="space-y-1.5">
                                @foreach ($ledger as $row)
                                    <div class="flex items-center gap-2.5 rounded-lg border border-black/[.06] px-2.5 py-2">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $row['kind'] === 'credit' ? 'bg-[#FFB27A]' : 'bg-indigo-600' }}"></span>
                                        <div class="min-w-0 flex-1">
                                            <span class="block truncate text-[10.5px] font-medium">{{ $row['title'] }}</span>
                                            <span class="font-mono-ui text-[8.5px] text-[#A1A6B4]">{{ $row['ref'] }} · {{ $row['client'] }} · {{ $row['when'] }}</span>
                                        </div>
                                        <span class="shrink-0 font-mono-ui text-[10.5px] font-medium {{ $row['delta'] > 0 ? 'text-[#0E7A55]' : 'text-[#0F1222]' }}">
                                            {{ $row['delta'] > 0 ? '+' : '−' }}{{ abs($row['delta']) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-marketing.window>
                </div>
            </div>
        </div>
    </main>

    {{-- ==================== When a credit is spent ===================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="The debit"
            heading="Charged when it ships, not when it's promised."
            lede="A task's cost is agreed up front and held against the balance. The ledger entry is written once, at the moment the task is marked complete."
        />

        <div class="mt-14 grid gap-px overflow-hidden rounded-2xl border border-black/[.07] bg-black/[.06] sm:grid-cols-3">
            @foreach ([
                ['Briefed', 'The task is created with a credit amount attached. It counts as committed — visible in the balance, not yet taken out of it.'],
                ['In flight', 'The cost travels with the task through On hold, In progress and review. Changing scope changes the number in one place.'],
                ['Settled', 'The task lands in Completed and the debit is written to the ledger, once, against the client it belongs to.'],
            ] as $i => [$stage, $copy])
                <div class="bg-white p-7">
                    <div class="flex items-center gap-2.5">
                        <span class="font-mono-ui text-[11px] text-[#A1A6B4]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 class="text-[15px] font-semibold tracking-tight">{{ $stage }}</h3>
                    </div>
                    <p class="mt-3 text-[14px] leading-relaxed text-[#5B6076]">{{ $copy }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-[13.5px] text-[#7A8092]">
            Cancelled work is never debited — the entry is simply not written.
        </p>
    </x-marketing.section>

    {{-- ============================ Exports ============================ --}}
    <x-marketing.section>
        <x-marketing.split reverse>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="At month end"
                    heading="An invoice line for every task."
                    lede="Export the ledger for a period and get one row per task, grouped by unit or flat, with subtotals and a grand total already calculated."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Grouped by unit', 'Each unit gets its own header and subtotal, so a department can be billed or charged back on its own.'],
                        ['Or flat', 'One continuous list when you want the whole organisation on a single sheet.'],
                        ['Reconcilable', 'Every row carries its task reference, so a client question about a line has an answer that takes seconds.'],
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
                <x-marketing.window chrome="september-northwind.csv">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[380px] font-mono-ui text-[10.5px]">
                            <thead>
                                <tr class="border-b border-black/[.07] bg-[#FAFAFC] text-left text-[9.5px] uppercase tracking-[.06em] text-[#A1A6B4]">
                                    <th class="px-4 py-2.5 font-medium">Task</th>
                                    <th class="px-4 py-2.5 font-medium">Unit</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Credits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ([
                                    ['CLX-1019', 'Design', '14'],
                                    ['CLX-1004', 'Design', '32'],
                                    ['CLX-0998', 'Content', '12'],
                                    ['CLX-0991', 'Content', '20'],
                                ] as [$ref, $unit, $credits])
                                    <tr class="border-b border-black/[.05]">
                                        <td class="px-4 py-2.5 text-[#0F1222]">{{ $ref }}</td>
                                        <td class="px-4 py-2.5 text-[#7A8092]">{{ $unit }}</td>
                                        <td class="px-4 py-2.5 text-right text-[#0F1222]">{{ $credits }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-[#FAFAFC]">
                                    <td class="px-4 py-2.5 font-semibold text-[#0F1222]" colspan="2">Grand total</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-[#0F1222]">78</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Know what a month of work costs."
        lede="Committed, in flight and settled — on one balance, updated as the board moves."
    />

</x-marketing.layout>
