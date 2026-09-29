<x-app-layout :title="$post->title">
    <div class="space-y-6" x-data="{ deleteUrl: '{{ route('posts.destroy', $post) }}' }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-lg font-semibold text-gray-900">{{ $post->title }}</h1>
                    <x-badge :variant="$post->status->badge()">{{ $post->status->label() }}</x-badge>
                    <x-badge variant="gray">{{ $post->source->label() }}</x-badge>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    <a class="hover:text-indigo-600" href="{{ route('websites.show', $post->website) }}">{{ $post->website->name }}</a>
                    · <span class="text-gray-400">/</span> {{ $post->slug }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if (in_array($post->status->value, ['draft', 'scheduled', 'failed', 'generated'], true))
                    <form method="POST" action="{{ route('posts.publish', $post) }}">
                        @csrf
                        <x-button variant="primary" type="submit">
                            {{ $post->status->value === 'failed' ? 'Retry publish' : 'Publish now' }}
                        </x-button>
                    </form>
                @elseif ($post->status->value === 'publishing')
                    <x-button variant="primary" disabled loading>Publishing…</x-button>
                @endif
                @if (in_array($post->status->value, ['draft', 'generated'], true))
                    @if (trim((string) $post->content) !== '')
                        <x-button
                            variant="secondary"
                            x-on:click="$dispatch('open-modal', 'confirm-ai-generate')"
                        >
                            Regenerate with AI
                        </x-button>
                    @else
                        <form method="POST" action="{{ route('posts.generate', $post) }}">
                            @csrf
                            <x-button variant="secondary" type="submit">Generate content</x-button>
                        </form>
                    @endif
                @elseif ($post->status->value === 'generating')
                    <x-button variant="secondary" disabled loading>Generating…</x-button>
                @endif
                <x-button variant="secondary" :href="route('posts.edit', $post)">Edit</x-button>
                <x-button
                    variant="danger"
                    x-on:click="$dispatch('open-modal', 'confirm-post-delete')"
                >
                    Delete
                </x-button>
            </div>
        </div>

        @if ($post->status->value === 'failed' && $post->failure_reason)
            <x-alert type="error" title="Publishing failed">
                {{ $post->failure_reason }}
            </x-alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if ($post->excerpt)
                    <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                        <h2 class="text-sm font-semibold text-gray-900">Excerpt</h2>
                        <p class="mt-2 text-sm text-gray-600">{{ $post->excerpt }}</p>
                    </div>
                @endif

                <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                    <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
                    <div class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-800">
                        @if (trim((string) $post->content) === '')
                            <span class="text-gray-400">No content yet.</span>
                        @else
                            {!! \App\Support\HtmlSanitizer::sanitize($post->content) !!}
                        @endif
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                    <h2 class="text-sm font-semibold text-gray-900">Publishing history</h2>

                    @if ($publishingLogs->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">No publishing attempts yet.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                        <th scope="col" class="py-2 pr-4">Attempt</th>
                                        <th scope="col" class="py-2 pr-4">Status</th>
                                        <th scope="col" class="py-2 pr-4">HTTP</th>
                                        <th scope="col" class="py-2 pr-4">Started</th>
                                        <th scope="col" class="py-2 pr-4">Completed</th>
                                        <th scope="col" class="py-2">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($publishingLogs as $log)
                                        <tr>
                                            <td class="py-2 pr-4 text-gray-900">#{{ $log->attempt }}</td>
                                            <td class="py-2 pr-4">
                                                <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                            </td>
                                            <td class="py-2 pr-4 text-gray-600">{{ $log->http_status ?? '—' }}</td>
                                            <td class="py-2 pr-4 text-gray-600">{{ $log->started_at?->format('M j, Y H:i') ?? '—' }}</td>
                                            <td class="py-2 pr-4 text-gray-600">{{ $log->completed_at?->format('M j, Y H:i') ?? '—' }}</td>
                                            <td class="py-2 text-gray-600">
                                                {{ $log->error_message ?? $log->response_summary ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                    <h2 class="text-sm font-semibold text-gray-900">AI generation history</h2>

                    @if ($aiLogs->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">No AI generation attempts yet.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                        <th scope="col" class="py-2 pr-4">Provider</th>
                                        <th scope="col" class="py-2 pr-4">Status</th>
                                        <th scope="col" class="py-2 pr-4">Tokens</th>
                                        <th scope="col" class="py-2 pr-4">Started</th>
                                        <th scope="col" class="py-2">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($aiLogs as $log)
                                        <tr>
                                            <td class="py-2 pr-4 text-gray-900">
                                                {{ $log->provider }}{{ $log->model ? ' · '.$log->model : '' }}
                                            </td>
                                            <td class="py-2 pr-4">
                                                <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                            </td>
                                            <td class="py-2 pr-4 text-gray-600">{{ $log->tokens_used ?? '—' }}</td>
                                            <td class="py-2 pr-4 text-gray-600">{{ $log->started_at?->format('M j, Y H:i') ?? '—' }}</td>
                                            <td class="py-2 text-gray-600">{{ $log->error_message ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                    <h2 class="text-sm font-semibold text-gray-900">Details</h2>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Website</dt>
                            <dd class="mt-0.5">
                                <a class="text-indigo-600 hover:text-indigo-500" href="{{ route('websites.show', $post->website) }}">
                                    {{ $post->website->name }}
                                </a>
                            </dd>
                        </div>

                        @if ($post->topic)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Topic</dt>
                                <dd class="mt-0.5 text-gray-900">{{ $post->topic }}</dd>
                            </div>
                        @endif

                        @if ($post->category)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Category</dt>
                                <dd class="mt-0.5 text-gray-900">{{ $post->category }}</dd>
                            </div>
                        @endif

                        @if ((array) $post->tags !== [])
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Tags</dt>
                                <dd class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ((array) $post->tags as $tag)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $tag }}</span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif

                        @if ((array) $post->keywords !== [])
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Focus keywords</dt>
                                <dd class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ((array) $post->keywords as $keyword)
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">{{ $keyword }}</span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif

                        @if ($post->meta_description)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Meta description</dt>
                                <dd class="mt-0.5 text-gray-900">{{ $post->meta_description }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Schedule</dt>
                            <dd class="mt-0.5 text-gray-900">
                                @if ($post->scheduled_at)
                                    {{ $post->scheduledAtSiteTime()->format('M j, Y H:i') }}
                                    <span class="text-xs text-gray-400">website time</span>
                                @else
                                    <span class="text-gray-400">Not scheduled</span>
                                @endif
                            </dd>
                        </div>

                        @if ($post->published_at)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">Published</dt>
                                <dd class="mt-0.5 text-gray-900">
                                    {{ $post->publishedAtSiteTime()->format('M j, Y H:i') }}
                                    <span class="text-xs text-gray-400">website time</span>
                                </dd>
                            </div>
                        @endif

                        @if ($post->wordpress_url)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">On WordPress</dt>
                                <dd class="mt-0.5 break-all">
                                    <a class="text-indigo-600 hover:text-indigo-500" href="{{ $post->wordpress_url }}" target="_blank" rel="noopener">
                                        {{ $post->wordpress_url }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        @if ($post->ai_provider)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500">AI</dt>
                                <dd class="mt-0.5 text-gray-900">
                                    {{ $post->ai_provider }}{{ $post->ai_model ? ' · '.$post->ai_model : '' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <x-modal name="confirm-ai-generate" title="Regenerate content with AI?" max-width="md">
            <p>
                The AI will replace this post's content, excerpt, tags, keywords
                and meta description. You can edit everything afterwards.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" action="{{ route('posts.generate', $post) }}">
                        @csrf
                        <x-button variant="primary" type="submit">Regenerate</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>

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
