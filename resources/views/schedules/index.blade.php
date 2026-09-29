<x-app-layout title="Schedules">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">Schedules</h1>
                <p class="mt-1 text-sm text-gray-500">Posts publish automatically when their time arrives.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('schedules.run') }}">
                    @csrf
                    <x-button variant="secondary" type="submit">Run scheduler now</x-button>
                </form>
                <x-button :href="route('posts.index')">All posts</x-button>
            </div>
        </div>

        <x-alert type="info" title="How automatic publishing runs">
            Every minute, the scheduler
            (<code class="rounded bg-sky-100 px-1">php artisan schedule:run</code> via cron, or
            <code class="rounded bg-sky-100 px-1">php artisan schedule:work</code> in development)
            queues every post whose time has arrived, and the queue worker
            (<code class="rounded bg-sky-100 px-1">php artisan queue:work</code>) then publishes it.
            Posts missed while the server was down publish on the next run.
        </x-alert>

        @if ($overdue->isNotEmpty())
            <x-alert type="warning" title="{{ $overdue->count() }} overdue post(s)">
                Their scheduled time has already passed. They publish on the next scheduler run
                once the queue worker is active — or publish one right away from the table below.
            </x-alert>
        @endif

        @php
            /** @var \Illuminate\Support\Collection $rows */
            $rows = $overdue->concat($upcoming);
        @endphp

        <x-table :columns="4" :isEmpty="$rows->isEmpty()">
            <x-slot name="head">
                <th scope="col">Title</th>
                <th scope="col">Website</th>
                <th scope="col">Publish at</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </x-slot>

            <x-slot name="body">
                @foreach ($rows as $post)
                    @php
                        $isOverdue = $post->scheduled_at->lessThan(now());
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="max-w-xs">
                            <a class="block truncate font-medium text-gray-900 hover:text-indigo-600" href="{{ route('posts.show', $post) }}">
                                {{ $post->title }}
                            </a>
                            @if ($post->topic)
                                <span class="block truncate text-xs text-gray-500">{{ $post->topic }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-gray-600">
                            <a class="hover:text-indigo-600" href="{{ route('websites.show', $post->website) }}">
                                {{ $post->website->name }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap text-sm text-gray-600">
                            <span class="mr-1">{{ $post->scheduledAtSiteTime()->format('M j, Y H:i') }}</span>
                            <span class="text-xs text-gray-400">site time</span>
                            @if ($isOverdue)
                                <x-badge variant="amber">Overdue</x-badge>
                                <span class="block text-xs text-gray-400">{{ $post->scheduled_at->diffForHumans() }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-2">
                                <x-button size="sm" variant="secondary" :href="route('posts.show', $post)">View</x-button>
                                <form method="POST" action="{{ route('posts.publish', $post) }}">
                                    @csrf
                                    <x-button size="sm" variant="secondary" type="submit">Publish now</x-button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-slot>

            <x-slot name="empty">
                <p class="font-medium text-gray-900">Nothing scheduled yet.</p>
                <p class="mt-1">Set a publish time on a post and it will publish automatically.</p>
                <p class="mt-4">
                    <x-button :href="route('posts.create')">New post</x-button>
                </p>
            </x-slot>
        </x-table>
    </div>
</x-app-layout>
