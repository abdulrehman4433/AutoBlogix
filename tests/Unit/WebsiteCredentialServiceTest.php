<?php

namespace Tests\Unit;

use App\Enums\ConnectionLogStatus;
use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Services\WebsiteCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebsiteCredentialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WebsiteCredentialService::class);
    }

    public function test_generate_api_key_uses_the_expected_format(): void
    {
        $key = $this->service->generateApiKey();

        $this->assertMatchesRegularExpression('/^abx_[a-z0-9]{32}$/', $key);
    }

    public function test_generated_api_keys_are_unique(): void
    {
        $keys = collect(range(1, 50))->map(fn () => $this->service->generateApiKey());

        $this->assertSame(50, $keys->unique()->count());
    }

    public function test_generate_api_secret_uses_the_expected_format(): void
    {
        $secret = $this->service->generateApiSecret();

        $this->assertMatchesRegularExpression('/^abxs_[0-9a-f]{40}$/', $secret);
        $this->assertGreaterThanOrEqual(37, strlen($secret));
    }

    public function test_issue_credentials_stores_an_encrypted_pair_and_logs_the_event(): void
    {
        $website = Website::factory()->create();

        $secret = $this->service->issueCredentials($website, '203.0.113.10');

        $website->refresh();

        $this->assertNotNull($website->api_key);
        $this->assertSame($secret, $website->encrypted_api_secret);
        $this->assertSame(WebsiteStatus::Pending, $website->status);

        // The secret must round-trip through the encrypted cast.
        $this->assertTrue($this->service->verifyCredentials($website->api_key, $secret) !== null);

        $log = $website->connectionLogs()->latest('id')->first();
        $this->assertSame('credentials_created', $log->action);
        $this->assertSame(ConnectionLogStatus::Success, $log->status);
        $this->assertSame('203.0.113.10', $log->ip_address);
        $this->assertStringNotContainsString($secret, $log->message);
    }

    public function test_verify_credentials_accepts_the_correct_pair_only(): void
    {
        $website = Website::factory()->create();
        $secret = $this->service->issueCredentials($website);

        $this->assertTrue($this->service->verifyCredentials($website->api_key, $secret)?->is($website) ?? false);
        $this->assertNull($this->service->verifyCredentials($website->api_key, 'abxs_wrong'));
        $this->assertNull($this->service->verifyCredentials('abx_unknown', $secret));
        $this->assertNull($this->service->verifyCredentials('', ''));
        $this->assertNull($this->service->verifyCredentials($website->api_key, ''));
    }

    public function test_verify_credentials_fails_for_websites_without_credentials(): void
    {
        $website = Website::factory()->create();

        $this->assertNull($this->service->verifyCredentials('abx_anything', 'abxs_anything'));
    }

    public function test_rotate_credentials_replaces_the_pair_and_invalidates_the_old_one(): void
    {
        $website = Website::factory()->connected()->create();
        $secret = $this->service->issueCredentials($website);
        $oldKey = $website->api_key;
        $oldSecret = $secret;

        $newSecret = $this->service->rotateCredentials($website);
        $website->refresh();

        $this->assertNotSame($oldKey, $website->api_key);
        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertSame(WebsiteStatus::Pending, $website->status);

        $this->assertNull($this->service->verifyCredentials($oldKey, $oldSecret));
        $this->assertNotNull($this->service->verifyCredentials($website->api_key, $newSecret));

        $this->assertSame(
            'credentials_rotated',
            $website->connectionLogs()->latest('id')->first()->action
        );
    }

    public function test_revoke_credentials_clears_the_pair_and_disconnects(): void
    {
        $website = Website::factory()->connected()->create();
        $secret = $this->service->issueCredentials($website);

        $this->service->revokeCredentials($website);
        $website->refresh();

        $this->assertNull($website->api_key);
        $this->assertNull($website->encrypted_api_secret);
        $this->assertSame(WebsiteStatus::Disconnected, $website->status);
        $this->assertNull($this->service->verifyCredentials('abx_gone', $secret));

        $this->assertSame(
            'credentials_revoked',
            $website->connectionLogs()->latest('id')->first()->action
        );
    }
}
