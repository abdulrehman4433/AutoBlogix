{{--
    Design-system pagination for AutoBlogix (glossy Tailwind look).
    Mirrors the default Laravel tailwind view's structure and translated
    strings (Showing / to / of / results / Previous / Next) so copy-based
    feature-test assertions keep passing.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {{-- Mobile: previous / next --}}
        <div class="flex items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="btn btn-sm btn-secondary pointer-events-none opacity-50">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-sm btn-secondary">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-sm btn-secondary">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span aria-disabled="true" class="btn btn-sm btn-secondary pointer-events-none opacity-50">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        {{-- Desktop: results summary --}}
        <p class="hidden text-sm text-ink-muted sm:block">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span class="font-semibold text-ink">{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span class="font-semibold text-ink">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            {!! __('of') !!}
            <span class="font-semibold text-ink">{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </p>

        {{-- Desktop: numbered links --}}
        <div class="hidden sm:block">
            <div class="flex items-center gap-1">
                @if (! $paginator->onFirstPage())
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        rel="prev"
                        aria-label="{{ __('pagination.previous') }}"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-ink-muted transition duration-150 hover:border-brand-300 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <x-icon name="chevron-left" size="sm" />
                    </a>
                @else
                    <span
                        aria-disabled="true"
                        aria-label="{{ __('pagination.previous') }}"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-ink-faint opacity-50"
                    >
                        <x-icon name="chevron-left" size="sm" />
                    </span>
                @endif

                @foreach ($elements as $element)
                    {{-- Three dots separator --}}
                    @if (is_string($element))
                        <span aria-disabled="true" class="inline-flex h-9 items-center justify-center rounded-lg border border-line bg-surface px-3 text-sm font-medium text-ink-muted">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Array of page links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span
                                    aria-current="page"
                                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-brand-gradient px-3 text-sm font-semibold text-white shadow-sm"
                                >{{ $page }}</span>
                            @else
                                <a
                                    href="{{ $url }}"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-line bg-surface px-3 text-sm font-medium text-ink-muted transition duration-150 hover:border-brand-300 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                >{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        rel="next"
                        aria-label="{{ __('pagination.next') }}"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-ink-muted transition duration-150 hover:border-brand-300 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                    >
                        <x-icon name="chevron-right" size="sm" />
                    </a>
                @else
                    <span
                        aria-disabled="true"
                        aria-label="{{ __('pagination.next') }}"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-ink-faint opacity-50"
                    >
                        <x-icon name="chevron-right" size="sm" />
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
