@props(['name'])

<div
    id="tabpanel-{{ \Illuminate\Support\Str::slug($name) }}"
    role="tabpanel"
    aria-labelledby="tab-{{ \Illuminate\Support\Str::slug($name) }}"
    x-show="tab === {{ \Illuminate\Support\Js::from($name) }}"
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    {{ $attributes }}
>
    {{ $slot }}
</div>
