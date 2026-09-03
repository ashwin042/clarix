@props([
    'items' => [],
    'open' => 0,
    'idPrefix' => 'faq',
    'variant' => 'stack',
])

{{--
    The homepage's "Why Clarix" accordion, generalised over a passed array.

    Single-open: one x-data for the whole list, so opening a panel closes the
    one before it by construction. The panel named by `open` ships open from
    the server as well as from Alpine, so it does not pop into place once
    Alpine boots — which is why the aria attributes are written twice, once as
    a plain attribute and once as an Alpine binding.

    Height animates on grid-template-rows (see .acc-panel in the layout), so a
    panel opens to its real height with no guessed max-height ceiling.

    Two presentations of the same disclosure, chosen with `variant`:

      stack — the original. Each question is a full-width row with a chevron
              and its answer directly beneath it. Used by the Help Center.

      pills — the triggers are a wrapped row of pills and every panel sits
              below the whole row, so a card keeps its compact tag strip and
              still opens in place. Used by the Documentation index.

    Both are disclosures, not tabs: nothing has to be open, and the trigger
    carries aria-expanded plus aria-controls onto the panel it owns. Tabs
    would promise arrow-key navigation and a permanently-selected item, and
    neither is true here.

    Items are ['q' => …] plus at least one of:
      'a'     => a paragraph of prose
      'steps' => an ordered list of instructions
    A stack item that carries only 'a' is the shape this component started at,
    so the Help Center's arrays did not have to change.

    Pass `open` => false for a list that starts fully closed — not null, which
    Blade replaces with the prop default on its way in, quietly reopening the
    first panel.
--}}

@php
    // false (or anything that is not an index) means no panel starts open.
    $openIndex = is_int($open) ? $open : null;

    $isPills = $variant === 'pills';

    // The stack owns its dividers; the pill row sits inside a card that has
    // its own edges already, so it brings none.
    $wrapperClass = $isPills ? '' : 'divide-y divide-black/[.08] border-y border-black/[.08]';
@endphp

<div x-data="{ open: {{ $openIndex === null ? 'null' : $openIndex }} }"
     {{ $attributes->merge(['class' => $wrapperClass]) }}>

    @if ($isPills)

        {{-- Triggers first, as one wrapped row, then every panel beneath it.
             Keeping the panels out of the list is what lets an opened topic
             span the full width of the card instead of being trapped in the
             column its pill happened to wrap into. --}}
        <ul class="flex flex-wrap gap-2">
            @foreach ($items as $i => $item)
                @php $isOpen = $openIndex === $i; @endphp

                <li>
                    <button type="button"
                            @click="open = open === {{ $i }} ? null : {{ $i }}"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            :aria-expanded="open === {{ $i }}"
                            aria-controls="{{ $idPrefix }}-panel-{{ $i }}"
                            @class([
                                'flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1 text-left font-mono-ui text-[10.5px] leading-snug transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                                'border-indigo-300 bg-[#EEF0FF] text-indigo-700' => $isOpen,
                                'border-[#DCD6C6] bg-white text-[#5B6076] hover:border-[#C8C2AF] hover:text-[#17143A]' => ! $isOpen,
                            ])
                            :class="open === {{ $i }}
                                ? 'border-indigo-300 bg-[#EEF0FF] text-indigo-700'
                                : 'border-[#DCD6C6] bg-white text-[#5B6076] hover:border-[#C8C2AF] hover:text-[#17143A]'">
                        <span class="min-w-0">{{ $item['q'] }}</span>

                        <svg @class(['acc-chev h-2.5 w-2.5 shrink-0 opacity-60', 'acc-chev--open' => $isOpen])
                             :class="{ 'acc-chev--open': open === {{ $i }} }"
                             viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 6.25 8 10.25 12 6.25"/>
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>

        @foreach ($items as $i => $item)
            @php $isOpen = $openIndex === $i; @endphp

            <div id="{{ $idPrefix }}-panel-{{ $i }}"
                 @class(['acc-panel', 'acc-panel--open' => $isOpen])
                 :class="{ 'acc-panel--open': open === {{ $i }} }"
                 aria-hidden="{{ $isOpen ? 'false' : 'true' }}"
                 :aria-hidden="open !== {{ $i }}">
                <div>
                    {{-- The margin lives inside the clipped element, so a
                         closed panel contributes no height at all — a margin
                         on the grid child would survive the collapse and
                         leave a gap under the pill row. --}}
                    <div class="mt-5 rounded-xl border border-[#E2DCCC] bg-white p-4 sm:p-5">
                        <h4 class="text-[13px] font-semibold tracking-tight text-[#17143A]">{{ $item['q'] }}</h4>

                        <x-marketing.faq-body :item="$item" class="mt-2.5" />
                    </div>
                </div>
            </div>
        @endforeach

    @else

        @foreach ($items as $i => $item)
            @php $isOpen = $openIndex === $i; @endphp

            <div>
                <h3>
                    <button type="button"
                            @click="open = open === {{ $i }} ? null : {{ $i }}"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            :aria-expanded="open === {{ $i }}"
                            aria-controls="{{ $idPrefix }}-panel-{{ $i }}"
                            class="flex w-full items-center justify-between gap-6 py-5 text-left text-[15.5px] font-semibold text-[#17143A] transition-colors hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ $item['q'] }}

                        {{-- Object form, not "expr && 'class'": a panel can ship
                             open from the server, and only the object form removes
                             a class Alpine did not add itself. --}}
                        <svg @class(['acc-chev h-4 w-4 shrink-0 text-[#7A8092]', 'acc-chev--open' => $isOpen])
                             :class="{ 'acc-chev--open': open === {{ $i }} }"
                             viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 6.25 8 10.25 12 6.25"/>
                        </svg>
                    </button>
                </h3>

                <div id="{{ $idPrefix }}-panel-{{ $i }}"
                     @class(['acc-panel', 'acc-panel--open' => $isOpen])
                     :class="{ 'acc-panel--open': open === {{ $i }} }"
                     aria-hidden="{{ $isOpen ? 'false' : 'true' }}"
                     :aria-hidden="open !== {{ $i }}">
                    <div>
                        <x-marketing.faq-body :item="$item" class="pb-6 pr-4 sm:pr-10" />
                    </div>
                </div>
            </div>
        @endforeach

    @endif
</div>
