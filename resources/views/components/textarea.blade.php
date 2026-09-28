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
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class'])->merge(['class' => 'block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm ' . ($error ? 'ring-red-300 focus:ring-red-500' : 'ring-gray-300')]) }}
        >{{ old($name, $attributes->get('value', '')) }}</textarea>
    </div>

    @if ($hint && ! $error)
        <p id="{{ $name }}-hint" class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
