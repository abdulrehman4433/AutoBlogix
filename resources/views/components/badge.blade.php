@props([
    'variant' => 'gray',
])

@php
    $variants = [
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-600/10',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'red' => 'bg-red-50 text-red-700 ring-red-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'blue' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
    $variants[$variant] ?? $variants['gray'],
]) }}>
    {{ $slot }}
</span>
