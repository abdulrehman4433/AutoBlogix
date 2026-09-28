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
        <div x-data="{ mobileOpen: false }" @close-navigation.window="mobileOpen = false" class="min-h-full bg-gray-50">
            <!-- Desktop sidebar -->
            <div class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-gray-900 lg:flex">
                <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-800 px-4">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white" aria-hidden="true">A</span>
                    <span class="text-lg font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
                </div>

                <nav class="flex flex-1 flex-col overflow-y-auto px-3 py-4" aria-label="Main navigation">
                    @include('layouts.navigation')
                </nav>

                <div class="border-t border-gray-800 px-4 py-3">
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
                    class="fixed inset-0 bg-gray-900/60"
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
                        class="relative flex w-64 flex-col bg-gray-900"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Navigation"
                    >
                        <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-800 px-4">
                            <div class="flex items-center gap-2">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white" aria-hidden="true">A</span>
                                <span class="text-lg font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
                            </div>
                            <button
                                type="button"
                                class="rounded-md p-1.5 text-gray-400 hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                aria-label="Close navigation"
                                @click="mobileOpen = false"
                            >
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
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
                <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">
                    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 lg:hidden"
                                aria-label="Open navigation"
                                @click="mobileOpen = true"
                            >
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                            </button>

                            <h1 class="truncate text-lg font-semibold text-gray-900">
                                {{ $title ?? config('app.name', 'AutoBlogix') }}
                            </h1>
                        </div>

                        <div class="flex items-center gap-3">
                            @auth
                                <x-dropdown align="right" width="48">
                                    <x-slot name="trigger">
                                        <button
                                            type="button"
                                            class="inline-flex max-w-[12rem] items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-gray-600 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        >
                                            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold uppercase text-indigo-700">
                                                {{ substr(auth()->user()->name, 0, 1) }}
                                            </span>
                                            <span class="truncate">{{ auth()->user()->name }}</span>
                                            <svg class="size-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <div class="px-4 py-2">
                                            <p class="truncate text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                            <p class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</p>
                                        </div>
                                        <div class="border-t border-gray-100">
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

                    @isset($header)
                        <div class="border-t border-gray-100 bg-white">
                            <div class="px-4 py-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </div>
                    @endisset
                </header>

                <!-- Flash messages -->
                <div class="space-y-3 px-4 pt-4 sm:px-6 lg:px-8">
                    @if (session('success'))
                        <x-alert type="success" :dismissible="true">{{ session('success') }}</x-alert>
                    @endif

                    @if (session('error'))
                        <x-alert type="error" :dismissible="true">{{ session('error') }}</x-alert>
                    @endif
                </div>

                <!-- Page content -->
                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
