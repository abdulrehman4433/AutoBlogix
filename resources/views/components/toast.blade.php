@props([
    'type' => 'success',
    'message' => null,
    'duration' => 3000,
])

@php
    $icons = [
        'success' => ['check-circle', 'text-success'],
        'error' => ['exclamation-circle', 'text-danger'],
        'warning' => ['exclamation-triangle', 'text-warning'],
        'info' => ['information-circle', 'text-info'],
    ];
    [$icon, $iconClasses] = $icons[$type] ?? $icons['success'];
    $hasMessage = $message !== null && $message !== '';
@endphp

@if ($hasMessage || (string) $slot !== '')
    <div
        {{ $attributes->class(['pointer-events-none fixed end-4 top-4 z-50 w-full max-w-sm sm:w-auto']) }}
        x-data="{ show: true }"
        x-init="setTimeout(() => { show = false }, {{ max(1000, (int) $duration) }})"
        x-cloak
    >
        <div
            class="toast pointer-events-auto flex w-full items-start gap-3"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-6"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-6"
            role="status"
            aria-live="polite"
        >
            <span class="mt-0.5 shrink-0 {{ $iconClasses }}">
                <x-icon :name="$icon" />
            </span>

            <div class="min-w-0 flex-1 pt-0.5 text-sm text-ink">
                @if ($message !== null && $message !== '')
                    {{ $message }}
                @else
                    {{ $slot }}
                @endif
            </div>

            <button
                type="button"
                class="-m-1 shrink-0 rounded-lg p-1 text-ink-faint transition hover:bg-black/5 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                aria-label="Dismiss"
                @click="show = false"
            >
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
    </div>
@endif
