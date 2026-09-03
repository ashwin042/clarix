@props([
    'chrome' => null,
    'dark' => false,
    'tilt' => null,
    'float' => false,
])

{{--
    The mockup frame the whole site draws in: traffic lights, a chrome bar, and
    the deep window shadow. Every screenshot-style visual on the marketing site
    goes inside one of these rather than being a bare card, which is what keeps
    a new page looking like the homepage.

    `tilt` takes one of the shell's pure-rotation transforms — terminal, thread
    or phone. Not `board`: that one carries a translateX(-50%) that only makes
    sense for the absolutely-positioned hero on the homepage.
--}}

@php
    $tiltClass = in_array($tilt, ['terminal', 'thread', 'phone'], true) ? "tilt-{$tilt}" : '';

    $frame = $dark
        ? 'border-white/[.06] bg-[#12141F] win-shadow-dark'
        : 'border-black/[.07] bg-white win-shadow';

    $bar = $dark
        ? 'border-white/[.07] bg-[#191C29]'
        : 'border-black/[.06] bg-[#F7F7F9]';
@endphp

<div @class(['float' => $float, 'w-full' => true])>
    <div {{ $attributes->merge(['class' => trim("overflow-hidden rounded-xl border {$frame} {$tiltClass}")]) }}>

        <div class="flex items-center gap-3 border-b {{ $bar }} px-3.5 py-2.5">
            <div class="flex gap-1.5">
                <span class="h-[10px] w-[10px] rounded-full bg-[#FF5F57]"></span>
                <span class="h-[10px] w-[10px] rounded-full bg-[#FEBC2E]"></span>
                <span class="h-[10px] w-[10px] rounded-full bg-[#28C840]"></span>
            </div>

            @if ($chrome)
                @if ($dark)
                    <div class="mx-auto font-mono-ui text-[10px] text-[#8A90A6]">{{ $chrome }}</div>
                @else
                    <div class="mx-auto rounded-md bg-white px-3 py-[3px] font-mono-ui text-[10px] text-[#8A8F9E] ring-1 ring-black/[.05]">
                        {{ $chrome }}
                    </div>
                @endif
            @endif
        </div>

        {{ $slot }}
    </div>
</div>
