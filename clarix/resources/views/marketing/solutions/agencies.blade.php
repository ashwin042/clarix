@php
    // The diagram's outer band is units — the thing an agency has several of.
    // Units are a real object in the product: a user belongs to one, the board
    // filters by it, and storage and analytics are totalled per unit.
    $units = [
        ['label' => 'Content',     'icon' => '<path d="M5 2.5h6.5L16 7v10.5H5V2.5Z"/><path d="M11 2.5V7h5M7.5 10.5h5M7.5 13.5h3.5"/>'],
        ['label' => 'Design',      'icon' => '<path d="M3 17l3-1 9-9a2 2 0 0 0-3-3l-9 9-1 3Z"/><path d="m12 5 3 3"/>'],
        ['label' => 'Development', 'icon' => '<path d="M7 6.5 3.5 10 7 13.5M13 6.5 16.5 10 13 13.5M11.5 4.5l-3 11"/>'],
        ['label' => 'Accounts',    'icon' => '<path d="M3 16.5h14"/><path d="M5.5 16.5V9M9.5 16.5V4.5M13.5 16.5v-5"/>'],
    ];

    // The middle band is the layer that reaches across units. Both are real
    // roles: a PM briefs and costs work anywhere, a supervisor owns one unit.
    $oversight = [
        ['label' => 'Project managers', 'icon' => '<circle cx="10" cy="7" r="3"/><path d="M4.5 16.5a5.5 5.5 0 0 1 11 0"/>'],
        ['label' => 'Supervisors',      'icon' => '<path d="M10 2.5 3.5 5.2v5c0 4 2.8 6.4 6.5 7.3 3.7-.9 6.5-3.3 6.5-7.3v-5L10 2.5Z"/><path d="m7.5 10 1.8 1.8L13 8"/>'],
    ];

    // Roles as the product actually defines them, minus superadmin — that one
    // is ours, not the agency's. Each line says what the role can see, since
    // that is the question an agency owner is actually asking.
    $roles = [
        ['name' => 'Admin',           'copy' => 'The whole organisation: every unit\'s board, the credit ledger, the storage breakdown and the subscription.'],
        ['name' => 'Project manager', 'copy' => 'Delivery across units. Briefs the task, sets its credit amount, assigns an owner and answers the client.'],
        ['name' => 'Supervisor',      'copy' => 'One unit. Their team\'s board and their team\'s workload — and nothing from the units next door.'],
        ['name' => 'Writer',          'copy' => 'The work itself: assigned tasks, the files on them, and the thread where they are reviewed.'],
        ['name' => 'HR',              'copy' => 'Attendance, leave and payroll for the people, without a line of sight into client work.'],
    ];

    // A unit rollup, the shape the analytics and storage screens report in.
    $rollup = [
        ['unit' => 'Content',     'flight' => 7, 'done' => 24, 'credits' => 486, 'storage' => '11.4 GB'],
        ['unit' => 'Design',      'flight' => 5, 'done' => 18, 'credits' => 512, 'storage' => '22.8 GB'],
        ['unit' => 'Development', 'flight' => 4, 'done' => 9,  'credits' => 738, 'storage' => '6.2 GB'],
        ['unit' => 'Accounts',    'flight' => 2, 'done' => 6,  'credits' => 104, 'storage' => '0.9 GB'],
    ];
@endphp

