@php
    /**
     * Main navigation. Items appear once their module route exists,
     * so the shell works from Phase 0 onward without dead links.
     *
     * @var array<int, array{label: string, route: string, icon: string}>
     */
    $navigation = [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'home',
        ],
        [
            'label' => 'Websites',
            'route' => 'websites.index',
            'icon' => 'globe',
        ],
        [
            'label' => 'Posts',
            'route' => 'posts.index',
            'icon' => 'document-text',
        ],
        [
            'label' => 'Schedules',
            'route' => 'schedules.index',
            'icon' => 'calendar',
        ],
        [
            'label' => 'AI Content',
            'route' => 'ai.generate',
            'icon' => 'sparkles',
        ],
        [
            'label' => 'AI Providers',
            'route' => 'ai.providers',
            'icon' => 'desktop',
        ],
        [
            'label' => 'Logs',
            'route' => 'logs.index',
            'icon' => 'list-bullet',
        ],
        [
            'label' => 'Settings',
            'route' => 'settings.index',
            'icon' => 'cog',
        ],
    ];

    $visibleNavigation = array_values(array_filter(
        $navigation,
        fn (array $item): bool => Route::has($item['route'])
    ));
@endphp

<ul class="space-y-1">
    @foreach ($visibleNavigation as $item)
        @php
            $isActive = request()->routeIs($item['route']);
        @endphp
        <li>
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition duration-150',
                    'bg-brand-gradient font-semibold text-white shadow-glow' => $isActive,
                    'font-medium text-gray-400 hover:bg-white/10 hover:text-white' => ! $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                <x-icon
                    :name="$item['icon']"
                    :class="$isActive ? 'shrink-0 text-white' : 'shrink-0 text-gray-400 group-hover:text-white'"
                />
                {{ $item['label'] }}
            </a>
        </li>
    @endforeach
</ul>
