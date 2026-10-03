<x-app-layout
    title="New post"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Posts', 'href' => route('posts.index')],
        ['label' => 'New post'],
    ]"
>
    <div class="mx-auto max-w-3xl space-y-6 animate-fade-in">
        <div>
            <h1 class="text-lg font-semibold text-ink">New post</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Write a post manually, then schedule or publish it to one of your WordPress sites.
            </p>
        </div>

        @if ($websites->isEmpty())
            <x-alert type="info" title="Add a website first">
                Posts are published to a WordPress site, so you need at least one website
                before creating a post.
                <p class="mt-3">
                    <x-button size="sm" :href="route('websites.create')">Add website</x-button>
                </p>
            </x-alert>
        @else
            @include('posts.partials.form', [
                'action' => route('posts.store'),
                'method' => 'POST',
                'websites' => $websites,
                'post' => null,
                'submitLabel' => 'Create post',
            ])
        @endif
    </div>
</x-app-layout>
