<x-app-layout
    :title="$post->title"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Posts', 'href' => route('posts.index')],
        ['label' => $post->title],
    ]"
>
    <div class="space-y-6 animate-fade-in" x-data="{ deleteUrl: '{{ route('posts.destroy', $post) }}' }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-lg font-semibold text-ink">{{ $post->title }}</h1>
                    <x-badge :variant="$post->status->badge()">{{ $post->status->label() }}</x-badge>
                    <x-badge variant="gray">{{ $post->source->label() }}</x-badge>
                </div>
                <p class="mt-1 text-sm text-ink-muted">
                    <a class="hover:text-brand-600" href="{{ route('websites.show', $post->website) }}">{{ $post->website->name }}</a>
                    · <span class="text-ink-faint">/</span> {{ $post->slug }}
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
                    <div class="card p-6">
                        <h2 class="text-sm font-semibold text-ink">Excerpt</h2>
                        <p class="mt-2 text-sm text-ink-muted">{{ $post->excerpt }}</p>
                    </div>
                @endif

                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-ink">Preview</h2>
                    <div class="mt-3 whitespace-pre-line text-sm leading-6 text-ink">
                        @if (trim((string) $post->content) === '')
                            <span class="text-ink-faint">No content yet.</span>
                        @else
                            {!! \App\Support\HtmlSanitizer::sanitize($post->content) !!}
                        @endif
                    </div>
                </div>

                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-ink">Publishing history</h2>

                    <div class="mt-3">
                        <x-table :columns="6" :isEmpty="$publishingLogs->isEmpty()">
                            <x-slot name="head">
                                <th scope="col">Attempt</th>
                                <th scope="col">Status</th>
                                <th scope="col">HTTP</th>
                                <th scope="col">Started</th>
                                <th scope="col">Completed</th>
                                <th scope="col">Details</th>
                            </x-slot>

                            <x-slot name="body">
                                @foreach ($publishingLogs as $log)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-ink">#{{ $log->attempt }}</td>
                                        <td class="whitespace-nowrap">
                                            <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                        </td>
                                        <td class="whitespace-nowrap text-ink-muted">{{ $log->http_status ?? '—' }}</td>
                                        <td class="whitespace-nowrap text-ink-muted">{{ $log->started_at?->format('M j, Y H:i') ?? '—' }}</td>
                                        <td class="whitespace-nowrap text-ink-muted">{{ $log->completed_at?->format('M j, Y H:i') ?? '—' }}</td>
                                        <td class="text-ink-muted">
                                            {{ $log->error_message ?? $log->response_summary ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </x-slot>

                            <x-slot name="empty">
                                <p class="font-medium text-ink">No publishing attempts yet.</p>
                            </x-slot>
                        </x-table>
                    </div>
                </div>

                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-ink">AI generation history</h2>

                    <div class="mt-3">
                        <x-table :columns="5" :isEmpty="$aiLogs->isEmpty()">
                            <x-slot name="head">
                                <th scope="col">Provider</th>
                                <th scope="col">Status</th>
                                <th scope="col">Tokens</th>
                                <th scope="col">Started</th>
                                <th scope="col">Details</th>
                            </x-slot>

                            <x-slot name="body">
                                @foreach ($aiLogs as $log)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-ink">
                                            {{ $log->provider }}{{ $log->model ? ' · '.$log->model : '' }}
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <x-badge :variant="$log->status->badge()">{{ $log->status->label() }}</x-badge>
                                        </td>
                                        <td class="whitespace-nowrap text-ink-muted">{{ $log->tokens_used ?? '—' }}</td>
                                        <td class="whitespace-nowrap text-ink-muted">{{ $log->started_at?->format('M j, Y H:i') ?? '—' }}</td>
                                        <td class="text-ink-muted">{{ $log->error_message ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </x-slot>

                            <x-slot name="empty">
                                <p class="font-medium text-ink">No AI generation attempts yet.</p>
                            </x-slot>
                        </x-table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-ink">Details</h2>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-ink-faint">Website</dt>
                            <dd class="mt-0.5">
                                <a class="text-brand-600 hover:text-brand-700" href="{{ route('websites.show', $post->website) }}">
                                    {{ $post->website->name }}
                                </a>
                            </dd>
                        </div>

                        @if ($post->topic)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">Topic</dt>
                                <dd class="mt-0.5 text-ink">{{ $post->topic }}</dd>
                            </div>
                        @endif

                        @if ($post->category)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">Category</dt>
                                <dd class="mt-0.5 text-ink">{{ $post->category }}</dd>
                            </div>
                        @endif

                        @if ((array) $post->tags !== [])
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">Tags</dt>
                                <dd class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ((array) $post->tags as $tag)
                                        <span class="rounded-full bg-surface-muted px-2 py-0.5 text-xs text-ink-muted">{{ $tag }}</span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif

                        @if ((array) $post->keywords !== [])
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">Focus keywords</dt>
                                <dd class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ((array) $post->keywords as $keyword)
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-700">{{ $keyword }}</span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif

                        @if ($post->meta_description)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">Meta description</dt>
                                <dd class="mt-0.5 text-ink">{{ $post->meta_description }}</dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-xs uppercase tracking-wide text-ink-faint">Schedule</dt>
                            <dd class="mt-0.5 text-ink">
                                @if ($post->scheduled_at)
                                    {{ $post->scheduledAtSiteTime()->format('M j, Y H:i') }}
                                    <span class="text-xs text-ink-faint">website time</span>
                                @else
                                    <span class="text-ink-faint">Not scheduled</span>
                                @endif
                            </dd>
                        </div>

                        @if ($post->published_at)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">Published</dt>
                                <dd class="mt-0.5 text-ink">
                                    {{ $post->publishedAtSiteTime()->format('M j, Y H:i') }}
                                    <span class="text-xs text-ink-faint">website time</span>
                                </dd>
                            </div>
                        @endif

                        @if ($post->wordpress_url)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">On WordPress</dt>
                                <dd class="mt-0.5 break-all">
                                    <a class="text-brand-600 hover:text-brand-700" href="{{ $post->wordpress_url }}" target="_blank" rel="noopener">
                                        {{ $post->wordpress_url }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        @if ($post->ai_provider)
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-ink-faint">AI</dt>
                                <dd class="mt-0.5 text-ink">
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
