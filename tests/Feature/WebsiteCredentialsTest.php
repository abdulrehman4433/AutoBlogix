<?php

namespace Tests\Feature;

use App\Enums\WebsiteStatus;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_shown_exactly_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/websites', [
            'name' => 'My Blog',
            'url' => 'https://example.com',
            'timezone' => 'UTC',
        ])->assertSessionHasNoErrors();

        $website = Website::firstOrFail();
        $secret = session('plaintext_secret');
        $this->assertNotNull($secret);

        // First visit after creation: the plaintext secret is visible.
        $this->get(route('websites.show', $website))->assertOk()->assertSee($secret);

        // Second visit: only the masked hint remains.
        $this->get(route('websites.show', $website))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertSee($website->maskedSecretHint());
    }

    public function test_api_key_and_masked_secret_are_visible_on_show(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        app(WebsiteCredentialService::class)->issueCredentials($website);
        $website->refresh();

        $response = $this->actingAs($user)->get(route('websites.show', $website));

        $response->assertOk();
        $response->assertSee($website->api_key);
        $response->assertSee($website->maskedSecretHint());
        $response->assertSee('only shown at creation and rotation');
    }

    public function test_rotate_issues_a_new_pair_and_invalidates_the_old_one(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->connected()->create(['user_id' => $user->id]);

        $credentials = app(WebsiteCredentialService::class);
        $oldSecret = $credentials->issueCredentials($website);
        $oldKey = $website->api_key;

        $response = $this->actingAs($user)
            ->post(route('websites.credentials.rotate', $website));

        $website->refresh();
        $newSecret = session('plaintext_secret');

        $response->assertRedirect(route('websites.show', $website));
        $this->assertNotNull($newSecret);
        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertNotSame($oldKey, $website->api_key);
        $this->assertSame(WebsiteStatus::Pending, $website->status);

        $this->assertNull($credentials->verifyCredentials($oldKey, $oldSecret));
        $this->assertNotNull($credentials->verifyCredentials($website->api_key, $newSecret));

        $this->assertSame(
            'credentials_rotated',
            $website->connectionLogs()->latest('id')->first()->action
        );
    }

    public function test_revoke_clears_credentials_and_sets_disconnected(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->connected()->create(['user_id' => $user->id]);
        app(WebsiteCredentialService::class)->issueCredentials($website);

        $response = $this->actingAs($user)
            ->post(route('websites.credentials.revoke', $website));

        $website->refresh();

        $response->assertRedirect(route('websites.show', $website));
        $this->assertNull($website->api_key);
        $this->assertNull($website->encrypted_api_secret);
        $this->assertSame(WebsiteStatus::Disconnected, $website->status);
        $this->assertSame(
            'credentials_revoked',
            $website->connectionLogs()->latest('id')->first()->action
        );

        $this->get(route('websites.show', $website))
            ->assertOk()
            ->assertSee('Credentials were revoked')
            ->assertSee('Generate credentials');
    }

    public function test_non_owner_cannot_rotate_or_revoke_credentials(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id]);
        app(WebsiteCredentialService::class)->issueCredentials($website);

        $actor = $this->actingAs($intruder);

        $actor->post(route('websites.credentials.rotate', $website))->assertNotFound();
        $actor->post(route('websites.credentials.revoke', $website))->assertNotFound();

        $this->assertNotNull($website->fresh()->api_key);
        $this->assertSame($owner->id, $website->fresh()->user_id);
    }

    public function test_credential_events_appear_in_connection_activity(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        app(WebsiteCredentialService::class)->issueCredentials($website);

        $this->actingAs($user)
            ->get(route('websites.show', $website))
            ->assertOk()
            ->assertSee('Credentials Created')
            ->assertSee('API credentials generated.');
    }
}
