<x-app-layout title="Dashboard">
    <div class="space-y-6 animate-fade-in">
        {{-- Metric cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6 stagger">
            <x-stat-card
                label="Websites"
                :value="$metrics['websites_total']"
                icon="globe"
                tone="brand"
                :hint="$metrics['websites_connected'].' connected'"
            />
            <x-stat-card
                label="Posts"
                :value="$metrics['posts_total']"
                icon="document-text"
                tone="neutral"
                hint="all time"
            />
            <x-stat-card
                label="Drafts"
                :value="$metrics['posts_by_status']['draft'] ?? 0"
                icon="pencil-square"
                tone="neutral"
                hint="awaiting review"
            />
            <x-stat-card
                label="Scheduled"
                :value="$metrics['posts_by_status']['scheduled'] ?? 0"
                icon="calendar"
                tone="info"
                hint="in queue"
            />
            <x-stat-card
                label="Published"
                :value="$metrics['posts_by_status']['published'] ?? 0"
                icon="check-circle"
                tone="success"
                hint="live on WordPress"
            />
            <x-stat-card
                label="Failed"
                :value="$metrics['posts_by_status']['failed'] ?? 0"
                icon="exclamation-triangle"
                tone="danger"
                hint="needs attention"
            />
        </div>

        {{-- Empty-state call to action on a fresh account --}}
        @if ($metrics['websites_total'] === 0)
            <div class="card overflow-hidden">
                <x-empty-state
                    icon="globe"
                    title="Welcome to {{ config('app.name') }}!"
                    description="Add your first WordPress website to start generating, scheduling and publishing posts."
                >
                    <x-slot name="actions">
                        @if (Route::has('websites.create'))
                            <x-button :href="route('websites.create')">Add a website</x-button>
                        @else
                            <x-button disabled>Add a website</x-button>
                        @endif
                    </x-slot>
                </x-empty-state>
            </div>
        @endif

        {{-- Activity panels --}}
        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Recent publishing activity --}}
            <section class="card overflow-hidden" aria-labelledby="recent-publishing-heading">
                <div class="border-b border-line px-5 py-4">
                    <h2 id="recent-publishing-heading" class="text-sm font-semibold text-ink">Recent publishing activity</h2>
                </div>

                @if ($metrics['recent_publishing']->isEmpty())
                    <x-empty-state compact icon="bolt" title="No publishing activity yet." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($metrics['recent_publishing'] as $log)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">
                                        {{ $log->post?->title ?? 'Deleted post' }}
                                    </p>
                                    <p class="truncate text-xs text-ink-muted">
                                        {{ $log->website?->name }} &middot; attempt #{{ $log->attempt }}
                                        @if ($log->http_status)
                                            &middot; HTTP {{ $log->http_status }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                    <span class="text-xs text-ink-faint">{{ $log->completed_at?->diffForHumans() ?? $log->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Upcoming scheduled posts --}}
            <section class="card overflow-hidden" aria-labelledby="upcoming-heading">
                <div class="border-b border-line px-5 py-4">
                    <h2 id="upcoming-heading" class="text-sm font-semibold text-ink">Upcoming scheduled posts</h2>
                </div>

                @if ($metrics['upcoming_posts']->isEmpty())
                    <x-empty-state compact icon="calendar" title="Nothing scheduled yet." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($metrics['upcoming_posts'] as $post)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $post->title }}</p>
                                    <p class="truncate text-xs text-ink-muted">{{ $post->website?->name }}</p>
                                </div>
                                <span class="shrink-0 text-xs text-ink-muted">
                                    {{ $post->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Recent connection activity --}}
            <section class="card overflow-hidden" aria-labelledby="connections-heading">
                <div class="border-b border-line px-5 py-4">
                    <h2 id="connections-heading" class="text-sm font-semibold text-ink">Recent connection activity</h2>
                </div>

                @if ($metrics['recent_connections']->isEmpty())
                    <x-empty-state compact icon="link" title="No connection activity yet." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($metrics['recent_connections'] as $log)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $log->website?->name }}</p>
                                    <p class="truncate text-xs text-ink-muted">{{ $log->action }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                    <span class="text-xs text-ink-faint">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
