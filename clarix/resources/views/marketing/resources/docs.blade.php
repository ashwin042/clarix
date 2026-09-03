@php
    /*
     * The index. Each area is a real part of the product; 'route' is the page
     * that carries the fuller overview of it, reached by the arrow at the foot
     * of the card.
     *
     * 'id' is what the sidebar anchors at, so every link in the left column
     * resolves to something on this page.
     *
     * Each topic is a disclosure that opens inside its card. Topics are
     * ['q' => …, 'a' => …, 'steps' => [...], 'verified' => bool].
     *
     * ------------------------------------------------------------------
     * 'verified' => true  — checked against the code. The behaviour
     *                       described is what the product does today.
     * 'verified' => false — PLACEHOLDER. Written from the shape of the
     *                       feature rather than from its implementation;
     *                       the reader is told so, by the note appended
     *                       below. Confirm against the screen and flip the
     *                       flag before treating any of it as documentation.
     * ------------------------------------------------------------------
     *
     * The unverified four are all in Automation: the AI surfaces exist as
     * screens (Overview, Chatbot, Scheduled Tasks, MCP & Plugins, Calendar)
     * but their runtime behaviour was not read closely enough to write
     * instructions anyone should follow. Chatbot is the exception and is
     * marked verified.
     */
    $areas = [
        [
            'id'    => 'getting-started',
            'label' => 'Getting started',
            'blurb' => 'Standing an organisation up: the account, the units under it, the people in them, and the first task through the board.',
            'route' => 'marketing.help',
            'linkLabel' => 'Help Center',
            'topics' => [
                [
                    'q' => 'Create your organisation',
                    'verified' => true,
                    'a' => 'There is no self-serve sign-up, which is why every button on this site says log in rather than register. An organisation is created for you during onboarding together with its first admin account, and that account is the one that sets up everything below. Everyone else is added from inside the product.',
                ],
                [
                    'q' => 'Add units',
                    'verified' => true,
                    'a' => 'A unit is a team, and it is the boundary the board, storage and analytics are all measured against — so it is worth deciding before you add people.',
                    'steps' => [
                        'Open the Units screen from the admin area.',
                        'Create one unit per team you want kept apart — usually a craft such as Content or Design, or a client-facing group.',
                        'How many units a plan allows rises with the tier, so check the ceiling on the pricing page before you design a structure around a count.',
                    ],
                ],
                [
                    'q' => 'Add people and set roles',
                    'verified' => true,
                    'a' => 'An admin creates each account directly. There is no invitation email to chase and nothing for the new person to accept.',
                    'steps' => [
                        'Open the Users screen and create the account with a name, a username or email address, and a password.',
                        'Choose the role: admin, PM, supervisor, HR or writer.',
                        'Assign the unit. A supervisor and a writer both see the board through it, so this is the field that decides what they can reach.',
                    ],
                ],
                [
                    'q' => 'Brief your first task',
                    'verified' => true,
                    'a' => 'A task carries everything the work needs: what it is, who owns it, when it is due and what it costs.',
                    'steps' => [
                        'From the Tasks screen, choose New task.',
                        'Give it a title, a task code and a task type.',
                        'Set the unit and the project manager, then the priority and the deadline.',
                        'Set the credit amount. Once the task exists, only an admin can change this.',
                        'Save. The task lands in Pending, and the activity log starts recording from that moment.',
                    ],
                ],
            ],
        ],
        [
            'id'    => 'the-board',
            'label' => 'The board',
            'blurb' => 'The six statuses work moves through, the two ways of looking at them, and what the task record holds once it is open.',
            'route' => 'marketing.product.boards',
            'linkLabel' => 'Task Boards',
            'topics' => [
                [
                    'q' => 'Statuses and what each means',
                    'verified' => true,
                    'a' => 'A task sits in exactly one of six statuses: Pending, On hold, In progress, Sent for review, Completed and Cancelled. Five of them move work forward. Cancelled takes it off the board without deleting the record, and contributes nothing to any credit figure. A task counts toward credits when — and only when — it reaches Completed.',
                ],
                [
                    'q' => 'Board view and table view',
                    'verified' => true,
                    'a' => 'The same tasks, two ways round. The table is the default and sorts by any column; the board is the drag-and-drop kanban, where moving a card between columns is what changes its status. Your choice is remembered for the session, so you come back to the view you left.',
                ],
                [
                    'q' => 'Filter and sort',
                    'verified' => true,
                    'a' => 'Narrow the list by search, status, priority, unit or task type, and sort the table by any column in either direction. Changing a filter takes you back to the first page, so a narrowed view never strands you on page four of the result you were looking at before.',
                ],
                [
                    'q' => 'The task detail',
                    'verified' => true,
                    'a' => 'Opening a task puts everything attached to it on one screen: the brief and its important notes, the unit and project manager, the priority, deadline and credit amount, the writers assigned to it, the notes thread, the files, and the activity log underneath all of it.',
                ],
                [
                    'q' => 'The activity log',
                    'verified' => true,
                    'a' => 'Changes are recorded by observers on the model rather than by the screen that made them, so every route into an action is covered — the interface, a background job and the API all leave the same trail. Entries are appended and never edited. Writers are referred to by role rather than by name, so the log cannot be read as a report on one person.',
                ],
            ],
        ],
        [
            'id'    => 'files',
            'label' => 'Files',
            'blurb' => 'Attaching work to the task that asked for it, telling deliverables from drafts, and where the plan\'s storage ceiling is measured.',
            'route' => 'marketing.product.files',
            'linkLabel' => 'File Management',
            'topics' => [
                [
                    'q' => 'Attach a file to a task',
                    'verified' => true,
                    'a' => 'Files are uploaded onto the task they belong to rather than into a folder tree. Each one records who added it, when, its size and its type, and the unit\'s storage total is updated by the same observer that writes the file — so the number cannot drift away from what is actually stored.',
                ],
                [
                    'q' => 'Deliverables and working files',
                    'verified' => true,
                    'a' => 'Every file on a task is either a working attachment or a completed file. That flag is the line between the deliverable a client should receive and the briefs, references and drafts that got you there, and the two can be listed and downloaded separately.',
                ],
                [
                    'q' => 'Download everything on a task',
                    'verified' => true,
                    'a' => 'Rather than pulling files down one at a time, a task can be taken whole: every file on it, streamed out as a single archive. There is a second download that takes only the completed files, which is the one to reach for when you are sending work to a client.',
                ],
                [
                    'q' => 'Storage per unit',
                    'verified' => true,
                    'a' => 'Usage is tracked per unit and totalled for the organisation, and it is kept current as files arrive and leave rather than recalculated on a schedule. Admins get the breakdown by unit, which is what turns the plan\'s ceiling into a trend you can see coming instead of an upload that suddenly fails.',
                ],
            ],
        ],
        [
            'id'    => 'credits',
            'label' => 'Credits',
            'blurb' => 'What a credit is, when it counts, and how a period of completed work becomes an export with one row per task.',
            'route' => 'marketing.product.credits',
            'linkLabel' => 'Credits & Billing',
            'topics' => [
                [
                    'q' => 'Set a task\'s credit amount',
                    'verified' => true,
                    'a' => 'The credit amount is set when the task is briefed and travels with it from there. Only an admin can change it afterwards, which is what stops the agreed cost of a piece of work drifting quietly while it is in flight.',
                ],
                [
                    'q' => 'When credits count',
                    'verified' => true,
                    'a' => 'Credits are counted from completed work. A task contributes its amount once it reaches Completed and is stamped with a completion date; anything still moving, and anything cancelled, contributes nothing at all. That is why a month\'s figure only changes when something actually ships.',
                ],
                [
                    'q' => 'Reading the credit report',
                    'verified' => true,
                    'a' => 'The credit list is a report over completed tasks for a period. Filter it by date range, unit, project manager or task type, and read it two ways: grouped, which subtotals by unit, or unified, which is one flat list across the whole organisation.',
                ],
                [
                    'q' => 'Exporting a period',
                    'verified' => true,
                    'a' => 'Whatever you have filtered to can be exported. The grouped export carries a header and a subtotal for each unit and then a grand total; the unified export is a single table ending in the same grand total. Both give one row per task, so a client question about a line is answerable in seconds.',
                ],
            ],
        ],
        [
            'id'    => 'people',
            'label' => 'People and roles',
            'blurb' => 'Who can see what, how the unit boundary is enforced, and the attendance, leave and payroll records held against the same people.',
            'route' => 'marketing.solutions.agencies',
            'linkLabel' => 'For Agencies',
            'topics' => [
                [
                    'q' => 'The five roles',
                    'verified' => true,
                    'a' => 'Admin, project manager, supervisor, writer and HR. An admin sees the whole organisation. A PM runs delivery across units. A supervisor is scoped to their own unit and sees nothing from the ones beside it. A writer sees the work assigned to them. HR sees attendance, leave and payroll without a line of sight into client work. There is a sixth role, superadmin, but it is ours rather than yours: it is how organisations are created and suspended.',
                ],
                [
                    'q' => 'Permissions per role',
                    'verified' => true,
                    'a' => 'Roles are not a fixed set of hard-coded grants. Each one holds a set of permissions that an admin can widen or narrow from the authorization panel, so what ships is a sensible starting point rather than a ceiling you have to live with.',
                ],
                [
                    'q' => 'Attendance',
                    'verified' => true,
                    'a' => 'Attendance is recorded per person per day, with a status and an optional note, and can be entered or corrected for any chosen date. Rolled up by unit, it turns "who is actually around this week" into a number rather than a guess.',
                ],
                [
                    'q' => 'Leave types and approvals',
                    'verified' => true,
                    'a' => 'An admin defines the leave types the organisation uses, each with a default annual allowance, and people request leave against one of them. Balances are derived from the requests rather than kept in a column of their own — there is no stored figure that can quietly go wrong, because it is recomputed from what actually happened.',
                ],
                [
                    'q' => 'Payroll records',
                    'verified' => true,
                    'a' => 'Payroll is held per person per month: a base amount, deductions and a note. A record is drafted and then finalised as a separate, deliberate step, and everyone can see their own payroll without being able to see anybody else\'s.',
                ],
            ],
        ],
        [
            'id'    => 'automation',
            'label' => 'Automation',
            'blurb' => 'The AI surfaces inside the product — what the agent reads, what it acts on, and which plan each one arrives with.',
            'route' => 'marketing.product.ai',
            'linkLabel' => 'AI Automation',
            'topics' => [
                [
                    'q' => 'AXOKAI and the task record',
                    'verified' => false,
                    'a' => 'AXOKAI is the agent Clarix runs against your client work, and it has its own product site. Inside Clarix it works from the task record rather than from a prompt you have to assemble — the brief, the notes thread, the files and the completion history are what it reads.',
                ],
                [
                    'q' => 'Chatbot',
                    'verified' => true,
                    'a' => 'Ask about your work in plain language and get an answer drawn from your own tasks rather than from a general model guessing at your business. The assistant carries an effort setting, with a balanced default that suits most questions and room to trade speed for depth when one deserves it.',
                ],
                [
                    'q' => 'Scheduled tasks',
                    'verified' => false,
                    'a' => 'Recurring work that creates itself — the monthly report, the weekly batch — set up once against a trigger and then briefed and costed the way a manual task is.',
                ],
                [
                    'q' => 'MCP and plugins',
                    'verified' => false,
                    'a' => 'A catalogue of connections into the tools the work already lives in, grouped by category. Full automation access is a Pro feature; the plans below that carry the chatbot without it.',
                ],
                [
                    'q' => 'Calendar',
                    'verified' => false,
                    'a' => 'Deadlines, scheduled runs and delivery dates on one timeline, so a week that is already full looks full before you promise anything else into it.',
                ],
            ],
        ],
    ];

    // The reader is told which topics are provisional, in the panel itself.
    // Derived from the flag rather than written out per topic, so the two can
    // never disagree.
    foreach ($areas as $a => $area) {
        foreach ($area['topics'] as $t => $topic) {
            if (! ($topic['verified'] ?? false)) {
                $areas[$a]['topics'][$t]['note'] =
                    'Draft. This one is written from the shape of the feature rather than checked against the screen, so treat it as an outline until it loses this note.';
            }
        }
    }
