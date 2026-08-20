@props(['status'])

@php
    $classes = match ($status) {
        'present'  => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'absent'   => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'half_day' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'on_leave' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
        default    => 'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex px-2.5 py-1 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ \App\Models\Attendance::STATUSES[$status] ?? $status }}
</span>
