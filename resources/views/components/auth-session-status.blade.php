@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-success-strong']) }}>
        {{ $status }}
    </div>
@endif
