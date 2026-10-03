<x-app-layout
    title="Posts"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Posts'],
    ]"
>
    <div class="space-y-6 animate-fade-in" x-data="{ deleteUrl: null }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-ink">Posts</h1>
                <p class="mt-1 text-sm text-ink-muted">Everything you write for your WordPress sites.</p>
            </div>
            <x-button :href="route('posts.create')">New post</x-button>
        </div>

        {{-- Filters (invalid values are ignored server-side) --}}
        <form method="GET" action="{{ route('posts.index') }}" class="grid gap-3 card p-4 sm:grid-cols-4">
            <x-input
                name="q"
                label="Search"
                :value="$filters['q'] ?? ''"
                placeholder="Title or topic"
            />
            <x-select
                name="status"
                label="Status"
                :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()"
                :selected="$filters['status'] ?? ''"
                placeholder="All statuses"
            />
            <x-select
                name="website"
                label="Website"
                :options="$websites->mapWithKeys(fn ($website) => [$website->id => $website->name])->all()"
                :selected="$filters['website'] ?? ''"
                placeholder="All websites"
            />
            <div class="flex items-end gap-2">
                <x-button variant="primary" type="submit">Filter</x-button>
                <x-button variant="secondary" :href="route('posts.index')">Reset</x-button>
            </div>
        </form>

        @if ($websites->isEmpty())
            <x-alert type="info" title="Add a website first">
                Posts are published to a WordPress site, so you need at least one website
                before creating a post.
                <p class="mt-3">
                    <x-button size="sm" :href="route('websites.create')">Add website</x-button>
                </p>
            </x-alert>
        @else
            <x-table :columns="6" :isEmpty="$posts->isEmpty()">
                <x-slot name="head">
                    <th scope="col">Title</th>
                    <th scope="col">Website</th>
                    <th scope="col">Status</th>
                    <th scope="col">Source</th>
                    <th scope="col">Time</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </x-slot>

                <x-slot name="body">
                    @foreach ($posts as $post)
                        <tr class="hover:bg-surface-muted">
                            <td class="max-w-xs">
                                <a class="block truncate font-medium text-ink hover:text-brand-600" href="{{ route('posts.show', $post) }}">
                                    {{ $post->title }}
                                </a>
                                @if ($post->topic)
                                    <span class="block truncate text-xs text-ink-muted">{{ $post->topic }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-ink-muted">
                                <a class="hover:text-brand-600" href="{{ route('websites.show', $post->website) }}">
                                    {{ $post->website->name }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap">
                                <x-badge :variant="$post->status->badge()">{{ $post->status->label() }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap">
                                <x-badge variant="gray">{{ $post->source->label() }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap text-sm text-ink-muted">
                                @if ($post->status->value === 'published' && $post->published_at)
                                    {{ $post->publishedAtSiteTime()->diffForHumans() }}
                                @elseif ($post->scheduled_at)
                                    {{ $post->scheduledAtSiteTime()->format('M j, Y H:i') }}
                                    <span class="text-xs text-ink-faint">site time</span>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-button size="sm" variant="secondary" :href="route('posts.show', $post)">View</x-button>
                                    <x-button size="sm" variant="secondary" :href="route('posts.edit', $post)">Edit</x-button>
                                    <x-button
                                        size="sm"
                                        variant="danger"
                                        x-on:click="deleteUrl = '{{ route('posts.destroy', $post) }}'; $dispatch('open-modal', 'confirm-post-delete')"
                                    >
                                        Delete
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot>

                <x-slot name="empty">
                    @if (request()->hasAny(['q', 'status', 'website']))
                        <p class="font-medium text-ink">No posts match your filters.</p>
                        <p class="mt-1">Try a different search, or reset the filters.</p>
                        <p class="mt-4">
                            <x-button variant="secondary" :href="route('posts.index')">Reset filters</x-button>
                        </p>
                    @else
                        <p class="font-medium text-ink">No posts yet.</p>
                        <p class="mt-1">Create your first post to get started.</p>
                        <p class="mt-4">
                            <x-button :href="route('posts.create')">New post</x-button>
                        </p>
                    @endif
                </x-slot>
            </x-table>

            @if ($posts->hasPages())
                <div class="card px-4 py-3">
                    {{ $posts->links('vendor.pagination.tailwind') }}
                </div>
            @endif
        @endif

        <x-modal name="confirm-post-delete" title="Delete this post?" max-width="md">
            <p>
                The post and its publishing history will be permanently deleted.
                This cannot be undone.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" :action="deleteUrl">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit">Delete post</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
