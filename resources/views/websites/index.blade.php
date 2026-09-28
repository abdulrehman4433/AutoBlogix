<x-app-layout title="Websites">
    <div class="space-y-6" x-data="{ deleteUrl: null }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">Websites</h1>
                <p class="mt-1 text-sm text-gray-500">WordPress sites connected to your AutoBlogix account.</p>
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
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap font-medium text-gray-900">
                            <a class="hover:text-indigo-600" href="{{ route('websites.show', $website) }}">
                                {{ $website->name }}
                            </a>
                        </td>
                        <td class="max-w-xs truncate text-gray-600">{{ $website->url }}</td>
                        <td class="whitespace-nowrap">
                            <x-badge :variant="$website->status->badge()">{{ $website->status->label() }}</x-badge>
                        </td>
                        <td class="whitespace-nowrap text-gray-600">
                            @if ($website->wordpress_version || $website->plugin_version)
                                @if ($website->wordpress_version)
                                    <span class="text-xs text-gray-500">WP {{ $website->wordpress_version }}</span>
                                @endif
                                @if ($website->plugin_version)
                                    <span class="text-xs text-gray-500">Plugin {{ $website->plugin_version }}</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-gray-600">
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
                <p class="font-medium text-gray-900">No websites yet.</p>
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