<x-marketing.layout
    title="For Agencies — Clarix"
    description="Manage multiple clients and teams: units for each craft, roles that keep them apart, one board above all of them and one ledger that says what a month of work cost."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14">

                <x-marketing.page-hero
                    eyebrow="For Agencies"
                    heading="Manage multiple clients and teams."
                    lede="Content, design and development each work their own queue. The board, the ledger and the client view sit above all of them — so a partner can see the state of the agency without asking three people for a status."
                />

                {{-- The homepage's arc, read as an org chart: several units
                     resolving into one organisation. --}}
                <div class="rise rise-4">
                    <x-marketing.org-diagram
                        :outer="$units"
                        :mid="$oversight"
                        outer-label="Units"
                        mid-label="Oversight"
                        core="One organisation"
                        sub="One board, one ledger, one set of roles"
                    />
                </div>
            </div>
        </div>
    </main>

    {{-- ============================ Roles ============================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Who sees what"
            heading="Five roles, and a boundary that holds."
            lede="An agency is not one team, and a board that shows everyone everything is the fastest way to leak one client's work into another's view. Roles decide what loads."
        />

        <div class="mt-14 space-y-px overflow-hidden rounded-2xl border border-black/[.07] bg-black/[.06]">
            @foreach ($roles as $i => $role)
                <div class="grid gap-3 bg-white px-6 py-5 sm:grid-cols-[200px_1fr] sm:items-baseline sm:gap-8">
                    <div class="flex items-center gap-2.5">
                        <span class="font-mono-ui text-[11px] text-[#A1A6B4]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="text-[14px] font-semibold tracking-tight">{{ $role['name'] }}</span>
                    </div>
                    <p class="text-[14px] leading-relaxed text-[#4A4F63]">{{ $role['copy'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-[13.5px] text-[#7A8092]">
            Attendance, leave and payroll arrive with the Standard plan.
            <a href="/home#pricing" class="font-medium text-indigo-600 underline-offset-2 hover:underline">Compare plans</a>
        </p>
    </x-marketing.section>

    {{-- ======================= Units in the numbers ==================== --}}
    <x-marketing.section>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="One organisation, many units"
                    heading="See which team is drowning before they say so."
                    lede="Every task belongs to a unit, so throughput, credit spend and storage are already broken out by team. No one has to assemble the number by hand at month end."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Units are the org chart', 'Add a unit for each craft or each client team. A person belongs to one, and the board respects that boundary without anyone policing it.'],
                        ['Throughput per unit', 'What is in flight and what shipped this month, per team — so the quiet queue and the overloaded one are both visible.'],
                        ['Storage per unit', 'Usage totalled for the organisation and broken down by team, so the plan ceiling arrives as a number rather than a failed upload.'],
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
                <x-marketing.window chrome="clarix.app/units">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[420px] text-left">
                            <thead>
                                <tr class="border-b border-black/[.07] bg-[#FAFAFC] font-mono-ui text-[9.5px] uppercase tracking-[.06em] text-[#A1A6B4]">
                                    <th class="px-4 py-2.5 font-medium">Unit</th>
                                    <th class="px-4 py-2.5 text-right font-medium">In flight</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Done</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Credits</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Storage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rollup as $row)
                                    <tr class="border-b border-black/[.05]">
                                        <td class="px-4 py-3 text-[12px] font-medium text-[#0F1222]">{{ $row['unit'] }}</td>
                                        <td class="px-4 py-3 text-right font-mono-ui text-[11px] text-[#4A4F63]">{{ $row['flight'] }}</td>
                                        <td class="px-4 py-3 text-right font-mono-ui text-[11px] text-[#4A4F63]">{{ $row['done'] }}</td>
                                        <td class="px-4 py-3 text-right font-mono-ui text-[11px] text-[#0F1222]">{{ $row['credits'] }}</td>
                                        <td class="px-4 py-3 text-right font-mono-ui text-[11px] text-[#7A8092]">{{ $row['storage'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-[#FAFAFC]">
                                    <td class="px-4 py-3 text-[12px] font-semibold text-[#0F1222]">Organisation</td>
                                    <td class="px-4 py-3 text-right font-mono-ui text-[11px] font-medium text-[#0F1222]">18</td>
                                    <td class="px-4 py-3 text-right font-mono-ui text-[11px] font-medium text-[#0F1222]">57</td>
                                    <td class="px-4 py-3 text-right font-mono-ui text-[11px] font-medium text-[#0F1222]">1,840</td>
                                    <td class="px-4 py-3 text-right font-mono-ui text-[11px] font-medium text-[#0F1222]">41.3 GB</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    {{-- ====================== Many clients at once ===================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.section-head
            eyebrow="Several clients, one board"
            heading="A new client is a filter, not a second install."
            lede="Clients do not each get their own workspace to log into and their own board to keep in sync. They get their own view of the one board your team already works in."
        />

        <div class="mt-14 grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['Their tasks, their thread', 'A client sees the status of the work that is theirs and the comments addressed to them. Everything else on the board stays where it is.', route('marketing.product.portal'), 'Client Portal'],
                ['Their spend, separately', 'Credits are debited against the client the task belongs to, so the September figure for one is never entangled with another.', route('marketing.product.credits'), 'Credits & Billing'],
                ['Their files, on the work', 'Briefs in, deliverables out, attached to the task that produced them rather than to a shared drive with four people\'s naming habits in it.', route('marketing.product.files'), 'File Management'],
            ] as [$label, $copy, $href, $linkLabel])
                <div class="flex flex-col rounded-2xl border border-black/[.07] bg-white p-7 shadow-[0_18px_34px_-24px_rgba(14,17,38,.34)]">
                    <h3 class="text-[15px] font-semibold tracking-tight">{{ $label }}</h3>
                    <p class="mt-2.5 flex-1 text-[14px] leading-relaxed text-[#5B6076]">{{ $copy }}</p>
                    <a href="{{ $href }}"
                       class="mt-5 inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-indigo-600 transition hover:text-indigo-700">
                        {{ $linkLabel }}
                        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                    </a>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-[13.5px] text-[#7A8092]">
            Pro is the plan built for this — multiple teams, multiple clients, and full automation access.
            <a href="/home#pricing" class="font-medium text-indigo-600 underline-offset-2 hover:underline">See what it includes</a>
        </p>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="Run the whole agency on one board."
        lede="Every unit, every client and every credit in one place — with the roles that keep them properly apart."
    />

</x-marketing.layout>
