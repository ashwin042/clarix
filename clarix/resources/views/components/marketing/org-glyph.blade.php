@props(['node'])

{{--
    What goes inside an org-diagram tile. A node names either an 'icon' — raw
    inner SVG markup on the 20x20 stroked grid the rest of the site draws on —
    or 'initials', which is how a client stands in for itself when no icon
    would say anything a two-letter mark does not.
--}}

@if ($node['initials'] ?? null)
    <span class="text-[12px] font-semibold tracking-tight text-[#4A4F63]">{{ $node['initials'] }}</span>
@else
    <svg class="h-[18px] w-[18px] {{ ($node['accent'] ?? false) ? 'text-indigo-600' : 'text-[#4A4F63]' }}"
         viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {!! $node['icon'] !!}
    </svg>
@endif
