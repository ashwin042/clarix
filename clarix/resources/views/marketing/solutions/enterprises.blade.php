@php
    // The same diagram again, at department scale. The middle band is where
    // the ERP surfaces get named: Attendance, Leave and Payroll are the three
    // the product actually ships, and they are what separates an enterprise
    // rollout from a team buying a task board.
    $departments = [
        ['label' => 'Marketing', 'icon' => '<path d="M3 8.5h3l6-4v11l-6-4H3v-3Z"/><path d="M14.5 8v4"/>'],
        ['label' => 'Creative',  'icon' => '<path d="M3 17l3-1 9-9a2 2 0 0 0-3-3l-9 9-1 3Z"/><path d="m12 5 3 3"/>'],
        ['label' => 'Product',   'icon' => '<path d="m10 2.5 7 3.5v8l-7 3.5-7-3.5V6l7-3.5Z"/><path d="M3 6l7 3.5L17 6M10 9.5v8"/>'],
        ['label' => 'Finance',   'icon' => '<path d="M3 16.5h14"/><path d="M5.5 16.5V9M9.5 16.5V4.5M13.5 16.5v-5"/>'],
        ['label' => 'People',    'icon' => '<circle cx="8" cy="7.5" r="2.6"/><path d="M3 16.5a5 5 0 0 1 10 0"/><path d="M13.5 5.4a2.6 2.6 0 0 1 0 4.9M14.5 12.4a4.6 4.6 0 0 1 2.6 4.1"/>'],
    ];

    $erp = [
        ['label' => 'Attendance', 'icon' => '<circle cx="10" cy="10" r="7.5"/><path d="M10 5.5V10l3 2"/>'],
        ['label' => 'Leave',      'icon' => '<rect x="3" y="4.5" width="14" height="12" rx="2"/><path d="M3 8.5h14M7 3v3M13 3v3"/>'],
        ['label' => 'Payroll',    'icon' => '<rect x="2.5" y="5" width="15" height="10" rx="2"/><path d="M2.5 8.5h15"/><circle cx="13.5" cy="11.8" r="1"/>'],
    ];

    // Roles as columns, the capabilities that actually differ as rows. true is
    // a check, false a dash — the same convention the pricing comparison uses.
    $matrix = [
        ['Every unit\'s board',      [true,  true,  false, false, false]],
        ['Brief and cost a task',    [true,  true,  true,  false, false]],
        ['Assign work',              [true,  true,  true,  false, false]],
        ['Credit ledger and export', [true,  true,  false, false, false]],
        ['Attendance and payroll',   [true,  false, false, false, true]],
    ];

    $matrixRoles = ['Admin', 'PM', 'Supervisor', 'Writer', 'HR'];

    // Straight off the Enterprise card on the pricing table.
    $enterprise = [
        'Dedicated account manager',
        'Custom integrations & API access',
        'SLA-backed uptime guarantee',
        'Onboarding & migration support',
        'Storage sized with you during onboarding',
    ];
@endphp

<x-marketing.layout
    title="For Enterprises — Clarix"
    description="Scale delivery across departments: units per team, roles and permissions that hold at group level, an attendance, leave and payroll layer, and an audit trail on every task."
