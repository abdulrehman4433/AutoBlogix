<x-app-layout
    title="Edit post"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Posts', 'href' => route('posts.index')],
        ['label' => $post->title, 'href' => route('posts.show', $post)],
        ['label' => 'Edit post'],
    ]"
>
    <div class="mx-auto max-w-3xl space-y-6 animate-fade-in">
        <div>
            <h1 class="text-lg font-semibold text-ink">Edit post</h1>
            <p class="mt-1 text-sm text-ink-muted truncate">{{ $post->title }}</p>
        </div>

        @include('posts.partials.form', [
            'action' => route('posts.update', $post),
            'method' => 'PUT',
            'websites' => $websites,
            'post' => $post,
            'submitLabel' => 'Save changes',
        ])
    </div>
</x-app-layout>
