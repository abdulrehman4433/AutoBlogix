@php
    /**
     * Shared create/edit form.
     *
     * @var string $action
     * @var string $method POST|PUT|PATCH
     * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Website> $websites
     * @var \App\Models\BlogPost|null $post
     * @var string $submitLabel
     */
    $post = $post ?? null;

    // Schedules only belong to posts that have not been handed to
    // WordPress yet (Phase 5 owns publishing statuses).
    $canSchedule = $post === null
        || in_array($post->status->value, ['draft', 'scheduled'], true);

    $scheduledValue = old(
        'scheduled_at',
        $post?->scheduledAtSiteTime()?->format('Y-m-d\TH:i') ?? '',
    );
@endphp

<form method="POST" action="{{ $action }}" class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="space-y-5">
        <x-select
            name="website_id"
            label="Website"
            :options="$websites->mapWithKeys(fn ($website) => [$website->id => $website->name])->all()"
            :selected="old('website_id', $post?->website_id)"
            placeholder="Choose a website"
            required
            hint="Where this post will be published."
        />

        <x-input
            name="title"
            label="Title"
            :value="old('title', $post?->title)"
            required
            maxlength="200"
            placeholder="How to plan your week"
        />

        <x-input
            name="topic"
            label="Topic"
            :value="old('topic', $post?->topic)"
            maxlength="200"
            placeholder="Internal topic or keyword theme"
            hint="Optional. Used to group and later to generate related content."
        />

        <x-textarea
            name="excerpt"
            label="Excerpt"
            :value="old('excerpt', $post?->excerpt)"
            rows="3"
            hint="Short summary shown in listings (and as the WordPress excerpt)."
        />

        <x-textarea
            name="content"
            label="Content"
            :value="old('content', $post?->content)"
            rows="14"
            hint="Plain text or HTML. This is what gets sent to WordPress when the post is published."
        />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input
                name="category"
                label="Category"
                :value="old('category', $post?->category)"
                maxlength="100"
                placeholder="News"
            />

            <x-input
                name="tags"
                label="Tags"
                :value="old('tags', $post ? implode(', ', (array) ($post->tags ?? [])) : '')"
                placeholder="launch, tutorial"
                hint="Comma separated."
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-input
                name="keywords"
                label="Focus keywords"
                :value="old('keywords', $post ? implode(', ', (array) ($post->keywords ?? [])) : '')"
                placeholder="weekly planner, productivity"
                hint="Comma separated."
            />

            <x-input
                name="meta_description"
                label="Meta description"
                :value="old('meta_description', $post?->meta_description)"
                maxlength="300"
                hint="Shown by search engines (max 300 characters)."
            />
        </div>

        <x-input
            name="scheduled_at"
            label="Publish at"
            type="datetime-local"
            :value="$scheduledValue"
            :disabled="! $canSchedule"
            hint="{{ $canSchedule
                ? 'In the website’s timezone. Leave empty to keep as a draft.'
                : 'This post’s schedule can no longer be changed.' }}"
        />
    </div>

    <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
        <x-button variant="secondary" :href="$post ? route('posts.show', $post) : route('posts.index')">Cancel</x-button>
        <x-button variant="primary" type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
