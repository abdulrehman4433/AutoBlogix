<x-app-layout title="Edit post">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Edit post</h1>
            <p class="mt-1 text-sm text-gray-500 truncate">{{ $post->title }}</p>
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
