@props([
    'items' => [],
])

@php
    /**
     * Professional breadcrumb trail.
     *
     * Each entry is either a plain label string or an array:
     * ['label' => string, 'href' => ?string, 'icon' => ?string].
     * The final entry is the current page (rendered as plain text with
     * aria-current="page"); entries with an href render as links.
     *
     * @var array<int, string|array{label: string, href?: string|null, icon?: string|null}>
     */
    $entries = array_map(static function (mixed $item): array {
        if (! is_array($item)) {
            return ['label' => (string) $item, 'href' => null, 'icon' => null];
        }

        return [
            'label' => (string) ($item['label'] ?? ''),
            'href' => $item['href'] ?? null,
            'icon' => $item['icon'] ?? null,
        ];
    }, array_values($items));
@endphp

@if ($entries !== [])
    <nav aria-label="Breadcrumb" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
            @foreach ($entries as $index => $entry)
                <li class="flex items-center gap-1.5">
                    @if ($index > 0)
                        <x-icon name="chevron-right" size="xs" class="shrink-0 text-ink-faint" aria-hidden="true" />
                    @endif

                    @if ($loop->last)
                        <span
                            aria-current="page"
                            class="inline-flex max-w-[12rem] items-center gap-1 truncate font-medium text-ink sm:max-w-[24rem]"
                        >{{ $entry['label'] }}</span>
                    @elseif ($entry['href'] === null)
                        <span class="inline-flex max-w-[12rem] items-center gap-1 truncate text-ink-muted sm:max-w-[24rem]">
                            {{ $entry['label'] }}
                        </span>
                    @else
                        <a
                            href="{{ $entry['href'] }}"
                            class="inline-flex max-w-[12rem] items-center gap-1 truncate text-ink-muted transition duration-150 hover:text-brand-600 sm:max-w-[24rem]"
                        >
                            @if ($entry['icon'])
                                <x-icon :name="$entry['icon']" size="xs" class="shrink-0" />
                            @endif
                            {{ $entry['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
