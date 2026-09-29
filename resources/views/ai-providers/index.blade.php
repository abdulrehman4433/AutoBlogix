<x-app-layout title="AI Providers">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">AI Providers</h1>
            <p class="mt-1 text-sm text-gray-500">
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

        <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            <h2 class="text-sm font-semibold text-gray-900">Saved configurations</h2>

            @if ($providers->isEmpty())
                <p class="mt-3 text-sm text-gray-500">No configurations yet.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <th scope="col" class="py-2 pr-4">Provider</th>
                                <th scope="col" class="py-2 pr-4">Model</th>
                                <th scope="col" class="py-2 pr-4">Base URL</th>
                                <th scope="col" class="py-2 pr-4">Key</th>
                                <th scope="col" class="py-2 pr-4">Status</th>
                                <th scope="col" class="py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($providers as $provider)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-900">{{ $provider->provider }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ $provider->model ?? 'default' }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ $provider->base_url ?? 'default' }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ $provider->maskedKeyHint() ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($provider->is_active)
                                            <x-badge variant="green">Active</x-badge>
                                        @else
                                            <x-badge variant="gray">Inactive</x-badge>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-3">
                                            @unless ($provider->is_active)
                                                <form method="POST" action="{{ route('ai.providers.activate', $provider) }}">
                                                    @csrf
                                                    <x-button size="sm" variant="secondary" type="submit">Activate</x-button>
                                                </form>
                                            @endunless
                                            <form
                                                method="POST"
                                                action="{{ route('ai.providers.destroy', $provider) }}"
                                                x-on:submit="if (! confirm('Remove this AI provider configuration?')) $event.preventDefault()"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <x-button size="sm" variant="danger" type="submit">Remove</x-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('ai.providers.store') }}" class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            @csrf
            <h2 class="text-sm font-semibold text-gray-900">Add a provider</h2>
            <p class="mt-1 text-sm text-gray-500">
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
    </div>
</x-app-layout>
