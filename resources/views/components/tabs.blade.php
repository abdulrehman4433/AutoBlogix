@props([
    'tabs' => [],
    'default' => null,
])

@php
    $default = $default ?? array_key_first($tabs);
@endphp

<div x-data="{ tab: {{ \Illuminate\Support\Js::from($default) }} }" {{ $attributes }}>
    <div
        role="tablist"
        class="inline-flex flex-wrap items-center gap-1 rounded-xl border border-line bg-surface-muted p-1"
    >
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                role="tab"
                id="tab-{{ \Illuminate\Support\Str::slug($key) }}"
                aria-controls="tabpanel-{{ \Illuminate\Support\Str::slug($key) }}"
                :aria-selected="tab === {{ \Illuminate\Support\Js::from($key) }}"
                @click="tab = {{ \Illuminate\Support\Js::from($key) }}"
                class="rounded-lg px-3.5 py-2 text-sm transition duration-150"
                :class="tab === {{ \Illuminate\Support\Js::from($key) }}
                    ? 'bg-surface font-semibold text-ink shadow-sm'
                    : 'font-medium text-ink-muted hover:text-ink'"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
