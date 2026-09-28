<x-app-layout title="Add website">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Add website</h1>
            <p class="mt-1 text-sm text-gray-500">
                Register a WordPress site. You will receive an API key and secret
                to enter in the AutoBlogix WordPress plugin.
            </p>
        </div>

        <form method="POST" action="{{ route('websites.store') }}" class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            @csrf

            <div class="space-y-5">
                <x-input
                    name="name"
                    label="Website name"
                    :value="old('name')"
                    required
                    placeholder="My Blog"
                    autocomplete="organization"
                />

                <x-input
                    name="url"
                    label="Website URL"
                    :value="old('url')"
                    type="url"
                    required
                    placeholder="https://example.com"
                    hint="The public address of your WordPress site (subdirectory installs are supported)."
                />

                <x-select
                    name="timezone"
                    label="Timezone"
                    :options="collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn (string $tz) => [$tz => $tz])->all()"
                    :selected="old('timezone', 'UTC')"
                    required
                    hint="Used when this site's schedules are evaluated."
                />
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <x-button variant="secondary" :href="route('websites.index')">Cancel</x-button>
                <x-button variant="primary" type="submit">Save and get credentials</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
