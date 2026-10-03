<x-app-layout
    title="Edit website"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Websites', 'href' => route('websites.index')],
        ['label' => $website->name, 'href' => route('websites.show', $website)],
        ['label' => 'Edit website'],
    ]"
>
    <div class="mx-auto max-w-2xl space-y-6 animate-fade-in">
        <div>
            <h1 class="text-lg font-semibold text-ink">Edit website</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Updating these details does not change your API credentials or connection status.
            </p>
        </div>

        <form method="POST" action="{{ route('websites.update', $website) }}" class="card p-6">
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

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-line pt-5">
                <x-button variant="secondary" :href="route('websites.show', $website)">Cancel</x-button>
                <x-button variant="primary" type="submit">Save changes</x-button>
            </div>
        </form>
    </div>
</x-app-layout>
