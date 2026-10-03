<x-app-layout
    title="Websites"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Websites'],
    ]"
>
    <div class="space-y-6 animate-fade-in" x-data="{ deleteUrl: null }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-ink">Websites</h1>
                <p class="mt-1 text-sm text-ink-muted">WordPress sites connected to your AutoBlogix account.</p>
            </div>
            <x-button :href="route('websites.create')">Add website</x-button>
        </div>

        <x-table :columns="6" :isEmpty="$websites->isEmpty()">
            <x-slot name="head">
                <th scope="col">Name</th>
                <th scope="col">URL</th>
                <th scope="col">Status</th>
                <th scope="col">Versions</th>
                <th scope="col">Last connected</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </x-slot>

            <x-slot name="body">
                @foreach ($websites as $website)
                    <tr class="hover:bg-surface-muted">
                        <td class="whitespace-nowrap font-medium text-ink">
                            <a class="hover:text-brand-600" href="{{ route('websites.show', $website) }}">
                                {{ $website->name }}
                            </a>
                        </td>
                        <td class="max-w-xs truncate text-ink-muted">{{ $website->url }}</td>
                        <td class="whitespace-nowrap">
                            <x-badge :variant="$website->status->badge()">{{ $website->status->label() }}</x-badge>
                        </td>
                        <td class="whitespace-nowrap text-ink-muted">
                            @if ($website->wordpress_version || $website->plugin_version)
                                @if ($website->wordpress_version)
                                    <span class="text-xs text-ink-muted">WP {{ $website->wordpress_version }}</span>
                                @endif
                                @if ($website->plugin_version)
                                    <span class="text-xs text-ink-muted">Plugin {{ $website->plugin_version }}</span>
                                @endif
                            @else
                                <span class="text-ink-faint">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-ink-muted">
                            {{ $website->last_connected_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-2">
                                <x-button size="sm" variant="secondary" :href="route('websites.show', $website)">View</x-button>
                                <x-button size="sm" variant="secondary" :href="route('websites.edit', $website)">Edit</x-button>
                                <x-button
                                    size="sm"
                                    variant="danger"
                                    x-on:click="deleteUrl = '{{ route('websites.destroy', $website) }}'; $dispatch('open-modal', 'confirm-website-delete')"
                                >
                                    Delete
                                </x-button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot>

            <x-slot name="empty">
                <p class="font-medium text-ink">No websites yet.</p>
                <p class="mt-1">Add your first WordPress site to start publishing.</p>
                <p class="mt-4">
                    <x-button :href="route('websites.create')">Add website</x-button>
                </p>
            </x-slot>
        </x-table>

        <x-modal name="confirm-website-delete" title="Delete this website?" max-width="md">
            <p>
                The website, all of its posts, and all connection and publishing
                logs will be permanently deleted. This cannot be undone.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" :action="deleteUrl">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit">Delete website</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
