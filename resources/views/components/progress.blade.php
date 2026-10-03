@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
    'showValue' => false,
])

@php
    $max = max(1, (int) $max);
    $value = min($max, max(0, (int) $value));
    $percent = (int) round(($value / $max) * 100);
@endphp

<div {{ $attributes }}>
    @if ($label !== null || $showValue)
        <div class="mb-1.5 flex items-center justify-between gap-3 text-xs font-medium text-ink-muted">
            @if ($label !== null)
                <span>{{ $label }}</span>
            @endif
            @if ($showValue)
                <span class="tabular-nums text-ink">{{ $percent }}%</span>
            @endif
        </div>
    @endif

    <div
        class="progress"
        role="progressbar"
        aria-valuenow="{{ $value }}"
        aria-valuemin="0"
        aria-valuemax="{{ $max }}"
        @if ($label !== null) aria-label="{{ $label }}" @endif
    >
        <div class="progress-bar" style="width: {{ $percent }}%"></div>
    </div>
</div>
