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
    <body class="h-full font-sans text-gray-900 antialiased">
        <div class="flex min-h-full flex-col items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-indigo-600 text-lg font-bold text-white" aria-hidden="true">A</span>
                        <span class="text-2xl font-semibold tracking-tight text-gray-900">{{ config('app.name') }}</span>
                    </a>
                    <p class="text-sm text-gray-500">Manage and publish to all your WordPress sites.</p>
                </div>

                <div class="bg-white px-6 py-8 shadow-sm ring-1 ring-gray-200 sm:rounded-xl">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
