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
    $describedBy = collect([
        $hint && ! $error ? "{$name}-hint" : null,
        $error ? "{$name}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div {{ $attributes->only('class')->class(['block']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500" aria-hidden="true">*</span>
        @endif
    </label>

    <div class="relative mt-1">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $attributes->get('value')) }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @elseif ($type === 'email') autocomplete="email" @endif
            @if ($autofocus) autofocus @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'value'])->merge(['class' => 'block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm ' . ($error ? 'ring-red-300 focus:ring-red-500' : 'ring-gray-300')]) }}
        />
    </div>

    @if ($hint && ! $error)
        <p id="{{ $name }}-hint" class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
