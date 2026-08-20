@props(['status'])

@php
    $classes = match ($status) {
        'paid'      => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'finalized' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
        'draft'     => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        default     => 'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex px-2.5 py-1 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ \App\Models\PayrollRecord::STATUSES[$status] ?? $status }}
</span>
