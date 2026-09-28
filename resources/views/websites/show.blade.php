<x-app-layout :title="$website->name">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('websites.index') }}" class="text-sm text-gray-500 hover:text-indigo-600">&larr; Back to websites</a>
                <div class="mt-1 flex items-center gap-3">
                    <h1 class="text-lg font-semibold text-gray-900">{{ $website->name }}</h1>
                    <x-badge :variant="$website->status->badge()">{{ $website->status->label() }}</x-badge>
                </div>
                <a
                    href="{{ $website->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-1 block text-sm text-indigo-600 hover:text-indigo-500"
                >{{ $website->url }} &nearr;</a>
            </div>

            <div class="flex flex-wrap items-center gap-2" x-data>
                <x-button size="sm" variant="secondary" :href="route('websites.edit', $website)">Edit</x-button>
                <x-button
                    size="sm"
                    variant="secondary"
                    x-on:click="$dispatch('open-modal', 'confirm-rotate-credentials')"
                >
                    Rotate credentials
                </x-button>
                @if ($website->api_key !== null)
                    <x-button
                        size="sm"
                        variant="secondary"
                        x-on:click="$dispatch('open-modal', 'confirm-revoke-credentials')"
                    >
                        Revoke
                    </x-button>
                @endif
                <x-button
                    size="sm"
                    variant="danger"
                    x-on:click="$dispatch('open-modal', 'confirm-delete-website')"
                >
                    Delete
                </x-button>
            </div>
        </div>

        {{-- One-time plaintext secret: flashed for exactly one request, never in the URL --}}
        @if (session('plaintext_secret'))
            <x-alert type="warning" title="Copy your API secret now — it is shown only once.">
                <div class="mt-2 space-y-2">
                    <p>
                        Enter both values in the AutoBlogix plugin settings on your
                        WordPress site. Afterwards only a masked hint will be visible here.
                    </p>
                    <div class="flex items-center gap-2">
                        <code class="block flex-1 truncate rounded-md bg-white px-3 py-2 text-sm ring-1 ring-gray-200">
                            {{ session('plaintext_secret') }}
                        </code>
                        <span x-data="{ copied: false }">
                            <x-button
                                size="sm"
                                variant="secondary"
                                x-on:click="navigator.clipboard.writeText(@js(session('plaintext_secret'))); copied = true; window.setTimeout(() => copied = false, 1500)"
                            >
                                <span x-show="!copied">Copy</span>
                                <span x-show="copied" x-cloak>Copied!</span>
                            </x-button>
                        </span>
                    </div>
                </div>
            </x-alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Status & connection details --}}
            <section class="rounded-xl bg-white p-6 ring-1 ring-gray-200" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-sm font-semibold text-gray-900">Connection status</h2>

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd class="mt-0.5"><x-badge :variant="$website->status->badge()">{{ $website->status->label() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Timezone</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $website->timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">WordPress version</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $website->wordpress_version ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Plugin version</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $website->plugin_version ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Last connected</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">
                            {{ $website->last_connected_at?->format('M j, Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Last sync</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">
                            {{ $website->last_sync_at?->diffForHumans() ?? '—' }}
                        </dd>
                    </div>
                </dl>

                @if ($website->status !== \App\Enums\WebsiteStatus::Connected)
                    <div class="mt-5 rounded-lg bg-sky-50 p-4 text-sm text-sky-900">
                        <p class="font-semibold">Finish connecting this website</p>
                        <ol class="mt-2 list-decimal space-y-1 pl-5">
                            <li>Copy the API key and secret shown below.</li>
                            <li>Install and activate the AutoBlogix plugin on your WordPress site.</li>
                            <li>Paste the key and secret into the plugin settings — the plugin calls back to AutoBlogix and the status turns <span class="font-semibold">Connected</span>.</li>
                        </ol>
                    </div>
                @endif

                @if ($website->status === \App\Enums\WebsiteStatus::Error)
                    <p class="mt-4 text-sm text-red-600">
                        The last connection attempt failed. Verify the credentials in
                        the plugin, or rotate them and try again.
                    </p>
                @endif
            </section>

            {{-- Credentials --}}
            <section class="rounded-xl bg-white p-6 ring-1 ring-gray-200" aria-labelledby="credentials-heading">
                <h2 id="credentials-heading" class="text-sm font-semibold text-gray-900">API credentials</h2>

                @if ($website->api_key === null)
                    <p class="mt-4 text-sm text-gray-500">
                        Credentials were revoked. Rotate them to generate a new
                        key and secret pair for the plugin.
                    </p>
                @else
                    <div class="mt-4 space-y-4">
                        <div>
                            <p class="text-xs font-medium text-gray-500">API key</p>
                            <div class="mt-1 flex items-center gap-2">
                                <code class="block flex-1 truncate rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200">
                                    {{ $website->api_key }}
                                </code>
                                <span x-data="{ copied: false }">
                                    <x-button
                                        size="sm"
                                        variant="secondary"
                                        x-on:click="navigator.clipboard.writeText(@js($website->api_key)); copied = true; window.setTimeout(() => copied = false, 1500)"
                                    >
                                        <span x-show="!copied">Copy</span>
                                        <span x-show="copied" x-cloak>Copied!</span>
                                    </x-button>
                                </span>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-gray-500">API secret</p>
                            <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200">
                                {{ $website->maskedSecretHint() ?? '••••••••' }}
                            </p>
                            <p class="mt-1.5 text-xs text-gray-500">
                                For security the secret is only shown at creation and rotation.
                                Lost it? Rotate the credentials to get a fresh pair.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                    <x-button
                        size="sm"
                        variant="secondary"
                        x-on:click="$dispatch('open-modal', 'confirm-rotate-credentials')"
                    >
                        {{ $website->api_key === null ? 'Generate credentials' : 'Rotate credentials' }}
                    </x-button>
                    @if ($website->api_key !== null)
                        <x-button
                            size="sm"
                            variant="ghost"
                            x-on:click="$dispatch('open-modal', 'confirm-revoke-credentials')"
                        >
                            Revoke credentials
                        </x-button>
                    @endif
                </div>
            </section>
        </div>

        {{-- Recent connection activity --}}
        <section class="rounded-xl bg-white ring-1 ring-gray-200" aria-labelledby="activity-heading">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 id="activity-heading" class="text-sm font-semibold text-gray-900">Connection activity</h2>
            </div>

            @if ($connectionLogs->isEmpty())
                <p class="px-5 py-6 text-sm text-gray-500">No connection activity yet.</p>
            @else
                <ul class="divide-y divide-gray-50">
                    @foreach ($connectionLogs as $log)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ ucwords(str_replace('_', ' ', $log->action)) }}
                                </p>
                                <p class="truncate text-xs text-gray-500">
                                    {{ $log->message }}
                                    @if ($log->ip_address)
                                        &middot; {{ $log->ip_address }}
                                    @endif
                                </p>
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

        {{-- Confirmation modals --}}
        <x-modal name="confirm-rotate-credentials" title="Rotate credentials?" max-width="md">
            <p>
                A new API key and secret will be generated and shown once. The
                current credentials stop working immediately, so you must update
                the plugin with the new pair or the website will disconnect.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" action="{{ route('websites.credentials.rotate', $website) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Rotate credentials</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>

        <x-modal name="confirm-revoke-credentials" title="Revoke credentials?" max-width="md">
            <p>
                The key and secret will be deleted. The plugin will no longer be
                able to authenticate, so publishing to this website stops until
                you generate new credentials.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" action="{{ route('websites.credentials.revoke', $website) }}">
                        @csrf
                        <x-button variant="danger" type="submit">Revoke credentials</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>

        <x-modal name="confirm-delete-website" title="Delete this website?" max-width="md">
            <p>
                <span class="font-medium text-gray-900">{{ $website->name }}</span>,
                all of its posts, and all connection and publishing logs will be
                permanently deleted. This cannot be undone.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" action="{{ route('websites.destroy', $website) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit">Delete website</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
