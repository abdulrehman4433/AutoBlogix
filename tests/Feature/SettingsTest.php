<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ------------------------------------------------------------------
    // Access & page
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_from_the_settings_pages(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
        $this->patch(route('settings.prompts.update'), $this->validPayload())
            ->assertRedirect(route('login'));
        $this->delete(route('settings.prompts.reset'))->assertRedirect(route('login'));
    }

    public function test_index_renders_account_the_config_prompt_fallback_and_system_info(): void
    {
        $systemDefault = (string) config('ai.defaults.blog_post_generation.system');

        $this->actingAs($this->user)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee($this->user->name)
            ->assertSee($this->user->email)
            ->assertSee('Edit profile')
            ->assertSee(route('profile.edit'))
            ->assertSee('Save template')
            ->assertSee('Reset to default')
            ->assertSee($systemDefault) // pre-filled from config before any row exists
            ->assertSee('Queue connection')
            ->assertSee((string) config('queue.default'));

        $this->assertSame(0, PromptTemplate::query()->count());
    }

    // ------------------------------------------------------------------
    // Save
    // ------------------------------------------------------------------

    public function test_saving_the_prompt_creates_exactly_one_row_and_resolves_the_new_text(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.prompts.update'), $this->validPayload([
                'system_prompt' => 'You are a terse writer.',
                'user_prompt' => 'Write about :title for :topic.',
            ]))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success', 'Prompt template saved. AI generation now uses this text.');

        $this->assertSame(1, PromptTemplate::query()->count());

        $row = PromptTemplate::query()->sole();
        $this->assertSame('blog_post_generation', $row->key);
        $this->assertSame('You are a terse writer.', $row->system_prompt);
        $this->assertSame('Write about :title for :topic.', $row->user_prompt);
        $this->assertNotNull($row->name); // name falls back to config, never null

        $resolved = PromptTemplate::resolve('blog_post_generation');
        $this->assertSame('You are a terse writer.', $resolved['system']);
        $this->assertSame('Write about :title for :topic.', $resolved['user']);

        // The page renders the saved text instead of the config default.
        $this->actingAs($this->user)
            ->get(route('settings.index'))
            ->assertSee('You are a terse writer.');

        // A second save edits the same row — never a duplicate.
        $this->actingAs($this->user)
            ->patch(route('settings.prompts.update'), $this->validPayload([
                'system_prompt' => 'Second version.',
                'user_prompt' => 'Second user prompt.',
            ]))
            ->assertRedirect(route('settings.index'));

        $this->assertSame(1, PromptTemplate::query()->count());
        $this->assertSame('Second version.', PromptTemplate::resolve('blog_post_generation')['system']);
    }

    public function test_validation_rejects_bad_input_without_writing(): void
    {
        $this->actingAs($this->user)
            ->from(route('settings.index'))
            ->patch(route('settings.prompts.update'), $this->validPayload(['system_prompt' => '']))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('system_prompt');

        $this->actingAs($this->user)
            ->patch(route('settings.prompts.update'), $this->validPayload(['user_prompt' => '']))
            ->assertSessionHasErrors('user_prompt');

        $this->actingAs($this->user)
            ->patch(route('settings.prompts.update'), $this->validPayload([
                'system_prompt' => str_repeat('a', 12001),
            ]))
            ->assertSessionHasErrors('system_prompt');

        $this->assertSame(0, PromptTemplate::query()->count());
    }

    // ------------------------------------------------------------------
    // Reset
    // ------------------------------------------------------------------

    public function test_reset_deletes_the_row_and_falls_back_to_the_config_prompt(): void
    {
        $this->actingAs($this->user)
            ->patch(route('settings.prompts.update'), $this->validPayload())
            ->assertRedirect(route('settings.index'));

        $this->assertSame(1, PromptTemplate::query()->count());

        $this->actingAs($this->user)
            ->delete(route('settings.prompts.reset'))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success', 'Prompt template reset to the built-in default.');

        $this->assertSame(0, PromptTemplate::query()->count());

        $systemDefault = (string) config('ai.defaults.blog_post_generation.system');
        $this->assertSame($systemDefault, PromptTemplate::resolve('blog_post_generation')['system']);

        $this->actingAs($this->user)
            ->get(route('settings.index'))
            ->assertSee($systemDefault);
    }

    // ------------------------------------------------------------------

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'system_prompt' => 'You are a blog writer. Respond with JSON only.',
            'user_prompt' => 'Write a post titled :title (tone: :tone).',
            ...$overrides,
        ];
    }
}