>

    {{-- ============================== Hero ============================= --}}
    <main class="relative overflow-hidden">

        <div aria-hidden="true"
             class="pointer-events-none absolute inset-x-[-20%] -top-64 h-[620px] scene-glow opacity-70 lg:inset-x-0"></div>

        <div class="relative z-10 mx-auto max-w-7xl px-6 pb-16 pt-12 sm:pt-16 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14">

                <x-marketing.page-hero
                    eyebrow="For Enterprises"
                    heading="Scale delivery across departments."
                    lede="Marketing briefs it, Creative makes it, Finance pays for it and People staff it. Clarix holds all four on one board, with the permissions to keep each department in its own lane and a record of everything that moved."
                />

                <div class="rise rise-4">
                    <x-marketing.org-diagram
                        :outer="$departments"
                        :mid="$erp"
                        outer-label="Departments"
                        mid-label="ERP"
                        core="Group operations"
                        sub="Roles, permissions and an audit trail"
                    />
                </div>
            </div>
        </div>
    </main>

    {{-- ============================== ERP ============================== --}}
    <x-marketing.section surface="soft" divider>
        <x-marketing.split>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="The people side"
                    heading="Delivery and the people delivering it, in one system."
                    lede="Attendance, leave and payroll are part of Clarix, not a second tool that has to be reconciled with it. The person on the task and the person on the roster are the same record."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Attendance', 'Daily check-in and check-out per person, rolled up by unit, so capacity is a number rather than a guess about who is around.'],
                        ['Leave', 'Requests against leave types you define, approved up the chain — and visible on the board, because a week off is why a task did not move.'],
                        ['Payroll', 'Payroll records held against the same people and units the work is assigned to, so a department\'s cost and its output sit in one place.'],
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

                <p class="mt-7 text-[13.5px] text-[#7A8092]">
                    Full ERP access arrives with the Standard plan, alongside Gantt charts and the AI chatbot.
                </p>
            </x-slot:text>

            <x-slot:visual>
                <x-marketing.window chrome="clarix.app/leave">
                    <div class="p-4">
                        <div class="flex items-center justify-between pb-3">
                            <span class="text-[12px] font-semibold">Leave requests</span>
                            <span class="rounded-md border border-black/[.07] px-2 py-0.5 font-mono-ui text-[9.5px] text-[#5B6076]">September</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ([
                                ['AK', 'Aman K.',  'Creative',  'Annual · 12–16 Sep', 'Approved', 'bg-[#ECFDF3] text-[#0E7A55]'],
                                ['PR', 'Priya R.', 'Marketing', 'Sick · 9 Sep',       'Approved', 'bg-[#ECFDF3] text-[#0E7A55]'],
                                ['DS', 'Dev S.',   'Product',   'Annual · 22–26 Sep', 'Pending',  'bg-[#FFF4E8] text-[#B45309]'],
                            ] as [$mark, $name, $unit, $detail, $state, $tint])
                                <div class="flex items-center gap-3 rounded-lg border border-black/[.06] px-3 py-2.5">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF0FF] font-mono-ui text-[9px] font-medium text-indigo-700">{{ $mark }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="truncate text-[12px] font-medium">{{ $name }}</span>
                                            <span class="shrink-0 font-mono-ui text-[9px] text-[#A1A6B4]">{{ $unit }}</span>
                                        </div>
                                        <span class="block truncate font-mono-ui text-[9.5px] text-[#A1A6B4]">{{ $detail }}</span>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[9.5px] font-semibold {{ $tint }}">{{ $state }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3.5 grid grid-cols-3 gap-3 border-t border-black/[.06] pt-3.5">
                            @foreach ([['Present today', '48'], ['On leave', '3'], ['Units', '5']] as [$k, $v])
                                <div>
                                    <dt class="text-[9.5px] font-medium uppercase tracking-[.08em] text-[#A1A6B4]">{{ $k }}</dt>
                                    <dd class="mt-1 font-mono-ui text-[14px] font-medium">{{ $v }}</dd>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    {{-- =========================== Governance ========================== --}}
    <x-marketing.section>
        <x-marketing.split reverse>
            <x-slot:text>
                <x-marketing.section-head
                    eyebrow="Governance"
                    heading="Permissions you set, and a record you can hand to an auditor."
                    lede="At department scale the question stops being what the tool can do and becomes who is allowed to do it. Roles carry permissions, permissions are yours to adjust, and every task keeps its own history."
                    align="left"
                />

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Permissions per role', 'The default grants are sensible; an admin can tighten or widen them for the organisation without waiting on us.'],
                        ['A boundary per unit', 'A supervisor sees their department. Not the next one\'s board, not the next one\'s spend, not by accident.'],
                        ['An activity log per task', 'Every status change, assignment, note and file is recorded against the task, so what happened in March is still answerable in September.'],
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
                <x-marketing.window chrome="clarix.app/admin/authorization">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[420px] text-left">
                            <thead>
                                <tr class="border-b border-black/[.07] bg-[#FAFAFC] font-mono-ui text-[9.5px] uppercase tracking-[.06em] text-[#A1A6B4]">
                                    <th class="px-4 py-2.5 font-medium">Capability</th>
                                    @foreach ($matrixRoles as $role)
                                        <th class="px-2 py-2.5 text-center font-medium">{{ $role }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($matrix as [$capability, $grants])
                                    <tr class="border-b border-black/[.05] last:border-b-0">
                                        <td class="px-4 py-3 text-[11.5px] font-medium text-[#0F1222]">{{ $capability }}</td>
                                        @foreach ($grants as $granted)
                                            <td class="px-2 py-3 text-center">
                                                @if ($granted)
                                                    <svg class="mx-auto h-3.5 w-3.5 text-[#0E7A55]" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-6"/></svg>
                                                    <span class="sr-only">Granted</span>
                                                @else
                                                    <span class="font-mono-ui text-[11px] text-[#C8CCD6]" aria-hidden="true">—</span>
                                                    <span class="sr-only">Not granted</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-marketing.window>
            </x-slot:visual>
        </x-marketing.split>
    </x-marketing.section>

    {{-- ====================== The Enterprise plan ====================== --}}
    <x-marketing.section surface="soft" divider width="max-w-4xl">
        <div class="rounded-3xl border border-black/[.07] bg-[#0F1222] px-8 py-12 sm:px-14">
            <span class="block text-[12.5px] font-semibold uppercase tracking-[.08em] text-white/60">Enterprise</span>

            <h2 class="mt-4 font-display text-[30px] font-normal leading-[1.1] tracking-[-0.02em] text-white sm:text-[38px]">
                Priced and sized with you, not off a list.
            </h2>

            <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-white/70">
                Everything on the Pro plan, plus the things a rollout across departments
                actually needs. There is no list price, because the shape of the thing is
                decided in the conversation.
            </p>

            <ul class="mt-9 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                @foreach ($enterprise as $feature)
                    <li class="flex items-start gap-3">
                        <span class="mt-[3px] flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-white/[.13]">
                            <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-6"/></svg>
                        </span>
                        <span class="text-[14px] leading-relaxed text-white/85">{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="/home#schedule-demo"
                   class="inline-flex w-full items-center justify-center rounded-full bg-white px-6 py-3 text-[15px] font-semibold text-[#0F1222] transition hover:bg-white/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto">
                    Talk to sales
                </a>
                <a href="/home#pricing"
                   class="inline-flex w-full items-center justify-center rounded-full border border-white/35 px-6 py-3 text-[15px] font-semibold text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-auto">
                    Compare plans
                </a>
            </div>
        </div>
    </x-marketing.section>

    <x-marketing.cta-band
        heading="One board, however many departments."
        lede="Units for each team, permissions that hold, and a record of every change — from the first brief to the payroll run."
    />

</x-marketing.layout>
