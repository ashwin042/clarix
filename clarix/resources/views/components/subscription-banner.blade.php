{{--
    Soft warning shown while the organization is inside its grace period.

    Nothing is blocked at this point — that is what the grace period is for —
    so this is the only thing that tells an agency their renewal has slipped
    before the day access stops. Renders nothing at all in every other state,
    including suspended, which gets its own page instead of the application.

    Usage: drop once in the org-facing layout.
--}}
@php
    $bannerSubscription = null;

    // Superadmins have no organization, and the query is scoped to the
    // viewer's own, so this reads their subscription and nobody else's.
    if (auth()->check() && ! auth()->user()->isSuperadmin()) {
        $bannerSubscription = \App\Models\OrganizationSubscription::query()
            ->where('status', 'past_due')
            ->latest('started_at')
            ->first();
    }
@endphp

@if($bannerSubscription)
    <div class="bg-amber-50 dark:bg-amber-500/10 border-b border-amber-200 dark:border-amber-500/25 px-4 py-2.5">
        <div class="flex items-center justify-center gap-2 text-center flex-wrap">
            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            <span class="text-sm text-amber-800 dark:text-amber-300">
                Your subscription renewal is overdue.
                @if($bannerSubscription->graceEndsAt())
                    Access will be suspended after
                    <span class="font-semibold">{{ $bannerSubscription->graceEndsAt()->format('d M Y') }}</span>.
                @endif
                Please contact support to renew.
            </span>
        </div>
    </div>
@endif
