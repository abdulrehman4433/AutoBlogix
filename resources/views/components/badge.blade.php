@props([
    'variant' => 'gray',
    'dot' => true,
])

@php
    $variants = [
        'gray' => 'badge-neutral',
        'green' => 'badge-success',
        'red' => 'badge-danger',
        'amber' => 'badge-warning',
        'blue' => 'badge-info',
        'indigo' => 'badge-brand',
    ];
@endphp

<span {{ $attributes->class([
    'badge',
    $variants[$variant] ?? $variants['gray'],
    'badge-plain' => ! $dot,
]) }}>
    {{ $slot }}
</span>
