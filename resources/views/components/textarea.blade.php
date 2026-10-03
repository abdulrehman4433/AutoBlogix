@props([
    'name',
    'label' => null,
    'required' => false,
    'disabled' => false,
    'rows' => 6,
    'placeholder' => null,
    'hint' => null,
])

@php
    $label = $label ?? ucfirst(str_replace('_', ' ', $name));
    $error = $errors->first($name);
    $floating = $placeholder === null;
    $describedBy = collect([
        $hint && ! $error ? "{$name}-hint" : null,
        $error ? "{$name}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div {{ $attributes->only('class')->class(['block']) }}>
    <div class="field {{ $floating ? 'field--float' : '' }}">
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $floating ? ' ' : $placeholder }}"
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class'])->merge(['class' => 'input']) }}
        >{{ old($name, $attributes->get('value', '')) }}</textarea>

        <label for="{{ $name }}" @class(['field-label' => $floating])>
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
