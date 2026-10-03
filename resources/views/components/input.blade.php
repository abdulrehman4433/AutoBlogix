@props([
    'name',
    'label' => null,
    'type' => 'text',
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'hint' => null,
    'autocomplete' => null,
    'autofocus' => false,
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
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $attributes->get('value')) }}"
            placeholder="{{ $floating ? ' ' : $placeholder }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @elseif ($type === 'email') autocomplete="email" @endif
            @if ($autofocus) autofocus @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'value'])->merge(['class' => 'input']) }}
        />
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
