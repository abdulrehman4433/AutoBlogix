<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PublishingLogStatus;
use App\Jobs\PublishPostJob;
use App\Models\BlogPost;
use App\Models\User;
use App\Models\Website;
use App\Services\HmacSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublishingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->website = Website::factory()->withCredentials()->create([
            'user_id' => $this->user->id,
            'url' => 'https://smoke-test.example.com',
        ]);
    }

    // ------------------------------------------------------------------
    // Access & idempotent entry
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_and_another_user_cannot_publish(): void
    {
        $post = $this->draftPost();

        $this->post(route('posts.publish', $post))->assertRedirect(route('login'));

        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('posts.publish', $post))
            ->assertNotFound();

        $this->assertFalse(Gate::forUser($other)->allows('publish', $post));
        $this->assertTrue(Gate::forUser($this->user)->allows('publish', $post));
    }

    public function test_publish_now_queues_the_attempt_and_opens_the_attempt_log(): void
    {
        Queue::fake();

        $post = $this->draftPost();

        $this->actingAs($this->user)
            ->post(route('posts.publish', $post))
            ->assertRedirect(route('posts.show', $post))
            ->assertSessionHas('success');

        Queue::assertPushed(PublishPostJob::class, fn (PublishPostJob $job): bool => $job->postId === $post->id);

        $post->refresh();
        $this->assertSame(PostStatus::Publishing, $post->status);
        $this->assertNotNull($post->scheduled_at); // draft → scheduled on explicit publish
        $this->assertNotNull($post->publish_idempotency_key);
        $this->assertTrue(Str::isUuid($post->publish_idempotency_key));

        $log = $post->publishingLogs()->sole();
        $this->assertSame(1, $log->attempt);
        $this->assertSame(PublishingLogStatus::Pending, $log->status);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->completed_at);
    }

    public function test_states_that_may_not_publish_are_rejected_without_side_effects(): void
    {
        Queue::fake();

        $publishing = $this->draftPost(['status' => PostStatus::Publishing]);

        $this->actingAs($this->user)
            ->post(route('posts.publish', $publishing))
            ->assertSessionHas('success', 'This post is already being published.');

        $published = BlogPost::factory()->published()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('posts.publish', $published))
            ->assertSessionHas('success', 'This post is already published.');

        $generating = $this->draftPost(['status' => PostStatus::Generating]);

        $this->actingAs($this->user)
            ->post(route('posts.publish', $generating))
            ->assertSessionHas('success', 'This post cannot be published right now.');

        Queue::assertNothingPushed();
        Http::assertNothingSent();

        $this->assertSame(PostStatus::Publishing, $publishing->fresh()->status);
        $this->assertSame(PostStatus::Generating, $generating->fresh()->status);
        $this->assertSame(0, $publishing->publishingLogs()->count());
        $this->assertSame(0, $generating->publishingLogs()->count());
    }

    // ------------------------------------------------------------------
    // Success path
    // ------------------------------------------------------------------

    public function test_publish_now_completes_to_published_and_closes_the_log(): void
    {
        Http::fake(['*' => Http::response($this->successBody(), 200)]);

        $post = $this->draftPost(['title' => 'Hello world', 'content' => '<p>Body.</p>']);

        $this->actingAs($this->user)
            ->post(route('posts.publish', $post))
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame(123, $post->wordpress_post_id);
        $this->assertSame('https://smoke-test.example.com/hello-world', $post->wordpress_url);
        $this->assertNotNull($post->published_at);
        $this->assertNull($post->failure_reason);

        $log = $post->publishingLogs()->sole();
        $this->assertSame(PublishingLogStatus::Success, $log->status);
        $this->assertSame(200, $log->http_status);
        $this->assertStringContainsString('123', (string) $log->response_summary);
        $this->assertNotNull($log->completed_at);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://smoke-test.example.com/wp-json/autoblogix/v1/publish'
                && $request->method() === 'POST';
        });
    }

    public function test_outbound_request_is_signed_with_the_website_secret_and_carries_the_key(): void
    {
        Http::fake(['*' => Http::response($this->successBody(), 200)]);

        $subdirectory = Website::factory()->withCredentials()->create([
            'user_id' => $this->user->id,
            'url' => 'https://smoke-test.example.com/blog',
        ]);

        $post = $this->draftPost(['website_id' => $subdirectory->id, 'title' => 'Signed post']);

        $this->actingAs($this->user)->post(route('posts.publish', $post));

        $key = $subdirectory->api_key;
        $secret = $subdirectory->encrypted_api_secret;

        Http::assertSent(function ($request) use ($post, $key, $secret): bool {
            $path = '/blog/wp-json/autoblogix/v1/publish';

            $expected = app(HmacSigner::class)->sign(
                'POST',
                $path,
                (string) $request->header('X-ABX-Timestamp')[0],
                (string) $request->header('X-ABX-Nonce')[0],
                $request->body(),
                $secret,
            );

            $data = $request->data();

            return $request->url() === 'https://smoke-test.example.com/blog/wp-json/autoblogix/v1/publish'
                && $request->header('X-ABX-Key')[0] === $key
                && hash_equals($expected, (string) $request->header('X-ABX-Signature')[0])
                && $data['idempotency_key'] === $post->fresh()->publish_idempotency_key
                && $data['title'] === 'Signed post'
                && $data['post_id'] === $post->id
                && Str::isUuid($data['idempotency_key']);
        });
    }

    public function test_scheduled_post_publishes_and_retry_reuses_the_key_with_attempt_two(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('', 500)
                ->push($this->successBody(), 200),
        ]);

        $post = $this->draftPost(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

        // Attempt 1 — HTTP 500 → failed.
        $this->actingAs($this->user)->post(route('posts.publish', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertNotNull($post->failure_reason);
        $key = $post->publish_idempotency_key;
        $this->assertNotNull($key);

        // Attempt 2 — Retry reuses the same key.
        $this->actingAs($this->user)->post(route('posts.publish', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame($key, $post->publish_idempotency_key);
        $this->assertNull($post->failure_reason);

        $logs = $post->publishingLogs()->orderBy('attempt')->get();
        $this->assertSame([1, 2], $logs->pluck('attempt')->all());
        $this->assertSame(PublishingLogStatus::Failed, $logs[0]->status);
        $this->assertSame(PublishingLogStatus::Success, $logs[1]->status);
        $this->assertSame(500, $logs[0]->http_status);
        $this->assertSame(200, $logs[1]->http_status);

        Http::assertSentCount(2);
    }

    // ------------------------------------------------------------------
    // Failure mapping (friendly message on the post, detail in the log)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{int, string}>
     */
    public static function httpFailureProvider(): array
    {
        return [
            '401 rejects credentials' => [401, 'rejected the API credentials'],
            '403 rejects credentials' => [403, 'rejected the API credentials'],
            '404 missing plugin endpoint' => [404, 'publish endpoint was not found'],
            '429 rate limited' => [429, 'rate limiting AutoBlogix'],
            '500 server error' => [500, 'server error (HTTP 500)'],
        ];
    }

    #[DataProvider('httpFailureProvider')]
    public function test_http_failures_map_to_friendly_reasons(int $status, string $expected): void
    {
        Http::fake(['*' => Http::response(['message' => 'internal detail'], $status)]);

        $post = $this->draftPost();

        $this->actingAs($this->user)->post(route('posts.publish', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertStringContainsString($expected, (string) $post->failure_reason);

        // Friendly only on the post — technical detail lives in the log.
        $this->assertStringNotContainsString('Exception', (string) $post->failure_reason);

        $log = $post->publishingLogs()->sole();
        $this->assertSame(PublishingLogStatus::Failed, $log->status);
        $this->assertSame($status, $log->http_status);
        $this->assertStringContainsString("HTTP {$status}", (string) $log->error_message);
        $this->assertNotNull($log->completed_at);
    }

    public function test_connection_failure_records_a_friendly_reason(): void
    {
        Http::fake([
            '*' => fn () => throw new ConnectionException('cURL error 6: Could not resolve host: smoke-test.example.com'),
        ]);

        $post = $this->draftPost();

        $this->actingAs($this->user)->post(route('posts.publish', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertStringContainsString('Could not reach smoke-test.example.com', (string) $post->failure_reason);
        $this->assertStringNotContainsString('cURL', (string) $post->failure_reason);

        $log = $post->publishingLogs()->sole();
        $this->assertNull($log->http_status);
        $this->assertStringContainsString('Could not resolve host', (string) $log->error_message);
    }

    public function test_plugin_failure_message_and_malformed_bodies_are_handled(): void
    {
        // First: plugin reports success:false; second: 200 with non-JSON body.
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'success' => false,
                    'code' => 'permission_denied',
                    'message' => 'The editor role is missing the publish capability.',
                ], 200)
                ->push('<html>Maintenance</html>', 200),
        ]);

        $post = $this->draftPost();

        $this->actingAs($this->user)->post(route('posts.publish', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertSame('The editor role is missing the publish capability.', $post->failure_reason);

        $other = $this->draftPost();

        $this->actingAs($this->user)->post(route('posts.publish', $other));

        $other->refresh();
        $this->assertSame(PostStatus::Failed, $other->status);
        $this->assertSame('The website returned an unexpected response.', $other->failure_reason);
        $this->assertStringContainsString('Maintenance', (string) $other->publishingLogs()->sole()->error_message);
    }

    // ------------------------------------------------------------------
    // UI states
    // ------------------------------------------------------------------

    public function test_show_page_renders_the_right_publish_action_per_status(): void
    {
        $acting = $this->actingAs($this->user);

        $draft = $this->draftPost();
        $acting->get(route('posts.show', $draft))
            ->assertOk()
            ->assertSee('Publish now')
            ->assertSee(route('posts.publish', $draft), false);

        $scheduled = $this->draftPost(['status' => PostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
        $acting->get(route('posts.show', $scheduled))
            ->assertOk()
            ->assertSee('Publish now');

        $failed = $this->draftPost(['status' => PostStatus::Failed, 'failure_reason' => 'The website returned a server error (HTTP 500).']);
        $acting->get(route('posts.show', $failed))
            ->assertOk()
            ->assertSee('Retry publish')
            ->assertSee('The website returned a server error (HTTP 500).');

        $publishing = $this->draftPost(['status' => PostStatus::Publishing]);
        $acting->get(route('posts.show', $publishing))
            ->assertOk()
            ->assertSee('Publishing…')
            ->assertDontSee('Publish now');

        $published = BlogPost::factory()->published()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
        ]);
        $acting->get(route('posts.show', $published))
            ->assertOk()
            ->assertDontSee('Publish now')
            ->assertDontSee('Retry publish');
    }

    // ------------------------------------------------------------------

    private function draftPost(array $attributes = []): BlogPost
    {
        return BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => PostStatus::Draft,
            ...$attributes,
        ]);
    }

    /**
     * @return array{success: bool, wordpress_post_id: int, url: string}
     */
    private function successBody(): array
    {
        return [
            'success' => true,
            'wordpress_post_id' => 123,
            'url' => 'https://smoke-test.example.com/hello-world',
        ];
    }
}
