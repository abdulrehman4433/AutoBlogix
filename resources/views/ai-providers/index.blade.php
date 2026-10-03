<x-app-layout
    title="AI Providers"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'AI Providers'],
    ]"
>
    <div class="mx-auto max-w-3xl space-y-6 animate-fade-in" x-data="{ removeUrl: null }">
        <div>
            <h1 class="text-lg font-semibold text-ink">AI Providers</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Connect an OpenAI-compatible API for real generations. Without one, AutoBlogix
                uses the built-in development provider — offline and predictable, ideal for
                trying the workflow.
            </p>
        </div>

        @php($active = $providers->firstWhere('is_active'))

        @if ($active === null)
            <x-alert type="info" title="Using the environment provider">
                No saved configuration — generation currently uses
                <span class="font-medium">AI_PROVIDER={{ $environmentProvider }}</span>.
            </x-alert>
        @else
            <x-alert type="success" title="Active provider">
                {{ $active->provider }}{{ $active->model ? ' · '.$active->model : '' }}
                · key {{ $active->maskedKeyHint() ?? '—' }}
            </x-alert>
        @endif

        <div class="card p-6">
            <h2 class="text-sm font-semibold text-ink">Saved configurations</h2>

            <div class="mt-3">
                <x-table :columns="6" :isEmpty="$providers->isEmpty()">
                    <x-slot name="head">
                        <th scope="col">Provider</th>
                        <th scope="col">Model</th>
                        <th scope="col">Base URL</th>
                        <th scope="col">Key</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </x-slot>

                    <x-slot name="body">
                        @foreach ($providers as $provider)
                            <tr class="hover:bg-surface-muted">
                                <td class="whitespace-nowrap font-medium text-ink">{{ $provider->provider }}</td>
                                <td class="whitespace-nowrap text-ink-muted">{{ $provider->model ?? 'default' }}</td>
                                <td class="text-ink-muted">{{ $provider->base_url ?? 'default' }}</td>
                                <td class="whitespace-nowrap text-ink-muted">{{ $provider->maskedKeyHint() ?? '—' }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($provider->is_active)
                                        <x-badge variant="green">Active</x-badge>
                                    @else
                                        <x-badge variant="gray">Inactive</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @unless ($provider->is_active)
                                            <form method="POST" action="{{ route('ai.providers.activate', $provider) }}">
                                                @csrf
                                                <x-button size="sm" variant="secondary" type="submit">Activate</x-button>
                                            </form>
                                        @endunless
                                        <x-button
                                            size="sm"
                                            variant="danger"
                                            x-on:click="removeUrl = '{{ route('ai.providers.destroy', $provider) }}'; $dispatch('open-modal', 'confirm-provider-remove')"
                                        >Remove</x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot>

                    <x-slot name="empty">
                        <p class="font-medium text-ink">No configurations yet.</p>
                    </x-slot>
                </x-table>
            </div>
        </div>

        <form method="POST" action="{{ route('ai.providers.store') }}" class="card p-6">
            @csrf
            <h2 class="text-sm font-semibold text-ink">Add a provider</h2>
            <p class="mt-1 text-sm text-ink-muted">
                Keys are stored encrypted and never displayed again — only the last four
                characters show. Saving a new provider makes it the active one.
            </p>

            <div class="mt-5 space-y-5">
                <x-input
                    name="api_key"
                    label="API key"
                    type="password"
                    :value="old('api_key')"
                    required
                    minlength="8"
                    maxlength="500"
                    placeholder="sk-…"
                    autocomplete="off"
                    hint="OpenAI-compatible key (OpenRouter and similar work via the base URL)."
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input
                        name="model"
                        label="Model"
                        :value="old('model')"
                        maxlength="64"
                        placeholder="gpt-4o-mini"
                        hint="Optional — defaults to the server's configured model."
                    />
                    <x-input
                        name="base_url"
                        label="Base URL"
                        :value="old('base_url')"
                        maxlength="255"
                        placeholder="https://api.openai.com/v1"
                        hint="Optional. Must be a public http(s) address."
                    />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <x-button variant="primary" type="submit">Save provider</x-button>
            </div>
        </form>

        <x-modal name="confirm-provider-remove" title="Remove this AI provider configuration?" max-width="md">
            <p>
                The saved configuration and its encrypted API key are permanently
                deleted. This cannot be undone.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" :action="removeUrl">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit">Remove</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
