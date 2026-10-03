@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $types = [
        'success' => 'border-success/30 bg-success-soft text-success-strong',
        'error' => 'border-danger/30 bg-danger-soft text-danger-strong',
        'warning' => 'border-warning/40 bg-warning-soft text-warning-strong',
        'info' => 'border-info/30 bg-info-soft text-info-strong',
    ];
    $classes = $types[$type] ?? $types['info'];
    $icon = [
        'success' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'error' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'warning' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
        'info' => 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
    ][$type] ?? 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z';
@endphp

<div
    {{ $attributes->class(['rounded-xl border p-4 shadow-xs', $classes]) }}
    role="alert"
    x-data="{ show: true }"
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-1"
>
    <div class="flex">
        <div class="shrink-0">
            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="{{ $icon }}" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            @if ($title)
                <p class="text-sm font-semibold">{{ $title }}</p>
            @endif
            <div class="text-sm {{ $title ? 'mt-1' : '' }}">
                {{ $slot }}
            </div>
        </div>
        @if ($dismissible)
            <div class="ml-auto pl-3">
                <button
                    type="button"
                    class="-m-1.5 inline-flex rounded-lg p-1.5 hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-current"
                    aria-label="Dismiss"
                    @click="show = false"
                >
                    <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </div>
        @endif
    </div>
</div>
