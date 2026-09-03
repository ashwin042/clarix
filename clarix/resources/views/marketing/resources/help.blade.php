@php
    // Grouped so the accordion stays readable — one long run of fourteen rows
    // is a list, not an answer. Each group gets its own idPrefix, since the
    // panel ids have to stay unique across the page.
    $groups = [
        [
            'id'    => 'setup',
            'label' => 'Getting set up',
            'items' => [
                [
                    'q' => 'How long does setting Clarix up take?',
                    'a' => 'An organisation, the units under it, the people in them, and then the first task. Most teams are running the same afternoon. The only decision that needs real thought is how you want work grouped into units, because that is the boundary everything else — the board, storage, analytics and permissions — is measured against.',
                ],
                [
                    'q' => 'Do I have to design the board before I can use it?',
                    'a' => 'No. Six statuses ship with the product — Pending, On hold, In progress, Sent for review, Completed and Cancelled — and they are the ones agency work actually passes through. There is nothing to configure before the first task goes in.',
                ],
                [
                    'q' => 'Can I bring existing work across?',
                    'a' => 'Tasks are created with a brief, a unit, an owner and a credit amount, so moving live work in is a matter of re-briefing what is still open rather than importing an archive. Enterprise plans include onboarding and migration support; on the other plans it is a manual pass, which for most teams is one sitting.',
                ],
            ],
        ],
        [
            'id'    => 'board',
            'label' => 'The board, files and credits',
            'items' => [
                [
                    'q' => 'When is a credit actually charged?',
                    'a' => 'The amount is agreed when the task is briefed and held against the balance as committed. The debit is written to the ledger once, at the moment the task moves into Completed. Cancelled work is never debited — the entry is simply not written.',
                ],
                [
                    'q' => 'Where do files live?',
                    'a' => 'On the task that asked for them. Versions stack in place with the newest marked rather than overwriting each other, and storage is counted per unit and totalled for the organisation, so the plan ceiling arrives as a number rather than as a failed upload.',
                ],
                [
                    'q' => 'What can a client see?',
                    'a' => 'The status of the tasks that are theirs, the comments addressed to them, and the files marked as deliverables. Credit costs, who the work is assigned to, internal review notes and every other client on the board stay with your team. It is the same task record, with the internal half withheld.',
                ],
                [
                    'q' => 'What happens to a cancelled task?',
                    'a' => 'It comes off the board and stays in the record. Its history and its files remain attached, so a question about it six months later still has an answer, and it never silently disappears from a client\'s view.',
                ],
            ],
        ],
        [
            'id'    => 'plans',
            'label' => 'Roles, plans and support',
            'items' => [
                [
                    'q' => 'Who can see across units?',
                    'a' => 'Admins and project managers see the whole organisation. A supervisor sees their own unit and nothing from the ones next door. Writers see the work assigned to them, and HR sees attendance, leave and payroll without a line of sight into client work. An admin can adjust what each role is granted.',
                ],
                [
                    'q' => 'Which plan do I need?',
                    'a' => 'Base covers task boards and file attachments with 5 GB of storage. Standard adds the full ERP layer — attendance, leave and payroll — plus Gantt charts and the AI chatbot at 50 GB. Pro adds full MCP and automation access at 100 GB. Enterprise has no list price and is sized during onboarding.',
                ],
                [
                    'q' => 'Is there a limit on teams or units?',
                    'a' => 'Yes, and the ceiling rises with the plan — Enterprise is the only tier without one. The exact counts sit on the pricing page beside storage and the feature differences, so they are in one place rather than repeated here where they would drift.',
                ],
                [
                    'q' => 'How do I reach a person?',
                    'a' => 'Email support on Base, priority email on Standard, priority chat on Pro, and a dedicated account manager on Enterprise. If you are already signed in, raising an issue from inside Clarix is usually faster — it arrives with your own admin first, with the whole context attached.',
                ],
            ],
        ],
    ];

    // Straight off the pricing table's support lines.
    $support = [
        ['plan' => 'Base',       'channel' => 'Email support'],
        ['plan' => 'Standard',   'channel' => 'Priority email support'],
        ['plan' => 'Pro',        'channel' => 'Priority chat support'],
        ['plan' => 'Enterprise', 'channel' => 'Dedicated account manager'],
    ];
@endphp

