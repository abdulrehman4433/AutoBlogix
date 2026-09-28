<x-app-layout title="Edit website">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Edit website</h1>
            <p class="mt-1 text-sm text-gray-500">
                Updating these details does not change your API credentials or connection status.
            </p>
        </div>

        <form method="POST" action="{{ route('websites.update', $website) }}" class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <x-input
                    name="name"
                    label="Website name"
                    :value="old('name', $website->name)"
                    required
                    placeholder="My Blog"
                    autocomplete="organization"
                />

                <x-input
                    name="url"
                    label="Website URL"
                    :value="old('url', $website->url)"
                    type="url"
                    required
                    placeholder="https://example.com"
                    hint="The public address of your WordPress site (subdirectory installs are supported)."
                />

                <x-select
                    name="timezone"
                    label="Timezone"
                    :options="collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn (string $tz) => [$tz => $tz])->all()"
                    :selected="old('timezone', $website->timezone)"
                    required
                    hint="Used when this site's schedules are evaluated."
                />
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <x-button variant="secondary" :href="route('websites.show', $website)">Cancel</x-button>
                <x-button variant="primary" type="submit">Save changes</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
