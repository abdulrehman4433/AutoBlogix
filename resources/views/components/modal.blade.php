@props([
    'name',
    'title' => null,
    'maxWidth' => 'md',
    'show' => false,
    'focusable' => false,
])

@php
    $maxWidths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-2xl',
        'xl' => 'sm:max-w-4xl',
    ];
@endphp

<div
    x-data="{ open: {{ $show ? 'true' : 'false' }} }"
    @open-modal.window="open = ($event.detail === '{{ $name }}')"
    @close-modal.window="open = false"
    @keydown.escape.window="open = false"
    @if ($focusable)
        x-effect="if (open) $nextTick(() => ($el.querySelector('input, select, textarea') ?? $el.querySelector('footer button, button'))?.focus())"
    @endif
>
    <div
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-gray-900/60 backdrop-blur-sm"
        aria-hidden="true"
        @click="open = false"
    ></div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto p-4 sm:items-center sm:p-0"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $title ?? ucfirst(str_replace('-', ' ', $name)) }}"
    >
        <div
            @click.outside="open = false"
            {{ $attributes->class(['w-full rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 ' . ($maxWidths[$maxWidth] ?? $maxWidths['md'])]) }}
        >
            @if ($title)
                <div class="flex items-start justify-between border-b border-line px-5 py-4">
                    <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
                    <button
                        type="button"
                        class="-m-1.5 rounded-lg p-1.5 text-ink-faint transition hover:bg-black/5 hover:text-ink focus:outline-none focus:ring-2 focus:ring-brand-500"
                        aria-label="Close"
                        @click="open = false"
                    >
                        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                        </svg>
                    </button>
                </div>
            @endif

            <div class="px-5 py-4">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex justify-end gap-3 border-t border-line bg-surface-muted px-5 py-3.5 rounded-b-2xl">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
