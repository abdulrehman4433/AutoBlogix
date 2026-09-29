<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AiLogStatus;
use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Jobs\PublishPostJob;
use App\Models\AiLog;
use App\Models\AiProvider;
use App\Models\BlogPost;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->website = Website::factory()->create(['user_id' => $this->user->id]);
    }

    // ------------------------------------------------------------------
    // Access, navigation, composer
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_and_navigation_shows_ai_links(): void
    {
        $this->get(route('ai.generate'))->assertRedirect(route('login'));
        $this->get(route('ai.providers'))->assertRedirect(route('login'));

        $page = $this->actingAs($this->user)->get(route('dashboard'));
        $page->assertOk()->assertSee('AI Content')->assertSee('AI Providers');
    }

    public function test_composer_shows_add_website_cta_without_websites(): void
    {
        $lonely = User::factory()->create();

        $this->actingAs($lonely)
            ->get(route('ai.generate'))
            ->assertOk()
            ->assertSee('Add a website first');

        $this->actingAs($this->user)
            ->get(route('ai.generate'))
            ->assertOk()
            ->assertSee('Generate draft')
            ->assertDontSee('Add a website first');
    }

    public function test_composer_creates_ai_draft_and_generates_with_development_provider(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.store'), [
            'website_id' => $this->website->id,
            'title' => 'How to plan your week',
            'topic' => 'weekly planning',
            'keywords' => 'weekly planner, productivity',
            'tone' => 'professional',
            'length' => 'medium',
        ]);

        $post = BlogPost::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('posts.show', $post))->assertSessionHas('success');

        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertSame(PostSource::Ai, $post->source);
        $this->assertNotNull($post->content);
        $this->assertStringContainsString('<h2>', $post->content);
        $this->assertNotNull($post->excerpt);
        $this->assertNotNull($post->meta_description);
        $this->assertSame(['weekly planner', 'productivity'], $post->keywords);
        $this->assertSame('development', $post->ai_provider);
        $this->assertNull($post->ai_model);

        $log = $post->aiLogs()->sole();
        $this->assertSame(AiLogStatus::Success, $log->status);
        $this->assertSame('blog_post_generation', $log->prompt_key);
        $this->assertSame('development', $log->provider);
        $this->assertNull($log->tokens_used);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->completed_at);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('AI generation history')
            ->assertSee('development', false);
    }

    public function test_composer_validation_rejects_foreign_website_and_bad_input(): void
    {
        $foreign = Website::factory()->create();

        $this->actingAs($this->user)
            ->post(route('ai.store'), [
                'website_id' => $foreign->id,
                'title' => 'Sneaky post',
                'tone' => 'professional',
                'length' => 'medium',
            ])
            ->assertSessionHasErrors('website_id');

        $this->actingAs($this->user)
            ->post(route('ai.store'), [
                'website_id' => $this->website->id,
                'tone' => 'professional',
                'length' => 'medium',
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($this->user)
            ->post(route('ai.store'), [
                'website_id' => $this->website->id,
                'title' => 'Tone check',
                'tone' => 'sarcastic',
                'length' => 'medium',
            ])
            ->assertSessionHasErrors('tone');

        $this->assertSame(0, BlogPost::query()->count());
    }

    // ------------------------------------------------------------------
    // Provider failure paths (real OpenAI code path, faked HTTP)
    // ------------------------------------------------------------------

    public function test_provider_failure_restores_draft_and_records_failed_log_with_friendly_flash(): void
    {
        AiProvider::factory()->create([
            'user_id' => $this->user->id,
            'api_key' => 'sk-live-1234567890abcd',
        ]);

        Http::fake(['*' => Http::response('{"error":"invalid key"}', 401)]);

        $response = $this->actingAs($this->user)->post(route('ai.store'), [
            'website_id' => $this->website->id,
            'title' => 'Doomed post',
            'tone' => 'friendly',
            'length' => 'short',
        ]);

        $response->assertSessionHas(
            'error',
            'The AI provider rejected the API key. Check it on the AI Providers page.',
        );

        $post = BlogPost::query()->sole();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->content);
        $this->assertNull($post->ai_provider);

        $log = $post->aiLogs()->sole();
        $this->assertSame(AiLogStatus::Failed, $log->status);
        $this->assertSame('openai', $log->provider);
        $this->assertStringContainsString('HTTP 401', (string) $log->error_message);

        // The technical detail must not reach the UI.
        $this->assertStringNotContainsString('HTTP 401', (string) session('error'));
    }

    public function test_garbage_provider_response_yields_friendly_format_error(): void
    {
        AiProvider::factory()->create(['user_id' => $this->user->id]);

        Http::fake(['*' => Http::response('<html>oops</html>', 200)]);

        $this->actingAs($this->user)
            ->post(route('posts.generate', $this->draftPost(['status' => PostStatus::Draft])))
            ->assertSessionHas('error', 'The AI provider returned an unexpected response.');

        $post = BlogPost::query()->latest('id')->firstOrFail();
        $this->assertSame(PostStatus::Draft, $post->status);

        $log = $post->aiLogs()->sole();
        $this->assertSame(AiLogStatus::Failed, $log->status);
        $this->assertStringContainsString('HTTP 200', (string) $log->error_message);
    }

    public function test_fenced_model_output_is_parsed_and_request_carries_prompt_and_auth(): void
    {
        AiProvider::factory()->create([
            'user_id' => $this->user->id,
            'model' => 'gpt-test',
            'api_key' => 'sk-test-key',
        ]);

        $fenced = "```json\n".json_encode([
            'content' => '<p>Fenced body text.</p>',
            'excerpt' => 'Fenced excerpt.',
            'tags' => ['alpha', 'beta'],
            'keywords' => ['kw-one', 'kw-two'],
            'meta_description' => 'Fenced meta.',
        ])."\n```";

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => $fenced]]],
            'usage' => ['total_tokens' => 4321],
        ], 200)]);

        $post = $this->draftPost([
            'title' => 'Sign post title',
            'keywords' => ['kw-one', 'kw-two'],
        ]);

        $this->actingAs($this->user)
            ->post(route('posts.generate', $post))
            ->assertSessionHas('success');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/chat/completions')
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === 'Bearer sk-test-key'
                && $data['model'] === 'gpt-test'
                && str_contains($data['messages'][0]['content'], 'JSON object')
                && str_contains($data['messages'][1]['content'], 'Sign post title')
                && str_contains($data['messages'][1]['content'], 'kw-one, kw-two')
                && str_contains($data['messages'][1]['content'], 'professional')
                && str_contains($data['messages'][1]['content'], '800');
        });

        $post->refresh();
        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertStringContainsString('Fenced body text.', (string) $post->content);
        $this->assertSame('openai', $post->ai_provider);
        $this->assertSame('gpt-test', $post->ai_model);

        $log = $post->aiLogs()->sole();
        $this->assertSame(AiLogStatus::Success, $log->status);
        $this->assertSame(4321, $log->tokens_used);
    }

    // ------------------------------------------------------------------
    // Status rules on the post show page
    // ------------------------------------------------------------------

    public function test_status_gate_rejects_non_draft_states_without_side_effects(): void
    {
        foreach ([
            PostStatus::Scheduled,
            PostStatus::Publishing,
            PostStatus::Published,
            PostStatus::Failed,
            PostStatus::Cancelled,
        ] as $status) {
            $post = $this->draftPost(['status' => $status]);

            $this->actingAs($this->user)
                ->post(route('posts.generate', $post))
                ->assertSessionHas('error', 'AI generation only works on draft or generated posts.');

            $this->assertSame($status, $post->fresh()->status);
            $this->assertSame(0, $post->aiLogs()->count());
        }

        $foreign = BlogPost::factory()->create();
        $this->actingAs($this->user)->post(route('posts.generate', $foreign))->assertNotFound();
    }

    public function test_generated_post_can_be_regenerated_and_fields_are_replaced(): void
    {
        $post = $this->draftPost([
            'status' => PostStatus::Generated,
            'content' => '<p>Old content to replace.</p>',
            'excerpt' => 'Old excerpt.',
        ]);

        // The first generation's attempt (this post was generated once before).
        AiLog::factory()->succeeded()->create([
            'user_id' => $this->user->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('posts.generate', $post))
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertStringNotContainsString('Old content to replace.', (string) $post->content);
        $this->assertStringContainsString('<h2>', (string) $post->content);
        $this->assertStringNotContainsString('Old excerpt.', (string) $post->excerpt);

        $this->assertSame(2, $post->aiLogs()->count());
        $this->assertSame(
            AiLogStatus::Success,
            $post->aiLogs()->latest('id')->first()->status,
        );
    }

    public function test_interrupted_generating_post_recovers_and_closes_stuck_log(): void
    {
        $post = $this->draftPost(['status' => PostStatus::Generating]);
        $stuck = AiLog::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $post->id,
            'status' => AiLogStatus::Processing,
        ]);

        $this->actingAs($this->user)
            ->post(route('posts.generate', $post))
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame(PostStatus::Generated, $post->status);

        $stuck->refresh();
        $this->assertSame(AiLogStatus::Failed, $stuck->status);
        $this->assertStringContainsString('interrupted', (string) $stuck->error_message);
        $this->assertNotNull($stuck->completed_at);

        $this->assertSame(2, $post->aiLogs()->count());
        $this->assertSame(
            1,
            $post->aiLogs()->where('status', AiLogStatus::Success)->count(),
        );
    }

    public function test_show_page_actions_depend_on_status(): void
    {
        $emptyDraft = $this->draftPost(['status' => PostStatus::Draft, 'content' => null]);
        $this->actingAs($this->user)
            ->get(route('posts.show', $emptyDraft))
            ->assertOk()
            ->assertSee('Generate content')
            ->assertDontSee('Regenerate with AI');

        $filledDraft = $this->draftPost(['status' => PostStatus::Draft]);
        $this->actingAs($this->user)
            ->get(route('posts.show', $filledDraft))
            ->assertOk()
            ->assertSee('Regenerate with AI')
            ->assertDontSee('Generate content');

        $generated = $this->draftPost(['status' => PostStatus::Generated]);
        $this->actingAs($this->user)
            ->get(route('posts.show', $generated))
            ->assertOk()
            ->assertSee('Publish now')
            ->assertSee('Regenerate with AI');

        $publishing = $this->draftPost(['status' => PostStatus::Publishing]);
        $this->actingAs($this->user)
            ->get(route('posts.show', $publishing))
            ->assertOk()
            ->assertDontSee('Generate content')
            ->assertDontSee('Regenerate with AI')
            ->assertDontSee('Publish now');

        $generating = $this->draftPost(['status' => PostStatus::Generating]);
        $this->actingAs($this->user)
            ->get(route('posts.show', $generating))
            ->assertOk()
            ->assertSee('Generating…')
            ->assertDontSee('Generate content');
    }

    // ------------------------------------------------------------------
    // Bridge to publishing (Phase 5) and scheduling (Phase 7)
    // ------------------------------------------------------------------

    public function test_generated_post_can_enter_publishing(): void
    {
        Queue::fake();

        $post = $this->draftPost(['status' => PostStatus::Generated]);

        $this->actingAs($this->user)
            ->post(route('posts.publish', $post))
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame(PostStatus::Publishing, $post->status);
        $this->assertNotNull($post->scheduled_at);
        $this->assertSame(1, $post->publishingLogs()->count());

        Queue::assertPushed(
            PublishPostJob::class,
            fn (PublishPostJob $job): bool => $job->postId === $post->id,
        );
    }

    public function test_editing_a_schedule_into_a_generated_post_marks_it_scheduled(): void
    {
        $post = $this->draftPost(['status' => PostStatus::Generated]);

        $response = $this->actingAs($this->user)->patch(route('posts.update', $post), [
            'website_id' => $post->website_id,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'scheduled_at' => '2030-05-05T10:00',
        ]);

        $response->assertStatus(302)->assertSessionHas('success');
        $this->assertSame(PostStatus::Scheduled, $post->fresh()->status);
    }

    // ------------------------------------------------------------------
    // AI Providers CRUD
    // ------------------------------------------------------------------

    public function test_provider_store_encrypts_key_masks_hint_and_keeps_one_active(): void
    {
        $key = 'sk-live-abcdefghijklmnopqrstuvwxyz0123';

        $this->actingAs($this->user)
            ->post(route('ai.providers.store'), [
                'api_key' => $key,
                'model' => 'gpt-4o-mini',
            ])
            ->assertRedirect(route('ai.providers'))
            ->assertSessionHas('success');

        $first = AiProvider::query()->latest('id')->firstOrFail();

        // Encrypted at rest, plaintext only through the cast.
        $this->assertSame($key, $first->api_key);
        $raw = DB::table('ai_providers')->where('id', $first->id)->value('api_key');
        $this->assertNotSame($key, $raw);
        $this->assertTrue($first->is_active);

        $page = $this->actingAs($this->user)->get(route('ai.providers'));
        $page->assertOk()->assertDontSee($key)->assertSee('••••0123');

        // A second save becomes the single active row.
        $this->actingAs($this->user)->post(route('ai.providers.store'), [
            'api_key' => 'sk-second-zzzzzzzzzzzzzzzz9876',
            'model' => 'gpt-4o',
        ]);

        $second = AiProvider::query()->latest('id')->firstOrFail();
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->is_active);
        $this->assertSame(1, AiProvider::query()->where('is_active', true)->count());

        // Activate the first row again.
        $this->actingAs($this->user)
            ->post(route('ai.providers.activate', $first))
            ->assertSessionHas('success');

        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertSame(1, AiProvider::query()->where('is_active', true)->count());
    }

    public function test_provider_validation_rejects_short_key_bad_model_and_private_url(): void
    {
        $this->actingAs($this->user)
            ->post(route('ai.providers.store'), [
                'api_key' => 'short',
                'model' => 'gpt 4o;drop',
            ])
            ->assertSessionHasErrors(['api_key', 'model']);

        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            // Environment switching also disables the test-mode CSRF bypass,
            // so CSRF is skipped explicitly here (orthogonal to what is tested).
            $this->withoutMiddleware(PreventRequestForgery::class);

            $this->actingAs($this->user)
                ->post(route('ai.providers.store'), [
                    'api_key' => 'sk-live-abcdefghijkl',
                    'base_url' => 'http://192.168.1.10/v1',
                ])
                ->assertSessionHasErrors('base_url');
        } finally {
            $this->app['env'] = $previous;
        }

        $this->assertSame(0, AiProvider::query()->count());
    }

    public function test_foreign_provider_config_resolves_to_404(): void
    {
        $foreign = AiProvider::factory()->create();

        $this->actingAs($this->user)
            ->post(route('ai.providers.activate', $foreign))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('ai.providers.destroy', $foreign))
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_deleting_active_provider_falls_back_to_environment(): void
    {
        $provider = AiProvider::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->delete(route('ai.providers.destroy', $provider))
            ->assertSessionHas('success');

        $this->assertSame(0, AiProvider::query()->count());

        $this->actingAs($this->user)
            ->get(route('ai.providers'))
            ->assertOk()
            ->assertSee('Using the environment provider')
            ->assertSee('AI_PROVIDER=development');
    }

    // ------------------------------------------------------------------
    // Prompts
    // ------------------------------------------------------------------

    #[DataProvider('templateKeyProvider')]
    public function test_prompt_seeder_is_idempotent(string $key): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $this->seed(PromptTemplateSeeder::class);

        $template = PromptTemplate::query()->where('key', $key)->sole();

        $this->assertSame($key, $template->key);
        $this->assertNotEmpty($template->system_prompt);
        $this->assertNotEmpty($template->user_prompt);
        $this->assertSame(1, PromptTemplate::query()->where('key', $key)->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function templateKeyProvider(): array
    {
        return [
            'blog post generation' => ['blog_post_generation'],
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function draftPost(array $attributes = []): BlogPost
    {
        return BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            ...$attributes,
        ]);
    }
}
