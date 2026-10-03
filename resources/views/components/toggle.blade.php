@props([
    'name',
    'checked' => false,
    'disabled' => false,
    'label' => null,
])

<label {{ $attributes->merge(['class' => 'inline-flex cursor-pointer items-center gap-3']) }}>
    <input
        type="checkbox"
        name="{{ $name }}"
        {{ $checked ? 'checked' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        class="toggle-input sr-only"
    >
    <span class="toggle" aria-hidden="true"></span>

    @if ($label !== null)
        <span class="text-sm font-medium text-ink">{{ $label }}</span>
    @elseif ($slot->isNotEmpty())
        <span class="text-sm font-medium text-ink">{{ $slot }}</span>
    @endif
</label>
