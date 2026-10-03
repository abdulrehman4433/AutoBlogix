@props([
    'value',
    'label' => 'Copy',
    'copiedLabel' => 'Copied!',
    'size' => 'sm',
    'variant' => 'secondary',
])

{{--
    Copy-to-clipboard with a "Copied!" tooltip.

    The value rides in a data attribute (Blade-escaped, no directives inside
    attributes) and the click handler lives on the plain wrapper element —
    @js() inside a component attribute never compiles and would ship dead JS.
--}}
<span
    {{ $attributes->class(['relative inline-flex']) }}
    data-copy="{{ $value }}"
    x-data="{ copied: false }"
    x-on:click="navigator.clipboard.writeText($el.dataset.copy); copied = true; window.setTimeout(() => copied = false, 1500)"
>
    <x-button :size="$size" :variant="$variant">
        {{ $label }}
    </x-button>

    <span
        x-show="copied"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        role="status"
        class="pointer-events-none absolute bottom-full left-1/2 z-40 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-ink px-2 py-1 text-xs font-medium text-white shadow-md"
    >{{ $copiedLabel }}</span>
</span>
