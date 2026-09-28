<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\WordPressAuthenticationException;
use App\Models\Website;
use App\Services\HmacSigner;
use App\Services\WebsiteCredentialService;
use App\Services\WordPressAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressAuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string PATH = '/api/v1/wordpress/connect';

    private WordPressAuthenticationService $service;

    private Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WordPressAuthenticationService(
            new HmacSigner,
            new WebsiteCredentialService,
        );

        $this->website = Website::factory()->withCredentials()->create();
    }

    public function test_valid_signed_request_returns_the_website(): void
    {
        $website = $this->service->authenticate($this->signedRequest());

        $this->assertSame($this->website->id, $website->id);
    }

    public function test_missing_headers_map_to_missing_headers_code(): void
    {
        $this->assertRejected($this->request([]), 'missing_headers');
    }

    public function test_malformed_nonce_maps_to_invalid_nonce_code(): void
    {
        $request = $this->signedRequest(['nonce' => 'short']);

        $this->assertRejected($request, 'invalid_nonce');
    }

    public function test_timestamp_older_than_five_minutes_maps_to_stale_timestamp(): void
    {
        $request = $this->signedRequest(['timestamp' => (string) (time() - 301)]);

        $this->assertRejected($request, 'stale_timestamp');
    }

    public function test_timestamp_more_than_five_minutes_in_the_future_maps_to_stale_timestamp(): void
    {
        $request = $this->signedRequest(['timestamp' => (string) (time() + 301)]);

        $this->assertRejected($request, 'stale_timestamp');
    }

    public function test_timestamp_at_the_window_edge_is_accepted(): void
    {
        $request = $this->signedRequest(['timestamp' => (string) (time() + 300)]);

        $this->assertSame($this->website->id, $this->service->authenticate($request)->id);
    }

    public function test_unknown_key_maps_to_unknown_key_without_touching_the_website(): void
    {
        $request = $this->signedRequest(['key' => 'abx_'.Str::lower(Str::random(32))]);

        $this->assertRejected($request, 'unknown_key');
        $this->assertSame(0, $this->website->connectionLogs()->count());
    }

    public function test_key_with_wrong_prefix_maps_to_unknown_key(): void
    {
        $request = $this->signedRequest(['key' => 'not-a-real-key-format']);

        $this->assertRejected($request, 'unknown_key');
    }

    public function test_signature_computed_with_a_wrong_secret_maps_to_invalid_signature(): void
    {
        $request = $this->signedRequest(['secret' => 'abxs_'.Str::random(40)]);

        $this->assertRejected($request, 'invalid_signature');

        $log = $this->website->connectionLogs()->firstOrFail();
        $this->assertSame('authenticate', $log->action);
        $this->assertSame('failed', $log->status->value);
    }

    public function test_tampered_body_after_signing_maps_to_invalid_signature(): void
    {
        $json = json_encode(['wordpress_version' => '6.7', 'plugin_version' => '1.0.0']);
        $request = $this->signedRequest(body: $json);
        $tampered = Request::create(
            self::PATH,
            'POST',
            [],
            [],
            [],
            $this->serverHeaders($request),
            json_encode(['wordpress_version' => '6.8', 'plugin_version' => '1.0.0']),
        );

        $this->assertRejected($tampered, 'invalid_signature');
    }

    public function test_replayed_nonce_is_rejected_after_the_first_use(): void
    {
        $request = $this->signedRequest();

        $this->service->authenticate($request);

        // The very same signed request again (identical timestamp and nonce).
        $this->assertRejected($request, 'replayed_nonce');
    }

    public function test_revoked_credentials_reject_the_old_key(): void
    {
        $key = $this->website->api_key;
        app(WebsiteCredentialService::class)->revokeCredentials($this->website);

        $request = $this->signedRequest(['key' => (string) $key]);

        $this->assertRejected($request, 'unknown_key');
    }

    private function assertRejected(Request $request, string $errorCode): void
    {
        try {
            $this->service->authenticate($request);
        } catch (WordPressAuthenticationException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
            $this->assertSame(401, $exception->status);

            return;
        }

        $this->fail('Expected WordPressAuthenticationException with code '.$errorCode.'.');
    }

    /**
     * Build a request exactly the way the plugin would send it.
     *
     * @param  array<string, string>  $overrides
     */
    private function signedRequest(array $overrides = [], string $body = '{"wordpress_version":"6.7","plugin_version":"1.0.0"}'): Request
    {
        $timestamp = $overrides['timestamp'] ?? (string) time();
        $nonce = $overrides['nonce'] ?? Str::lower(Str::random(32));
        $key = $overrides['key'] ?? (string) $this->website->api_key;
        $secret = $overrides['secret'] ?? (string) $this->website->encrypted_api_secret;

        $signature = $overrides['signature'] ?? (new HmacSigner)->sign('POST', self::PATH, $timestamp, $nonce, $body, $secret);

        return $this->request([
            'X-ABX-Key' => $key,
            'X-ABX-Timestamp' => $timestamp,
            'X-ABX-Nonce' => $nonce,
            'X-ABX-Signature' => $signature,
        ], $body);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(array $headers, string $body = '{}'): Request
    {
        return Request::create(self::PATH, 'POST', [], [], [], $this->serverHeadersFromArray($headers), $body);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function serverHeadersFromArray(array $headers): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    /**
     * @return array<string, string>
     */
    private function serverHeaders(Request $request): array
    {
        return $this->serverHeadersFromArray([
            'X-ABX-Key' => (string) $request->header('X-ABX-Key'),
            'X-ABX-Timestamp' => (string) $request->header('X-ABX-Timestamp'),
            'X-ABX-Nonce' => (string) $request->header('X-ABX-Nonce'),
            'X-ABX-Signature' => (string) $request->header('X-ABX-Signature'),
        ]);
    }
}
