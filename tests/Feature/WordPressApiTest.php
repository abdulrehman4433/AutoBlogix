<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PublishingLogStatus;
use App\Enums\WebsiteStatus;
use App\Models\BlogPost;
use App\Models\Website;
use App\Services\HmacSigner;
use App\Services\WebsiteCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WordPressApiTest extends TestCase
{
    use RefreshDatabase;

    private const string CONNECT = '/api/v1/wordpress/connect';

    private const string PUBLISH_RESULT = '/api/v1/wordpress/publish-result';

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->website = Website::factory()->withCredentials()->create();
    }

    // ------------------------------------------------------------------
    // Authentication envelope
    // ------------------------------------------------------------------

    public function test_connect_with_valid_signature_marks_the_website_connected(): void
    {
        $response = $this->signedPost(self::CONNECT, [
            'wordpress_version' => '6.7.1',
            'plugin_version' => '1.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.website_id', $this->website->id);
        $response->assertJsonPath('data.status', 'connected');

        $this->website->refresh();
        $this->assertSame(WebsiteStatus::Connected, $this->website->status);
        $this->assertSame('6.7.1', $this->website->wordpress_version);
        $this->assertSame('1.0.0', $this->website->plugin_version);
        $this->assertNotNull($this->website->last_connected_at);
        $this->assertNotNull($this->website->last_sync_at);

        $log = $this->website->connectionLogs()->firstOrFail();
        $this->assertSame('connect', $log->action);
        $this->assertSame('success', $log->status->value);
    }

    public function test_missing_auth_headers_are_rejected(): void
    {
        $this->postJson(self::CONNECT, ['wordpress_version' => '6.7', 'plugin_version' => '1.0'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'missing_headers');
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->signedPost(self::CONNECT, ['wordpress_version' => '6.7', 'plugin_version' => '1.0'], [
            'key' => 'abx_'.Str::lower(Str::random(32)),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'unknown_key');

        $this->assertSame(0, $this->website->connectionLogs()->count());
    }

    public function test_revoked_credentials_reject_the_old_key(): void
    {
        $key = $this->website->api_key;

        app(WebsiteCredentialService::class)->revokeCredentials($this->website);

        $this->signedPost(self::CONNECT, ['wordpress_version' => '6.7', 'plugin_version' => '1.0'], [
            'key' => (string) $key,
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'unknown_key');
    }

    public function test_invalid_signature_is_rejected_and_logged(): void
    {
        $this->signedPost(self::CONNECT, ['wordpress_version' => '6.7', 'plugin_version' => '1.0'], [
            'signature' => str_repeat('deadbeef', 8),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_signature');

        $log = $this->website->connectionLogs()->firstOrFail();
        $this->assertSame('authenticate', $log->action);
        $this->assertSame('failed', $log->status->value);
    }

    public function test_stale_timestamp_is_rejected_in_both_directions(): void
    {
        $body = ['wordpress_version' => '6.7', 'plugin_version' => '1.0'];

        $this->signedPost(self::CONNECT, $body, ['timestamp' => (string) (time() - 400)])
            ->assertStatus(401)
            ->assertJsonPath('error', 'stale_timestamp');

        $this->signedPost(self::CONNECT, $body, ['timestamp' => (string) (time() + 400)])
            ->assertStatus(401)
            ->assertJsonPath('error', 'stale_timestamp');

        $this->assertSame(2, $this->website->connectionLogs()->count());
    }

    public function test_replayed_signed_request_is_rejected(): void
    {
        $body = ['wordpress_version' => '6.7', 'plugin_version' => '1.0'];
        $nonce = Str::lower(Str::random(32));
        $timestamp = (string) time();

        $this->signedPost(self::CONNECT, $body, ['nonce' => $nonce, 'timestamp' => $timestamp])
            ->assertOk();

        $this->signedPost(self::CONNECT, $body, ['nonce' => $nonce, 'timestamp' => $timestamp])
            ->assertStatus(401)
            ->assertJsonPath('error', 'replayed_nonce');
    }

    public function test_connect_rate_limit_returns_429_after_ten_attempts(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->signedPost(self::CONNECT, ['wordpress_version' => '6.7', 'plugin_version' => '1.0'])
                ->assertOk();
        }

        $this->signedPost(self::CONNECT, ['wordpress_version' => '6.7', 'plugin_version' => '1.0'])
            ->assertStatus(429);
    }

    public function test_connect_validates_the_payload(): void
    {
        $this->signedPost(self::CONNECT, ['wordpress_version' => '6.7'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_failed')
            ->assertJsonPath('errors.plugin_version.0', 'The plugin version field is required.');

        $this->website->refresh();
        $this->assertSame(WebsiteStatus::Pending, $this->website->status);
    }

    // ------------------------------------------------------------------
    // verify / disconnect / heartbeat
    // ------------------------------------------------------------------

    public function test_verify_is_read_only_and_logs_the_check(): void
    {
        $this->signedPost('/api/v1/wordpress/verify', [])
            ->assertOk()
            ->assertJsonPath('data.website_id', $this->website->id)
            ->assertJsonPath('data.status', 'pending');

        $this->website->refresh();
        $this->assertSame(WebsiteStatus::Pending, $this->website->status);
        $this->assertNull($this->website->last_connected_at);

        $log = $this->website->connectionLogs()->firstOrFail();
        $this->assertSame('verify', $log->action);
    }

    public function test_disconnect_marks_the_website_disconnected(): void
    {
        $this->signedPost('/api/v1/wordpress/disconnect', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'disconnected');

        $this->website->refresh();
        $this->assertSame(WebsiteStatus::Disconnected, $this->website->status);

        $log = $this->website->connectionLogs()->firstOrFail();
        $this->assertSame('disconnect', $log->action);
    }

    public function test_heartbeat_refreshes_versions_and_sync_without_logging_when_connected(): void
    {
        $this->website->status = WebsiteStatus::Connected;
        $this->website->save();

        $this->signedPost('/api/v1/wordpress/heartbeat', [
            'wordpress_version' => '6.8',
            'plugin_version' => '1.1.0',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'connected');

        $this->website->refresh();
        $this->assertSame('6.8', $this->website->wordpress_version);
        $this->assertSame('1.1.0', $this->website->plugin_version);
        $this->assertNotNull($this->website->last_sync_at);
        $this->assertSame(0, $this->website->connectionLogs()->count());
    }

    public function test_heartbeat_reconnects_a_pending_website_and_logs_the_change(): void
    {
        $this->signedPost('/api/v1/wordpress/heartbeat', [
            'wordpress_version' => '6.7.1',
            'plugin_version' => '1.0.0',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'connected');

        $this->website->refresh();
        $this->assertSame(WebsiteStatus::Connected, $this->website->status);
        $this->assertNotNull($this->website->last_connected_at);

        $log = $this->website->connectionLogs()->firstOrFail();
        $this->assertSame('heartbeat', $log->action);
    }

    // ------------------------------------------------------------------
    // publish-result
    // ------------------------------------------------------------------

    public function test_publish_result_marks_the_post_published_and_closes_the_log(): void
    {
        $post = $this->postWithKey();

        $response = $this->signedPost(self::PUBLISH_RESULT, [
            'idempotency_key' => $post->publish_idempotency_key,
            'success' => true,
            'wordpress_post_id' => 123,
            'url' => 'https://blog.example.com/hello-world',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $response->assertJsonPath('data.already_recorded', false);

        $post->refresh();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertSame(123, $post->wordpress_post_id);
        $this->assertSame('https://blog.example.com/hello-world', $post->wordpress_url);
        $this->assertNotNull($post->published_at);
        $this->assertNull($post->failure_reason);

        $log = $post->publishingLogs()->firstOrFail();
        $this->assertSame(PublishingLogStatus::Success, $log->status);
        $this->assertNotNull($log->completed_at);
        $this->assertNotNull($log->response_summary);
    }

    public function test_publish_result_is_idempotent_for_duplicate_deliveries(): void
    {
        $post = $this->postWithKey();

        $payload = [
            'idempotency_key' => $post->publish_idempotency_key,
            'success' => true,
            'wordpress_post_id' => 123,
            'url' => 'https://blog.example.com/hello-world',
        ];

        $this->signedPost(self::PUBLISH_RESULT, $payload)->assertOk();

        // Duplicate delivery with different WordPress values: nothing may change.
        $response = $this->signedPost(self::PUBLISH_RESULT, [
            'idempotency_key' => $post->publish_idempotency_key,
            'success' => true,
            'wordpress_post_id' => 999,
            'url' => 'https://blog.example.com/other',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.already_recorded', true);
        $response->assertJsonPath('data.wordpress_post_id', 123);

        $post->refresh();
        $this->assertSame(123, $post->wordpress_post_id);
        $this->assertSame('https://blog.example.com/hello-world', $post->wordpress_url);
        $this->assertSame(1, $post->publishingLogs()->count());
        $this->assertSame(PublishingLogStatus::Success, $post->publishingLogs()->firstOrFail()->status);
    }

    public function test_publish_result_failure_marks_the_post_failed(): void
    {
        $post = $this->postWithKey();

        $this->signedPost(self::PUBLISH_RESULT, [
            'idempotency_key' => $post->publish_idempotency_key,
            'success' => false,
            'message' => 'REST API returned 500.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $post->refresh();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertSame('REST API returned 500.', $post->failure_reason);

        $log = $post->publishingLogs()->firstOrFail();
        $this->assertSame(PublishingLogStatus::Failed, $log->status);
        $this->assertSame('REST API returned 500.', $log->error_message);
    }

    public function test_publish_result_rejects_an_unknown_idempotency_key(): void
    {
        $this->signedPost(self::PUBLISH_RESULT, [
            'idempotency_key' => (string) Str::uuid(),
            'success' => true,
            'wordpress_post_id' => 1,
            'url' => 'https://blog.example.com/x',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'unknown_idempotency_key');
    }

    public function test_publish_result_rejects_another_websites_idempotency_key(): void
    {
        $other = Website::factory()->withCredentials()->create();
        $foreignPost = BlogPost::factory()->scheduled()->create([
            'website_id' => $other->id,
            'user_id' => $other->user_id,
            'publish_idempotency_key' => (string) Str::uuid(),
        ]);

        // Signed with THIS website's credentials: the foreign key must not match.
        $this->signedPost(self::PUBLISH_RESULT, [
            'idempotency_key' => $foreignPost->publish_idempotency_key,
            'success' => true,
            'wordpress_post_id' => 5,
            'url' => 'https://other.example.com/post',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'unknown_idempotency_key');

        $foreignPost->refresh();
        $this->assertSame(PostStatus::Scheduled, $foreignPost->status);
    }

    public function test_publish_result_validates_conditional_fields(): void
    {
        $post = $this->postWithKey();

        $this->signedPost(self::PUBLISH_RESULT, [
            'idempotency_key' => $post->publish_idempotency_key,
            'success' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_failed');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * A scheduled post owned by the test website, awaiting a publish result.
     */
    private function postWithKey(): BlogPost
    {
        $post = BlogPost::factory()->scheduled()->create([
            'website_id' => $this->website->id,
            'user_id' => $this->website->user_id,
            'publish_idempotency_key' => (string) Str::uuid(),
        ]);

        $post->publishingLogs()->create([
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Processing,
            'started_at' => now(),
        ]);

        return $post;
    }

    /**
     * POST with headers signed exactly like the WordPress plugin would.
     * The JSON bytes encoded here are byte-identical to postJson()'s.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $overrides
     */
    private function signedPost(string $uri, array $body, array $overrides = []): TestResponse
    {
        $timestamp = $overrides['timestamp'] ?? (string) time();
        $nonce = $overrides['nonce'] ?? Str::lower(Str::random(32));
        $key = $overrides['key'] ?? (string) $this->website->api_key;
        $secret = $overrides['secret'] ?? (string) $this->website->encrypted_api_secret;

        $signature = $overrides['signature'] ?? app(HmacSigner::class)->sign(
            'POST',
            $uri,
            $timestamp,
            $nonce,
            json_encode($body),
            $secret,
        );

        return $this->withHeaders([
            'X-ABX-Key' => $key,
            'X-ABX-Timestamp' => $timestamp,
            'X-ABX-Nonce' => $nonce,
            'X-ABX-Signature' => $signature,
        ])->postJson($uri, $body);
    }
}
