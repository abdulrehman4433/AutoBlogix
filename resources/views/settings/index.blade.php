<x-app-layout
    title="Settings"
    :breadcrumb="[
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'home'],
        ['label' => 'Settings'],
    ]"
>
    <div class="space-y-6 animate-fade-in">
        <div>
            <h1 class="text-lg font-semibold text-ink">Settings</h1>
            <p class="mt-1 text-sm text-ink-muted">Account, AI prompt template, and system information.</p>
        </div>

        {{-- Account --}}
        <div class="card p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Account</h2>
                    <dl class="mt-3 space-y-1 text-sm text-ink-muted">
                        <div class="flex gap-2">
                            <dt class="w-24 text-ink-faint">Name</dt>
                            <dd class="font-medium text-ink">{{ $account->name }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-24 text-ink-faint">Email</dt>
                            <dd>{{ $account->email }}</dd>
                        </div>
                    </dl>
                </div>
                <x-button variant="secondary" :href="route('profile.edit')">Edit profile</x-button>
            </div>
            <p class="mt-4 text-xs text-ink-muted">
                Change your name, email, or password on the profile page.
            </p>
        </div>

        {{-- AI prompt template --}}
        <div class="card p-6">
            <h2 class="text-sm font-semibold text-ink">AI prompt template</h2>
            <p class="mt-1 text-sm text-ink-muted">
                The system and user prompt used by <em>Blog post generation</em>.
                Templates are global (shared by every account); until you save,
                the built-in defaults from <code class="rounded bg-surface-muted px-1 text-ink">config/ai.php</code> apply.
            </p>

            <form method="POST" action="{{ route('settings.prompts.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')

                <x-textarea
                    name="system_prompt"
                    label="System prompt"
                    :value="old('system_prompt', $prompt['system'])"
                    rows="5"
                    hint="Sets the assistant's role and output rules."
                />

                <x-textarea
                    name="user_prompt"
                    label="User prompt"
                    :value="old('user_prompt', $prompt['user'])"
                    rows="9"
                    hint="Placeholders substituted at generation time: :title, :topic, :keywords, :tone, :length_words."
                />

                <div class="flex items-center gap-3">
                    <x-button variant="primary" type="submit">Save template</x-button>
                    <x-button variant="secondary" x-on:click="$dispatch('open-modal', 'confirm-prompt-reset')">
                        Reset to default
                    </x-button>
                </div>
            </form>
        </div>

        {{-- System --}}
        <div class="card p-6">
            <h2 class="text-sm font-semibold text-ink">System</h2>
            <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                @foreach ($system as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-line pb-2">
                        <dt class="text-ink-muted">{{ $label }}</dt>
                        <dd class="text-right font-medium text-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-xs text-ink-muted">
                Secrets (API keys, database passwords) are never shown here.
            </p>
        </div>

        <x-modal name="confirm-prompt-reset" title="Reset the prompt template?" max-width="md">
            <p>
                The saved template is deleted and AI generation falls back to the
                built-in default. You can save your own text again afterwards.
            </p>
            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button variant="secondary" x-on:click="$dispatch('close-modal')">Cancel</x-button>
                    <form method="POST" action="{{ route('settings.prompts.reset') }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit">Reset to default</x-button>
                    </form>
                </div>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
