<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PublishingLogStatus;
use App\Models\BlogPost;
use App\Models\PublishingLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PostsTest extends TestCase
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
    // Access & index
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/posts')->assertRedirect(route('login'));
        $this->get('/posts/create')->assertRedirect(route('login'));
        $this->post('/posts', [])->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_users_posts(): void
    {
        $other = User::factory()->create();
        $otherWebsite = Website::factory()->create(['user_id' => $other->id]);

        BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'My Secret Draft',
        ]);
        BlogPost::factory()->create([
            'user_id' => $other->id,
            'website_id' => $otherWebsite->id,
            'title' => 'Competitor Article',
        ]);

        $response = $this->actingAs($this->user)->get('/posts');

        $response->assertOk();
        $response->assertSee('My Secret Draft');
        $response->assertDontSee('Competitor Article');
    }

    public function test_index_filters_by_status_website_and_search(): void
    {
        $secondWebsite = Website::factory()->create(['user_id' => $this->user->id]);

        BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'Alpha tips',
            'status' => PostStatus::Draft,
        ]);
        BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'Beta guide',
            'status' => PostStatus::Scheduled,
        ]);
        BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $secondWebsite->id,
            'title' => 'Gamma notes',
            'status' => PostStatus::Draft,
        ]);

        $acting = $this->actingAs($this->user);

        $acting->get('/posts?status=scheduled')
            ->assertOk()
            ->assertSee('Beta guide')
            ->assertDontSee('Alpha tips')
            ->assertDontSee('Gamma notes');

        $acting->get('/posts?website='.$secondWebsite->id)
            ->assertOk()
            ->assertSee('Gamma notes')
            ->assertDontSee('Alpha tips');

        $acting->get('/posts?q=Alpha')
            ->assertOk()
            ->assertSee('Alpha tips')
            ->assertDontSee('Beta guide');

        // Invalid filter values are ignored, never a 422.
        $acting->get('/posts?status=not-a-status&website=999999')
            ->assertOk()
            ->assertSee('Alpha tips')
            ->assertSee('Beta guide');
    }

    public function test_index_shows_empty_states(): void
    {
        $this->actingAs($this->user)
            ->get('/posts')
            ->assertOk()
            ->assertSee('No posts yet.');

        $userWithoutWebsites = User::factory()->create();

        $this->actingAs($userWithoutWebsites)
            ->get('/posts')
            ->assertOk()
            ->assertSee('Add a website first');
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function test_create_page_renders_and_requires_a_website_first(): void
    {
        $this->actingAs($this->user)
            ->get('/posts/create')
            ->assertOk()
            ->assertSee('New post')
            ->assertSee($this->website->name);

        $userWithoutWebsites = User::factory()->create();

        $this->actingAs($userWithoutWebsites)
            ->get('/posts/create')
            ->assertOk()
            ->assertSee('Add a website first');
    }

    public function test_store_creates_a_draft_with_defaults(): void
    {
        $response = $this->actingAs($this->user)->post('/posts', [
            'website_id' => $this->website->id,
            'title' => 'Hello World!',
            'content' => '<p>First post.</p>',
            'tags' => 'news, launch',
        ]);

        $post = BlogPost::firstOrFail();

        $response->assertRedirect(route('posts.show', $post));

        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertSame('manual', $post->source->value);
        $this->assertSame('hello-world', $post->slug);
        $this->assertSame($this->user->id, $post->user_id);
        $this->assertSame($this->website->id, $post->website_id);
        $this->assertSame(['news', 'launch'], $post->tags);
        $this->assertNull($post->scheduled_at);
    }

    public function test_store_with_a_schedule_converts_website_local_time_to_utc(): void
    {
        $newYork = Website::factory()->create([
            'user_id' => $this->user->id,
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($this->user)->post('/posts', [
            'website_id' => $newYork->id,
            'title' => 'Scheduled piece',
            'scheduled_at' => '2030-01-01T14:00',
        ])->assertRedirect();

        $post = BlogPost::firstOrFail();

        $this->assertSame(PostStatus::Scheduled, $post->status);
        // 14:00 in New York (EST, UTC-5) is 19:00 UTC.
        $this->assertSame('2030-01-01 19:00:00', $post->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_store_validates_required_fields_and_ownership(): void
    {
        $foreignWebsite = Website::factory()->create(); // belongs to another user

        $this->actingAs($this->user)
            ->post('/posts', ['website_id' => $this->website->id])
            ->assertSessionHasErrors(['title']);

        $this->actingAs($this->user)
            ->post('/posts', ['title' => 'No site'])
            ->assertSessionHasErrors(['website_id']);

        $this->actingAs($this->user)
            ->post('/posts', [
                'website_id' => $foreignWebsite->id,
                'title' => 'Not mine',
            ])
            ->assertSessionHasErrors(['website_id']);

        $this->actingAs($this->user)
            ->post('/posts', [
                'website_id' => $this->website->id,
                'title' => 'Past schedule',
                'scheduled_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors(['scheduled_at']);

        $this->assertSame(0, BlogPost::count());
    }

    // ------------------------------------------------------------------
    // Show & preview
    // ------------------------------------------------------------------

    public function test_show_renders_content_and_sanitizes_it(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'Preview me',
            'content' => '<h2>Hello</h2>'
                .'<script>alert(1)</script>'
                .'<p onclick="steal()">Hi</p>'
                .'<img src="x" onerror="alert(2)">'
                .'<a href="javascript:alert(3)">link</a>',
        ]);

        $response = $this->actingAs($this->user)->get(route('posts.show', $post));

        $response->assertOk();
        $response->assertSee('Preview me');
        $response->assertSee('<h2>Hello</h2>', false);
        $response->assertSee('<p>Hi</p>', false);
        $response->assertDontSee('<script>', false);
        $response->assertDontSee('steal()', false);       // on* handler stripped
        $response->assertDontSee('onerror', false);
        $response->assertDontSee('alert(2)', false);      // was inside onerror
        $response->assertDontSee('javascript:', false);
        $response->assertSee('No publishing attempts yet.');
    }

    public function test_show_displays_publishing_history(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
        ]);

        $post->publishingLogs()->create([
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Failed,
            'error_message' => 'REST API returned 500.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('REST API returned 500.');
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function test_update_changes_fields_and_splits_tags(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'Old title',
        ]);

        $originalSlug = $post->slug;

        $response = $this->actingAs($this->user)->put(route('posts.update', $post), [
            'website_id' => $this->website->id,
            'title' => 'New title',
            'tags' => 'alpha, beta ,  gamma',
            'keywords' => 'kw1, kw2',
        ]);

        $response->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame('New title', $post->title);
        $this->assertSame($originalSlug, $post->slug); // slug is never regenerated on edit
        $this->assertSame(['alpha', 'beta', 'gamma'], $post->tags);
        $this->assertSame(['kw1', 'kw2'], $post->keywords);
    }

    public function test_update_clearing_the_schedule_returns_scheduled_to_draft(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->user)->put(route('posts.update', $post), [
            'website_id' => $this->website->id,
            'title' => $post->title,
            'scheduled_at' => '',
        ])->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNull($post->scheduled_at);
    }

    public function test_update_never_touches_publish_statuses(): void
    {
        $post = BlogPost::factory()->published()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
        ]);

        $this->actingAs($this->user)->put(route('posts.update', $post), [
            'website_id' => $this->website->id,
            'title' => 'Edited after publish',
            'scheduled_at' => '',
        ])->assertRedirect(route('posts.show', $post));

        $post->refresh();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertNotNull($post->wordpress_post_id);
    }

    public function test_update_accepts_an_unchanged_past_schedule_but_rejects_a_new_past_one(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->subDay()->startOfMinute(),
        ]);

        // Same instant resubmitted (seconds already zero) → allowed.
        $this->actingAs($this->user)->put(route('posts.update', $post), [
            'website_id' => $this->website->id,
            'title' => 'Keep my schedule',
            'scheduled_at' => $post->scheduled_at->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        // A different past instant → rejected.
        $this->actingAs($this->user)->put(route('posts.update', $post), [
            'website_id' => $this->website->id,
            'title' => 'Keep my schedule',
            'scheduled_at' => now()->subDays(3)->startOfMinute()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors(['scheduled_at']);
    }

    // ------------------------------------------------------------------
    // Delete & ownership
    // ------------------------------------------------------------------

    public function test_delete_removes_the_post_and_its_publishing_logs(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
        ]);

        $post->publishingLogs()->create([
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Success,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
        $this->assertSame(0, PublishingLog::count());
    }

    public function test_another_users_post_is_not_found_and_the_policy_denies_them(): void
    {
        $other = User::factory()->create();

        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'Original title',
        ]);

        $acting = $this->actingAs($other);

        $acting->get(route('posts.show', $post))->assertNotFound();
        $acting->get(route('posts.edit', $post))->assertNotFound();
        $acting->put(route('posts.update', $post), [
            'website_id' => $this->website->id,
            'title' => 'Hijacked',
        ])->assertNotFound();
        $acting->delete(route('posts.destroy', $post))->assertNotFound();

        $this->assertFalse(Gate::forUser($other)->allows('update', $post));
        $this->assertFalse(Gate::forUser($other)->allows('delete', $post));
        $this->assertTrue(Gate::forUser($this->user)->allows('update', $post));

        $post->refresh();
        $this->assertSame('Original title', $post->title);
    }
}
