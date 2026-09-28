<?php

namespace Tests\Feature;

use App\Enums\WebsiteStatus;
use App\Models\BlogPost;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/websites')->assertRedirect(route('login'));
        $this->get('/websites/create')->assertRedirect(route('login'));
        $this->post('/websites', [])->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_users_websites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Website::factory()->create(['user_id' => $user->id, 'name' => 'My Public Blog']);
        Website::factory()->create(['user_id' => $other->id, 'name' => 'Competitor Site']);

        $response = $this->actingAs($user)->get('/websites');

        $response->assertOk();
        $response->assertSee('My Public Blog');
        $response->assertDontSee('Competitor Site');
    }

    public function test_create_page_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/websites/create');

        $response->assertOk();
        $response->assertSee('Add website');
        $response->assertSee('Save and get credentials');
    }

    public function test_website_can_be_created_with_issued_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/websites', [
            'name' => 'My Blog',
            'url' => 'https://example.com',
            'timezone' => 'UTC',
        ]);

        $website = Website::firstOrFail();

        $response->assertRedirect(route('websites.show', $website));

        $this->assertSame($user->id, $website->user_id);
        $this->assertSame('My Blog', $website->name);
        $this->assertSame('https://example.com', $website->url);
        $this->assertSame('UTC', $website->timezone);
        $this->assertSame(WebsiteStatus::Pending, $website->status);
        $this->assertMatchesRegularExpression('/^abx_[a-z0-9]{32}$/', $website->api_key);

        $secret = session('plaintext_secret');
        $this->assertNotNull($secret);
        $this->assertSame($secret, $website->encrypted_api_secret);

        $log = $website->connectionLogs()->firstOrFail();
        $this->assertSame('credentials_created', $log->action);
    }

    public function test_url_is_normalized_before_being_stored(): void
    {
        $this->actingAs(User::factory()->create())->post('/websites', [
            'name' => 'Example',
            'url' => '  Example.com/blog/  ',
            'timezone' => 'UTC',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://example.com/blog', Website::firstOrFail()->url);
    }

    public function test_invalid_url_is_rejected_with_a_friendly_message(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/websites', [
            'name' => 'Bad',
            'url' => 'https://exa mple.com',
            'timezone' => 'UTC',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertSame(0, Website::count());
    }

    public function test_private_urls_are_rejected_outside_local_environments(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            // Environment switching also disables the test-mode CSRF bypass,
            // so CSRF is skipped explicitly here (orthogonal to what is tested).
            $this->withoutMiddleware(PreventRequestForgery::class);

            $response = $this->actingAs(User::factory()->create())->post('/websites', [
                'name' => 'Internal',
                'url' => 'http://127.0.0.1:8080',
                'timezone' => 'UTC',
            ]);

            $response->assertSessionHasErrors('url');
            $this->assertSame(0, Website::count());
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_duplicate_url_is_rejected_for_the_same_user(): void
    {
        $user = User::factory()->create();
        Website::factory()->create(['user_id' => $user->id, 'url' => 'https://dup.example.com']);

        $response = $this->actingAs($user)->post('/websites', [
            'name' => 'Again',
            'url' => 'https://dup.example.com/',
            'timezone' => 'UTC',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertStringContainsString(
            'already added this website',
            session('errors')->first('url')
        );
    }

    public function test_same_url_may_be_used_by_a_different_user(): void
    {
        Website::factory()->create(['url' => 'https://shared.example.com']);

        $this->actingAs(User::factory()->create())->post('/websites', [
            'name' => 'Other owner',
            'url' => 'https://shared.example.com',
            'timezone' => 'UTC',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Website::count());
    }

    public function test_validation_errors_render(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/websites', [
            'name' => '',
            'url' => '',
            'timezone' => 'Mars/Olympus',
        ]);

        $response->assertSessionHasErrors(['name', 'url', 'timezone']);
    }

    public function test_website_can_be_updated(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/websites/{$website->id}", [
            'name' => 'Renamed',
            'url' => 'https://new.example.org',
            'timezone' => 'Europe/Berlin',
        ]);

        $response->assertRedirect(route('websites.show', $website));

        $website->refresh();
        $this->assertSame('Renamed', $website->name);
        $this->assertSame('https://new.example.org', $website->url);
        $this->assertSame('Europe/Berlin', $website->timezone);
    }

    public function test_update_rejects_another_own_website_url(): void
    {
        $user = User::factory()->create();
        Website::factory()->create(['user_id' => $user->id, 'url' => 'https://first.example.com']);
        $second = Website::factory()->create(['user_id' => $user->id, 'url' => 'https://second.example.com']);

        $response = $this->actingAs($user)->put("/websites/{$second->id}", [
            'name' => $second->name,
            'url' => 'https://first.example.com',
            'timezone' => 'UTC',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertSame('https://second.example.com', $second->fresh()->url);
    }

    public function test_website_can_be_deleted_with_its_posts(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $user->id]);
        BlogPost::factory()->create(['user_id' => $user->id, 'website_id' => $website->id]);

        $response = $this->actingAs($user)->delete("/websites/{$website->id}");

        $response->assertRedirect(route('websites.index'));
        $this->assertSame(0, Website::count());
        $this->assertSame(0, BlogPost::count());
    }

    public function test_other_users_website_returns_404_for_every_route(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $website = Website::factory()->create(['user_id' => $owner->id]);

        $actor = $this->actingAs($intruder);

        $actor->get("/websites/{$website->id}")->assertNotFound();
        $actor->get("/websites/{$website->id}/edit")->assertNotFound();
        $actor->put("/websites/{$website->id}", [
            'name' => 'Hijacked',
            'url' => 'https://evil.example.com',
            'timezone' => 'UTC',
        ])->assertNotFound();
        $actor->delete("/websites/{$website->id}")->assertNotFound();

        $website->refresh();
        $this->assertSame($owner->id, $website->user_id);
        $this->assertNotSame('Hijacked', $website->name);
    }
}
