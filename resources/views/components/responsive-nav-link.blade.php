@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-lg px-3 py-2 text-start text-sm font-semibold text-brand-700 bg-brand-50 transition duration-150'
            : 'block w-full rounded-lg px-3 py-2 text-start text-sm font-medium text-gray-600 transition duration-150 hover:bg-gray-100 hover:text-gray-900';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
