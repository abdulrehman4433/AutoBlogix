@props([
    'variant' => 'text',
    'lines' => 1,
    'width' => null,
    'height' => null,
])

@php
    $style = collect([
        $width !== null ? "width: {$width}" : null,
        $height !== null ? "height: {$height}" : null,
    ])->filter()->implode('; ');
@endphp

@if ($variant === 'circle')
    <div {{ $attributes->class(['skeleton rounded-full']) }} style="{{ $style ?: 'width: 2.5rem; height: 2.5rem' }}"></div>
@elseif ($variant === 'title')
    <div {{ $attributes->class(['skeleton h-6']) }} style="{{ $style ?: 'width: 60%' }}"></div>
@elseif ($variant === 'block')
    <div {{ $attributes->class(['skeleton']) }} style="{{ $style ?: 'height: 8rem' }}"></div>
@else
    <div {{ $attributes->class(['space-y-3']) }}>
        @for ($i = 0; $i < max(1, (int) $lines); $i++)
            <div class="skeleton skeleton-text"></div>
        @endfor
    </div>
@endif