@endphp

<x-marketing.layout
    title="Documentation — Clarix"
    description="How each part of Clarix works: the board and its statuses, files and storage, credits and the report, roles and permissions, and the automation layer."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[560px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-14 pt-12 sm:pt-16 lg:pb-16">
            <x-marketing.page-hero
                eyebrow="Documentation"
                heading="Everything Clarix does, written down."
                lede="Six areas, in the order you meet them. Open any topic to read it here; the link at the foot of each card goes to the fuller overview of that area."
                align="center"
            />
        </div>
    </main>

    {{-- ============================= Index ============================= --}}
    <x-marketing.section surface="soft" divider width="max-w-6xl">
        <div class="grid gap-10 lg:grid-cols-[210px_minmax(0,1fr)] lg:gap-14">

            {{-- Sidebar tree. Every entry is an anchor onto this page, so the
                 column navigates rather than merely listing. --}}
            <nav class="lg:sticky lg:top-8 lg:self-start" aria-label="Documentation contents">
                <span class="block font-mono-ui text-[10px] uppercase tracking-[.12em] text-[#8A8FA0]">Contents</span>

                <ul class="mt-4 space-y-px border-l border-black/[.10]">
                    @foreach ($areas as $area)
                        <li>
                            <a href="#{{ $area['id'] }}"
                               class="-ml-px block border-l border-transparent py-1.5 pl-4 font-mono-ui text-[11.5px] text-[#5B6076] transition hover:border-indigo-600 hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                {{ $area['label'] }}
                            </a>

                            <ul class="mb-2 space-y-1 pl-4">
                                @foreach ($area['topics'] as $topic)
                                    <li class="border-l border-transparent pl-4 text-[11.5px] leading-snug text-[#A1A6B4]">{{ $topic['q'] }}</li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Section cards, in the same paper stock the pricing table uses. --}}
            <div class="space-y-5">
                @foreach ($areas as $i => $area)
                    <section id="{{ $area['id'] }}" class="scroll-mt-8 rounded-[18px] border border-[#E9E4D8] bg-[#FBFAF6] p-6 sm:p-7">
                        <div class="flex items-baseline gap-3">
                            <span class="font-mono-ui text-[11px] text-[#A1A6B4]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <h2 class="font-display text-[22px] font-normal leading-[1.2] tracking-[-0.015em] text-[#17143A]">
                                {{ $area['label'] }}
                            </h2>
                        </div>

                        <p class="mt-3 max-w-2xl text-[14px] leading-relaxed text-[#5B6076]">{{ $area['blurb'] }}</p>

                        {{-- The tags are the disclosure's triggers: the card
                             opens in place rather than sending the reader to a
                             page that does not exist yet. Every card starts
                             closed, so the index still reads as an index. --}}
                        <x-marketing.faq
                            variant="pills"
                            :items="$area['topics']"
                            :open="false"
                            :id-prefix="'docs-' . $area['id']"
                            class="mt-5"
                        />

                        <a href="{{ route($area['route']) }}"
                           class="mt-6 inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-indigo-600 transition hover:text-indigo-700">
                            {{ $area['linkLabel'] }}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                        </a>
                    </section>
                @endforeach
            </div>
        </div>
    </x-marketing.section>

    {{-- ======================= What a page looks like ================== --}}
    <x-marketing.section>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="How it is written"
                    heading="Short answers, and the screen beside them."
                    lede="Each topic is written against the screen it describes, in the same language the interface uses. If the app calls a column Sent for review, so does the page about it."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Named the way the product is', 'Units, credits, statuses and roles are the terms the app uses. There is no second vocabulary to learn before the documentation becomes useful.'],
                        ['One question per topic', 'A topic answers a single thing — when a credit counts, how a supervisor\'s view is scoped — so the answer is the first sentence rather than the ninth paragraph.'],
                        ['Drafts say so', 'A topic that has not been checked line by line against the product carries a note saying exactly that. We would rather flag an outline than let it pass as documentation.'],
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
                {{-- The reader itself, drawn in the site's window chrome. --}}
                <x-marketing.window chrome="clarix.app/docs/the-board/statuses">
                    <div class="grid grid-cols-[96px_minmax(0,1fr)]">

                        <div class="border-r border-black/[.06] bg-[#FAFAFC] p-3">
                            @foreach (['Getting started', 'The board', 'Files', 'Credits', 'People', 'Automation'] as $nav)
                                <span class="mb-1.5 block truncate font-mono-ui text-[8.5px] {{ $nav === 'The board' ? 'font-medium text-indigo-700' : 'text-[#A1A6B4]' }}">{{ $nav }}</span>
                            @endforeach
                        </div>

                        <div class="p-4">
                            <span class="font-mono-ui text-[8.5px] uppercase tracking-[.1em] text-[#A1A6B4]">The board</span>
                            <h3 class="mt-1.5 font-display text-[15px] font-normal leading-tight tracking-[-0.01em] text-[#17143A]">Statuses</h3>

                            <p class="mt-2.5 text-[10px] leading-relaxed text-[#5B6076]">
                                A task sits in exactly one status. Five of them move work forward; the sixth
                                takes it off the board without deleting the record.
                            </p>

                            <div class="mt-3 space-y-1.5">
                                @foreach ([
                                    ['Pending',         'bg-[#F1F2F6] text-[#5B6076]'],
                                    ['In progress',     'bg-[#EEF0FF] text-indigo-700'],
                                    ['Sent for review', 'bg-[#EEF6FF] text-[#1D6FB8]'],
                                    ['Completed',       'bg-[#ECFDF3] text-[#0E7A55]'],
                                ] as [$status, $tint])
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-1.5 py-0.5 text-[8px] font-semibold {{ $tint }}">{{ $status }}</span>
                                        <span class="h-1 flex-1 rounded-full bg-black/[.05]"></span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3.5 rounded-lg border border-black/[.06] bg-[#FAFAFC] px-2.5 py-2">
                                <span class="font-mono-ui text-[8px] uppercase tracking-[.08em] text-[#A1A6B4]">Note</span>
                                <p class="mt-0.5 text-[9px] leading-snug text-[#5B6076]">A task counts toward credits once it reaches Completed.</p>
                            </div>
                        </div>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Read it, or just open the board."
        lede="Most of Clarix explains itself once a real task is moving through it. The documentation is there for the parts that don't."
    />

</x-marketing.layout>
