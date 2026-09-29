<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AiLogStatus;
use App\Enums\ConnectionLogStatus;
use App\Enums\PublishingLogStatus;
use App\Models\AiLog;
use App\Models\BlogPost;
use App\Models\PublishingLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Website $website;

    private BlogPost $post;

    private User $otherUser;

    private Website $otherWebsite;

    private BlogPost $otherPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->website = Website::factory()->create(['user_id' => $this->user->id]);
        $this->post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'title' => 'My log subject post',
        ]);

        $this->otherUser = User::factory()->create();
        $this->otherWebsite = Website::factory()->create(['user_id' => $this->otherUser->id]);
        $this->otherPost = BlogPost::factory()->create([
            'user_id' => $this->otherUser->id,
            'website_id' => $this->otherWebsite->id,
            'title' => 'Other user post',
        ]);
    }

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_from_the_logs_page(): void
    {
        $this->get(route('logs.index'))->assertRedirect(route('login'));
    }

    public function test_empty_state_renders_before_any_activity_exists(): void
    {
        $this->actingAs($this->user)
            ->get(route('logs.index'))
            ->assertOk()
            ->assertSee('No activity yet.');

        $this->actingAs($this->user)
            ->get(route('logs.index', ['type' => 'connection']))
            ->assertOk()
            ->assertSee('No logs match your filters.');
    }

    // ------------------------------------------------------------------
    // Feed content & scoping
    // ------------------------------------------------------------------

    public function test_feed_shows_my_rows_across_all_three_types_and_hides_other_users(): void
    {
        $this->seedLogs();

        $this->actingAs($this->user)
            ->get(route('logs.index'))
            ->assertOk()
            // own rows visible, with subjects/kinds/details resolved
            ->assertSee('Website connected.')
            ->assertSee('Attempt 1')
            ->assertSee('Published as WordPress post 5.')
            ->assertSee('Development')
            ->assertSee($this->website->name)
            ->assertSee('My log subject post')
            // other users' rows invisible in every type
            ->assertDontSee('Other site message.')
            ->assertDontSee('Other post summary.')
            ->assertDontSee('Other AI failure.')
            ->assertDontSee($this->otherWebsite->name)
            ->assertDontSee('Other user post');
    }

    public function test_type_filter_narrows_to_a_single_type(): void
    {
        $this->seedLogs();

        $this->actingAs($this->user)
            ->get(route('logs.index', ['type' => 'publishing']))
            ->assertOk()
            ->assertSee('Attempt 1')
            ->assertDontSee('Website connected.')
            ->assertDontSee('Development');

        $this->actingAs($this->user)
            ->get(route('logs.index', ['type' => 'connection']))
            ->assertOk()
            ->assertSee('Website connected.')
            ->assertDontSee('Attempt 1')
            ->assertDontSee('Development');

        $this->actingAs($this->user)
            ->get(route('logs.index', ['type' => 'ai']))
            ->assertOk()
            ->assertSee('Development')
            ->assertDontSee('Attempt 1')
            ->assertDontSee('Website connected.');
    }

    public function test_status_filter_narrows_to_a_single_status(): void
    {
        $this->seedLogs();
        $this->website->connectionLogs()->create([
            'action' => 'authenticate',
            'status' => ConnectionLogStatus::Failed,
            'message' => 'Connection trouble.',
            'ip_address' => '10.0.0.1',
        ]);
        PublishingLog::query()->create([
            'post_id' => $this->post->id,
            'website_id' => $this->website->id,
            'attempt' => 2,
            'status' => PublishingLogStatus::Failed,
            'error_message' => 'Plugin rejected it.',
            'started_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('logs.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('Connection trouble.')
            ->assertSee('Plugin rejected it.')
            ->assertDontSee('Website connected.')
            ->assertDontSee('Published as WordPress post 5.');

        $this->actingAs($this->user)
            ->get(route('logs.index', ['status' => 'success']))
            ->assertOk()
            ->assertSee('Website connected.')
            ->assertSee('Published as WordPress post 5.')
            ->assertDontSee('Connection trouble.')
            ->assertDontSee('Plugin rejected it.');
    }

    public function test_invalid_filter_values_are_ignored_never_422(): void
    {
        $this->seedLogs();

        $this->actingAs($this->user)
            ->get(route('logs.index', ['type' => 'bogus', 'status' => 'bogus']))
            ->assertOk()
            ->assertSee('Website connected.')
            ->assertSee('Attempt 1')
            ->assertSee('Development');
    }

    public function test_pagination_renders_over_the_union(): void
    {
        foreach (range(1, 16) as $i) {
            $this->website->connectionLogs()->create([
                'action' => 'heartbeat',
                'status' => ConnectionLogStatus::Success,
                'message' => 'Paginated row '.$i,
                'ip_address' => '127.0.0.1',
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('logs.index'))
            ->assertOk()
            ->assertSee('Paginated row')
            ->assertSee('Next'); // page 2 exists (15 per page)
    }

    // ------------------------------------------------------------------

    private function seedLogs(): void
    {
        $this->website->connectionLogs()->create([
            'action' => 'connect',
            'status' => ConnectionLogStatus::Success,
            'message' => 'Website connected.',
            'ip_address' => '127.0.0.1',
        ]);
        $this->otherWebsite->connectionLogs()->create([
            'action' => 'disconnect',
            'status' => ConnectionLogStatus::Failed,
            'message' => 'Other site message.',
            'ip_address' => '10.0.0.9',
        ]);

        PublishingLog::query()->create([
            'post_id' => $this->post->id,
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Success,
            'response_summary' => 'Published as WordPress post 5.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        PublishingLog::query()->create([
            'post_id' => $this->otherPost->id,
            'website_id' => $this->otherWebsite->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Success,
            'response_summary' => 'Other post summary.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        AiLog::query()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'prompt_key' => 'blog_post_generation',
            'provider' => 'development',
            'status' => AiLogStatus::Success,
            'tokens_used' => 42,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        AiLog::query()->create([
            'user_id' => $this->otherUser->id,
            'post_id' => $this->otherPost->id,
            'prompt_key' => 'blog_post_generation',
            'provider' => 'openai',
            'status' => AiLogStatus::Failed,
            'error_message' => 'Other AI failure.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
