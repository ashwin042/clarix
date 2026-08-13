{{-- The AXOKAI avatar: a sparkle in a gradient badge, matching the icon
     badges across the AI & Automation section. Used beside every assistant
     reply and by the typing indicator, so it lives here rather than being
     pasted twice and drifting.

     The sparkle has concave points on purpose — a straight-edged star reads
     as a plus sign at this size, which collides with the attach button in
     the composer below. --}}
<span {{ $attributes->merge(['class' => 'mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-600 shadow-[0_2px_10px_-2px_rgba(79,70,229,0.55)] ring-1 ring-inset ring-white/20']) }}>
    <svg class="h-[18px] w-[18px] text-white" viewBox="0 -3.5 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 2.5c.3 3.4 2.6 5.7 6 6-3.4.3-5.7 2.6-6 6-.3-3.4-2.6-5.7-6-6 3.4-.3 5.7-2.6 6-6Z"/>
    </svg>
</span>
