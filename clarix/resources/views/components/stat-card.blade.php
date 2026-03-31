@props(['label', 'value', 'color' => 'indigo', 'icon' => null, 'change' => null])

@php
    $colorClasses = [
        'indigo' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'dot' => 'bg-indigo-500'],
        'blue'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-600',   'dot' => 'bg-blue-500'],
        'green'  => ['bg' => 'bg-green-50',  'text' => 'text-green-600',  'dot' => 'bg-green-500'],
        'amber'  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600',  'dot' => 'bg-amber-500'],
        'rose'   => ['bg' => 'bg-rose-50',   'text' => 'text-rose-600',   'dot' => 'bg-rose-500'],
    ];
    $c = $colorClasses[$color] ?? $colorClasses['indigo'];
@endphp

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
        <div class="w-8 h-8 {{ $c['bg'] }} rounded-lg flex items-center justify-center">
            <div class="w-2.5 h-2.5 rounded-full {{ $c['dot'] }}"></div>
        </div>
    </div>
    <p class="mt-3 text-2xl font-bold text-gray-900">{{ $value }}</p>
    @if ($change)
        <p class="mt-1 text-xs text-gray-400">{{ $change }}</p>
    @endif
</div>
