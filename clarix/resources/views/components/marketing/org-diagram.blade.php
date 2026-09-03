@props([
    'outer' => [],
    'mid' => [],
    'core',
    'sub' => null,
    'outerLabel' => null,
    'midLabel' => null,
])

{{--
    The homepage's platform arc, generalised into an org-shape diagram: filled
    half-donut bands nesting inward to a solid core, with tiles sitting on the
    bands. The three Solutions pages all draw the same picture — many things
    resolving into one organisation — and only the labels change, so the
    geometry lives here rather than three times over.

    It is the homepage's motif at hero scale, not a second idiom: same band
    colours, same white tiles, same pale -> medium -> core progression.

    Nodes are:  ['label' => …, 'icon' => '<path …/>' | 'initials' => 'NW']
    plus an optional 'accent' => true, which tints the tile the way the
    homepage marks its Clarix Agent node.
--}}

@php
    // One 640x340 box with the circle's centre at the bottom edge, dead
    // centre. Unlike the homepage — which scales a fixed-pixel box — the arcs
    // here are an SVG with a viewBox and the tiles are placed in percentages,
    // so the diagram fills whatever column it is handed while the tiles and
    // their labels stay at a constant, readable size.
    $BOX_W = 640;
    $BOX_H = 340;
    $CX = $BOX_W / 2;

    $R1 = 318.0;   // outer edge of the pale band
    $R2 = 228.0;   // pale | medium boundary
    $R3 = 140.0;   // medium | core boundary, and the core's own radius

    // Tile radii sit toward the outer side of their band, so the label — which
    // hangs below the tile — lands on the same band as its icon. The 104 units
    // between the two rows is the load-bearing figure: a tile plus its label is
    // 66px tall and does not shrink with the diagram, so anything tighter has
    // the two apex tiles touching once this is scaled into a narrow column.
    $R_OUTER = 284.0;
    $R_MID   = 180.0;

    // A filled half-donut: outer arc over the top, in along the baseline,
    // inner arc back, closed. Lifted verbatim from the homepage.
    $bandPath = fn (float $ro, float $ri) => sprintf(
        'M%2$s %1$d A%3$s %3$s 0 0 1 %4$s %1$d L%5$s %1$d A%6$s %6$s 0 0 0 %7$s %1$d Z',
        $BOX_H, $CX - $ro, $ro, $CX + $ro, $CX + $ri, $ri, $CX - $ri
    );
    $corePath = sprintf(
        'M%2$s %1$d A%3$s %3$s 0 0 1 %4$s %1$d Z',
        $BOX_H, $CX - $R3, $R3, $CX + $R3
    );

    // Even pitch across the span, apex-centred. A single node sits on the
    // apex rather than at one end of the run.
    $spread = function (int $n, float $from, float $to): array {
        if ($n <= 0) {
            return [];
        }
        if ($n === 1) {
            return [($from + $to) / 2];
        }

        $step = ($to - $from) / ($n - 1);

        return array_map(fn (int $i) => $from + $i * $step, range(0, $n - 1));
    };

    // 'lift' is tile height / 2 + gap + label height, so `bottom` lands the
    // *icon* on the arc rather than the label beneath it. It is a real pixel
    // measurement subtracted from a percentage, which is what keeps the tiles
    // the same size at every width.
    $LIFT = 44;

    $place = function (array $nodes, float $r, float $from, float $to) use ($CX, $BOX_W, $BOX_H, $spread, $LIFT) {
        $degrees = $spread(count($nodes), $from, $to);

        foreach ($nodes as $i => $node) {
            $rad = deg2rad($degrees[$i]);

            $nodes[$i]['left']   = round(($CX + $r * cos($rad)) / $BOX_W * 100, 3);
            $nodes[$i]['bottom'] = round(($r * sin($rad)) / $BOX_H * 100, 3);
            $nodes[$i]['lift']   = $LIFT;
        }

        return $nodes;
    };

    // The outer run reaches nearly to the baseline, which is what buys the
    // end nodes their horizontal clearance from the row below.
    $outerNodes = $place($outer, $R_OUTER, 158, 22);
    $midNodes   = $place($mid, $R_MID, 138, 42);

    // Two diagrams on one page would otherwise collide on a shared gradient id.
    $coreId = uniqid('org-core-');

    $tile = 'flex h-11 w-11 items-center justify-center rounded-[12px] shadow-[0_18px_34px_-16px_rgba(14,17,38,.22),0_3px_10px_-4px_rgba(14,17,38,.10)]';
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>

    {{-- Arc layout, md and up. --}}
    <div class="relative mx-auto hidden w-full md:block" style="aspect-ratio: {{ $BOX_W }} / {{ $BOX_H }}">

        {{-- the filled bands --}}
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 {{ $BOX_W }} {{ $BOX_H }}" fill="none" aria-hidden="true">
            <defs>
                <linearGradient id="{{ $coreId }}" x1="0" y1="{{ $BOX_H - $R3 }}" x2="0" y2="{{ $BOX_H }}" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#6D64EA"/>
                    <stop offset="1" stop-color="#4F46E5"/>
                </linearGradient>
            </defs>
            <path d="{{ $bandPath($R1, $R2) }}" fill="#EDEAFB"/>
            <path d="{{ $bandPath($R2, $R3) }}" fill="#BEB6F2"/>
            <path d="{{ $corePath }}" fill="url(#{{ $coreId }})"/>
        </svg>

        {{-- tiles and labels, over the bands --}}
        <div class="pointer-events-none absolute inset-0">
            @foreach ($outerNodes as $node)
                <div class="absolute flex -translate-x-1/2 flex-col items-center"
                     style="left: {{ $node['left'] }}%; bottom: calc({{ $node['bottom'] }}% - {{ $node['lift'] }}px)">
                    <span class="{{ $tile }} {{ ($node['accent'] ?? false) ? 'bg-white ring-1 ring-indigo-200' : 'bg-white ring-1 ring-black/[.07]' }}">
                        <x-marketing.org-glyph :node="$node" />
                    </span>
                    <span class="mt-1.5 whitespace-nowrap text-[11.5px] font-medium text-[#4A4F63]">{{ $node['label'] }}</span>
                </div>
            @endforeach

            @foreach ($midNodes as $node)
                <div class="absolute flex -translate-x-1/2 flex-col items-center"
                     style="left: {{ $node['left'] }}%; bottom: calc({{ $node['bottom'] }}% - {{ $node['lift'] }}px)">
                    <span class="{{ $tile }} {{ ($node['accent'] ?? false) ? 'bg-white ring-1 ring-indigo-200' : 'bg-white' }}">
                        <x-marketing.org-glyph :node="$node" />
                    </span>
                    <span class="mt-1.5 whitespace-nowrap text-[11px] font-semibold text-[#37307A]">{{ $node['label'] }}</span>
                </div>
            @endforeach

            {{-- The core's own label, sitting on the solid fill. Its width is a
                 percentage of the diagram rather than a fixed measure, because the
                 core it has to stay inside shrinks with the diagram — 42% tracks
                 the chord at this height closely enough that the text never
                 spills onto the band above. --}}
            <div class="absolute bottom-[22px] left-1/2 w-[42%] -translate-x-1/2 text-center">
                <div class="text-[12.5px] font-semibold text-white">{{ $core }}</div>
                @if ($sub)
                    <div class="mt-1 text-[10.5px] leading-snug text-white/70">{{ $sub }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Stacked below md: the same pale -> medium -> core nesting, squared
         off, because arcs do not survive a phone's width. --}}
    <div class="md:hidden">
        <div class="mx-auto max-w-sm rounded-t-[28px] bg-[#EDEAFB] px-3 pt-6">

            @if ($outerLabel)
                <span class="block pb-3 text-center text-[10px] font-semibold uppercase tracking-[.1em] text-[#6B63A8]">{{ $outerLabel }}</span>
            @endif

            <div class="flex flex-wrap justify-center gap-x-4 gap-y-3">
                @foreach ($outer as $node)
                    <div class="flex w-[72px] flex-col items-center">
                        <span class="{{ $tile }} bg-white ring-1 {{ ($node['accent'] ?? false) ? 'ring-indigo-200' : 'ring-black/[.07]' }}">
                            <x-marketing.org-glyph :node="$node" />
                        </span>
                        <span class="mt-1.5 text-center text-[10px] font-medium leading-tight text-[#4A4F63]">{{ $node['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 rounded-t-[24px] bg-[#BEB6F2] px-3 pt-5">

                @if ($mid)
                    @if ($midLabel)
                        <span class="block pb-3 text-center text-[10px] font-semibold uppercase tracking-[.1em] text-[#3B3480]">{{ $midLabel }}</span>
                    @endif

                    <div class="flex flex-wrap justify-center gap-x-4 gap-y-3">
                        @foreach ($mid as $node)
                            <div class="flex w-[80px] flex-col items-center">
                                <span class="{{ $tile }} bg-white {{ ($node['accent'] ?? false) ? 'ring-1 ring-indigo-200' : '' }}">
                                    <x-marketing.org-glyph :node="$node" />
                                </span>
                                <span class="mt-1.5 text-center text-[10px] font-semibold leading-tight text-[#37307A]">{{ $node['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 rounded-t-[20px] bg-[#4F46E5] px-4 pb-9 pt-6 text-center">
                    <div class="text-[12.5px] font-semibold text-white">{{ $core }}</div>
                    @if ($sub)
                        <div class="mt-1 text-[10.5px] leading-snug text-white/70">{{ $sub }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
