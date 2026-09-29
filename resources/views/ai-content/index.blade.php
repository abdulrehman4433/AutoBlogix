<x-app-layout title="AI Content">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">AI Content</h1>
            <p class="mt-1 text-sm text-gray-500">
                Describe what you want, and AutoBlogix writes a ready-to-edit draft with your
                chosen tone and length — using your configured AI provider, or the built-in
                development provider when none is set.
            </p>
        </div>

        @if ($websites->isEmpty())
            <x-alert type="info" title="Add a website first">
                AI-generated posts belong to a WordPress site, so you need at least one
                website before creating content.
                <p class="mt-3">
                    <x-button size="sm" :href="route('websites.create')">Add website</x-button>
                </p>
            </x-alert>
        @else
            <form method="POST" action="{{ route('ai.store') }}" class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                @csrf
                <div class="space-y-5">
                    <x-select
                        name="website_id"
                        label="Website"
                        :options="$websites->mapWithKeys(fn ($website) => [$website->id => $website->name])->all()"
                        :selected="old('website_id')"
                        placeholder="Choose a website"
                        required
                        hint="Where this post will be published."
                    />

                    <x-input
                        name="title"
                        label="Title"
                        :value="old('title')"
                        required
                        maxlength="200"
                        placeholder="How to plan your week"
                        hint="The AI writes around this title."
                    />

                    <x-input
                        name="topic"
                        label="Topic"
                        :value="old('topic')"
                        maxlength="200"
                        placeholder="Focus theme for the article"
                        hint="Optional. Steers the angle of the generated content."
                    />

                    <x-input
                        name="keywords"
                        label="Focus keywords"
                        :value="old('keywords')"
                        placeholder="weekly planner, productivity"
                        hint="Comma separated (up to 10)."
                    />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-select
                            name="tone"
                            label="Tone"
                            :options="$tones"
                            :selected="old('tone', 'professional')"
                            required
                        />
                        <x-select
                            name="length"
                            label="Length"
                            :options="$lengths"
                            :selected="old('length', 'medium')"
                            required
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <x-button variant="primary" type="submit">Generate draft</x-button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