<x-marketing.layout
    title="Help Center — Clarix"
    description="The questions that come up first — how credits are charged, what clients can see, who can see across units — and the ways to reach a person when the answer is not here."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[560px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-14 pt-12 sm:pt-16 lg:pb-16">
            <x-marketing.page-hero
                eyebrow="Help Center"
                heading="Answers, and a way to reach a person."
                lede="The questions that come up first, grouped by where they come up. If the answer is not here, every plan has a way through to somebody."
                align="center"
                primary="/home#schedule-demo"
                primary-label="Talk to us"
                :secondary="route('marketing.docs')"
                secondary-label="Read the docs"
            />
        </div>
    </main>

    {{-- ============================== FAQ ============================== --}}
    <x-marketing.section surface="soft" divider width="max-w-4xl">
        @foreach ($groups as $g => $group)
            <div @class(['mt-16' => ! $loop->first])>
                <h2 class="font-mono-ui text-[11px] uppercase tracking-[.12em] text-[#8A8FA0]">{{ $group['label'] }}</h2>

                {{-- Only the very first panel on the page ships open; the other
                     groups start closed so the page does not open as a wall. --}}
                <x-marketing.faq
                    :items="$group['items']"
                    :open="$g === 0 ? 0 : false"
                    :id-prefix="'help-' . $group['id']"
                    class="mt-5"
                />
            </div>
        @endforeach
    </x-marketing.section>

    {{-- =========================== Contact panel ======================= --}}
    <x-marketing.section>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="Still stuck"
                    heading="Raise it from inside Clarix."
                    lede="If you are already signed in, an issue raised in the product carries its own context — who raised it, in which unit, against which work — and reaches your admin before it reaches us."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['It arrives with the context', 'A priority, a status and the thread underneath it. Nobody has to reconstruct what you were doing when it went wrong.'],
                        ['Your admin sees it first', 'Most of what gets raised is a permission or a setup question, and the person who can fix that is in your own organisation.'],
                        ['It has a state, not an inbox', 'Open, in review, resolved or closed — so an issue is either being worked on or finished, never quietly lost in a mailbox.'],
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

                <div class="mt-9 overflow-hidden rounded-2xl border border-black/[.07]">
                    <div class="border-b border-black/[.06] bg-[#FAFAFC] px-5 py-3">
                        <span class="font-mono-ui text-[10px] uppercase tracking-[.08em] text-[#A1A6B4]">Support by plan</span>
                    </div>
                    @foreach ($support as $row)
                        <div class="flex items-baseline justify-between gap-4 border-b border-black/[.06] px-5 py-3.5 last:border-b-0">
                            <span class="text-[13.5px] font-semibold tracking-tight">{{ $row['plan'] }}</span>
                            <span class="text-[13px] text-[#5B6076]">{{ $row['channel'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-slot:text>

            <x-slot:visual>
                <x-marketing.window chrome="clarix.app/issues/214">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4 border-b border-black/[.06] pb-3">
                            <div class="min-w-0">
                                <span class="font-mono-ui text-[10px] text-[#A1A6B4]">ISS-214</span>
                                <h3 class="mt-0.5 truncate text-[13px] font-semibold tracking-tight">Supervisor cannot see the Design board</h3>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <span class="rounded-full bg-[#FFF4E8] px-2 py-0.5 text-[9px] font-semibold text-[#B45309]">Medium</span>
                                <span class="rounded-full bg-[#EEF6FF] px-2 py-0.5 text-[9px] font-semibold text-[#1D6FB8]">In review</span>
                            </div>
                        </div>

                        <div class="mt-3 space-y-3">
                            <div class="flex gap-2.5">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#7C3AED] text-[8px] font-semibold text-white">AK</span>
                                <div class="min-w-0">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-[11px] font-semibold">Supervisor, Design</span>
                                        <span class="text-[9px] text-[#A1A6B4]">2h</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] leading-relaxed text-[#4A4F63]">
                                        I can open my own tasks but the unit board is empty. Everyone else in Design sees it.
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-2.5">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0E7A55] text-[8px] font-semibold text-white">AD</span>
                                <div class="min-w-0">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-[11px] font-semibold">Admin</span>
                                        <span class="text-[9px] text-[#A1A6B4]">just now</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] leading-relaxed text-[#4A4F63]">
                                        You were on Content, not Design. Moved you across — reload and it should be there.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3.5 flex items-center gap-2 rounded-lg border border-black/[.07] bg-[#FAFAFC] px-2.5 py-2">
                            <span class="flex-1 text-[10.5px] text-[#A1A6B4]">Reply…</span>
                            <span class="rounded-md border border-black/[.07] bg-white px-2 py-1 font-mono-ui text-[9px] text-[#5B6076]">Resolve</span>
                            <span class="rounded-md bg-indigo-600 px-2 py-1 text-[9.5px] font-medium text-white">Send</span>
                        </div>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Nothing here answering it?"
        lede="Book twenty minutes and we will walk your setup rather than sending you a link to this page."
    />

</x-marketing.layout>
