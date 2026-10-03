@props([
    'title',
    'description' => null,
    'icon' => 'document-text',
    'compact' => false,
])

<div {{ $attributes->class(['flex flex-col items-center justify-center px-6 text-center', $compact ? 'py-8' : 'py-14']) }}>
    <span class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
        <x-icon :name="$icon" size="lg" />
    </span>

    <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 max-w-sm text-xs leading-5 text-ink-muted">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-2 max-w-sm text-xs leading-5 text-ink-muted">
            {{ $slot }}
        </div>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
