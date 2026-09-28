<x-app-layout title="Dashboard">
    <div class="space-y-6">
        {{-- Metric cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Websites</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $metrics['websites_total'] }}</p>
                <p class="mt-1 text-xs text-emerald-600">{{ $metrics['websites_connected'] }} connected</p>
            </div>

            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Posts</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $metrics['posts_total'] }}</p>
                <p class="mt-1 text-xs text-gray-400">all time</p>
            </div>

            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Drafts</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $metrics['posts_by_status']['draft'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-400">awaiting review</p>
            </div>

            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Scheduled</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $metrics['posts_by_status']['scheduled'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-sky-600">in queue</p>
            </div>

            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Published</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $metrics['posts_by_status']['published'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-400">live on WordPress</p>
            </div>

            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Failed</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $metrics['posts_by_status']['failed'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-red-500">needs attention</p>
            </div>
        </div>

        {{-- Empty-state call to action on a fresh account --}}
        @if ($metrics['websites_total'] === 0)
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center">
                <h2 class="text-base font-semibold text-gray-900">Welcome to {{ config('app.name') }}!</h2>
                <p class="mx-auto mt-1 max-w-xl text-sm text-gray-500">
                    Add your first WordPress website to start generating, scheduling and publishing posts.
                </p>
                <div class="mt-4">
                    @if (Route::has('websites.create'))
                        <x-button :href="route('websites.create')">Add a website</x-button>
                    @else
                        <x-button disabled>Add a website</x-button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Activity panels --}}
        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Recent publishing activity --}}
            <section class="rounded-xl bg-white ring-1 ring-gray-200" aria-labelledby="recent-publishing-heading">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 id="recent-publishing-heading" class="text-sm font-semibold text-gray-900">Recent publishing activity</h2>
                </div>

                @if ($metrics['recent_publishing']->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">No publishing activity yet.</p>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach ($metrics['recent_publishing'] as $log)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">
                                        {{ $log->post?->title ?? 'Deleted post' }}
                                    </p>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $log->website?->name }} &middot; attempt #{{ $log->attempt }}
                                        @if ($log->http_status)
                                            &middot; HTTP {{ $log->http_status }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                    <span class="text-xs text-gray-400">{{ $log->completed_at?->diffForHumans() ?? $log->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Upcoming scheduled posts --}}
            <section class="rounded-xl bg-white ring-1 ring-gray-200" aria-labelledby="upcoming-heading">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 id="upcoming-heading" class="text-sm font-semibold text-gray-900">Upcoming scheduled posts</h2>
                </div>

                @if ($metrics['upcoming_posts']->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">Nothing scheduled yet.</p>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach ($metrics['upcoming_posts'] as $post)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $post->title }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $post->website?->name }}</p>
                                </div>
                                <span class="shrink-0 text-xs text-gray-500">
                                    {{ $post->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Recent connection activity --}}
            <section class="rounded-xl bg-white ring-1 ring-gray-200" aria-labelledby="connections-heading">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 id="connections-heading" class="text-sm font-semibold text-gray-900">Recent connection activity</h2>
                </div>

                @if ($metrics['recent_connections']->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-500">No connection activity yet.</p>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach ($metrics['recent_connections'] as $log)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $log->website?->name }}</p>
                                    <p class="truncate text-xs text-gray-500">{{ $log->action }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                    <span class="text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
