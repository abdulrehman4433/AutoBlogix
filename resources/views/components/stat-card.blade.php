@props([
    'label',
    'value' => null,
    'icon' => null,
    'tone' => 'brand',
    'hint' => null,
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'gradient' => 'bg-brand-gradient text-white shadow-glow',
        'success' => 'bg-success-soft text-success-strong',
        'warning' => 'bg-warning-soft text-warning-strong',
        'danger' => 'bg-danger-soft text-danger-strong',
        'info' => 'bg-info-soft text-info-strong',
        'neutral' => 'bg-surface-muted text-ink-muted',
    ];
    $toneClasses = $tones[$tone] ?? $tones['brand'];
@endphp

<div {{ $attributes->class(['card p-5']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-ink-muted">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-ink tabular-nums">
                @if ($value !== null)
                    {{ $value }}
                @else
                    {{ $slot }}
                @endif
            </p>
            @if ($hint)
                <p class="mt-1.5 text-xs font-medium text-ink-faint">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $toneClasses }}">
                <x-icon :name="$icon" />
            </span>
        @endif
    </div>
</div>
