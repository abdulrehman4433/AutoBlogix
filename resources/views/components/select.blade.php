@props([
    'name',
    'label' => null,
    'required' => false,
    'disabled' => false,
    'hint' => null,
    'placeholder' => null,
    'options' => [],
    'selected' => null,
])

@php
    $label = $label ?? ucfirst(str_replace('_', ' ', $name));
    $error = $errors->first($name);
    $value = old($name, $selected ?? '');
    $describedBy = collect([
        $hint && ! $error ? "{$name}-hint" : null,
        $error ? "{$name}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div {{ $attributes->only('class')->class(['block']) }}>
    <div class="field">
        <select
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class'])->merge(['class' => 'input']) }}
        >
            @if ($placeholder !== null)
                <option value="" @selected($value === '')>{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>

        <label for="{{ $name }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
    </div>

    @if ($hint && ! $error)
        <p id="{{ $name }}-hint" class="input-hint">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $name }}-error" class="input-message input-message--error">{{ $error }}</p>
    @endif
</div>
