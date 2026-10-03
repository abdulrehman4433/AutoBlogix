@props(['disabled' => false])

<input
    @disabled($disabled)
    @if ($errors->has($attributes->get('name'))) aria-invalid="true" @endif
    {{ $attributes->merge(['class' => 'input']) }}
>
