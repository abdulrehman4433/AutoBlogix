<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'AutoBlogix') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased">
        <div x-data="{ mobileOpen: false }" @close-navigation.window="mobileOpen = false" class="min-h-full bg-canvas">
            <!-- Desktop sidebar -->
            <div class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/10 bg-gray-900/90 backdrop-blur-xl lg:flex">
                <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-white/10 px-4">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-brand-gradient text-sm font-bold text-white shadow-glow" aria-hidden="true">A</span>
                    <span class="text-lg font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
                </div>

                <nav class="flex flex-1 flex-col overflow-y-auto px-3 py-4" aria-label="Main navigation">
                    @include('layouts.navigation')
                </nav>

                <div class="border-t border-white/10 px-4 py-3">
                    <p class="text-xs text-gray-500">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
                </div>
            </div>

            <!-- Mobile sidebar -->
            <div x-show="mobileOpen" x-cloak class="relative z-50 lg:hidden">
                <div
                    x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
                    @click="mobileOpen = false"
                    aria-hidden="true"
                ></div>

                <div class="fixed inset-0 z-50 flex">
                    <div
                        x-transition:enter="ease-out duration-200"
                        x-transition:enter-start="-translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="ease-in duration-150"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="-translate-x-full"
                        class="relative flex w-64 flex-col border-r border-white/10 bg-gray-900/95 backdrop-blur-xl"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Navigation"
                    >
                        <div class="flex h-16 shrink-0 items-center justify-between border-b border-white/10 px-4">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-gradient text-sm font-bold text-white shadow-glow" aria-hidden="true">A</span>
                                <span class="text-lg font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                                aria-label="Close navigation"
                                @click="mobileOpen = false"
                            >
                                <x-icon name="x-mark" size="lg" />
                            </button>
                        </div>

                        <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Main navigation">
                            @include('layouts.navigation')
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Main column -->
            <div class="flex min-h-screen flex-col lg:pl-64">
                <!-- Top bar -->
                <header class="sticky top-0 z-30 border-b border-line bg-white/80 backdrop-blur-xl">
                    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-ink-muted transition hover:bg-black/5 hover:text-ink focus:outline-none focus:ring-2 focus:ring-brand-500 lg:hidden"
                                aria-label="Open navigation"
                                @click="mobileOpen = true"
                            >
                                <x-icon name="bars-3" size="lg" />
                            </button>

                            <h1 class="truncate text-lg font-semibold text-ink">
                                {{ $title ?? config('app.name', 'AutoBlogix') }}
                            </h1>
                        </div>

                        <div class="flex items-center gap-3">
                            @auth
                                <x-dropdown align="right" width="48">
                                    <x-slot name="trigger">
                                        <button
                                            type="button"
                                            class="inline-flex max-w-[12rem] items-center gap-2 rounded-full bg-white/70 px-3 py-1.5 text-sm font-medium text-ink ring-1 ring-line transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                        >
                                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-xs font-semibold uppercase text-white">
                                                {{ substr(auth()->user()->name, 0, 1) }}
                                            </span>
                                            <span class="truncate">{{ auth()->user()->name }}</span>
                                            <x-icon name="chevron-down" size="sm" class="shrink-0 text-gray-400" />
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <div class="px-4 py-2">
                                            <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                                            <p class="truncate text-xs text-ink-muted">{{ auth()->user()->email }}</p>
                                        </div>
                                        <div class="border-t border-line">
                                            <x-dropdown-link :href="route('profile.edit')">
                                                Profile settings
                                            </x-dropdown-link>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <x-dropdown-link
                                                    :href="route('logout')"
                                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                                >
                                                    Log out
                                                </x-dropdown-link>
                                            </form>
                                        </div>
                                    </x-slot>
                                </x-dropdown>
                            @endauth
                        </div>
                    </div>

                    @if ($breadcrumb !== null && $breadcrumb !== [])
                        <div class="px-4 pb-3 sm:px-6 lg:px-8">
                            <x-breadcrumb :items="$breadcrumb" />
                        </div>
                    @endif

                    @isset($header)
                        <div class="border-t border-line">
                            <div class="px-4 py-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </div>
                    @endisset
                </header>

                <!-- Flash messages (auto-dismiss toasts) -->
                @if (session('success'))
                    <x-toast type="success" :message="session('success')" />
                @endif

                @if (session('error'))
                    <x-toast type="error" :message="session('error')" />
                @endif

                {{-- Validation failures land here via redirect()->back() — scan every
                     bag, because validateWithBag() errors skip the default one. --}}
                @php($firstError = collect($errors->getBags())->flatMap->all()->first())
                @if ($firstError !== null && $firstError !== '')
                    <x-toast type="error" :message="$firstError" />
                @endif

                <!-- Page content -->
                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
