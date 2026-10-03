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
        <div class="relative flex min-h-full flex-col items-center justify-center overflow-hidden bg-canvas px-4 py-12 sm:px-6 lg:px-8">
            <!-- Decorative brand glows -->
            <div class="pointer-events-none absolute -top-32 end-[-6rem] size-72 rounded-full bg-brand-gradient opacity-20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute bottom-[-7rem] start-[-6rem] size-72 rounded-full bg-accent-gradient opacity-20 blur-3xl" aria-hidden="true"></div>

            <div class="relative w-full max-w-md">
                <div class="mb-8 flex flex-col items-center gap-3">
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-gradient text-lg font-bold text-white shadow-glow" aria-hidden="true">A</span>
                        <span class="text-2xl font-semibold tracking-tight text-ink">{{ config('app.name') }}</span>
                    </a>
                    <p class="text-sm text-ink-muted">Manage and publish to all your WordPress sites.</p>
                </div>

                <div class="card px-6 py-8 sm:rounded-2xl fade-up">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
