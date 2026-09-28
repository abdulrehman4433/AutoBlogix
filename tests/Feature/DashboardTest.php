<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\User;
use App\Models\Website;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Websites');
        $response->assertSee('Recent publishing activity');
        $response->assertSee('Upcoming scheduled posts');
        $response->assertSee('Recent connection activity');
    }

    public function test_dashboard_shows_empty_state_on_fresh_account(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertSee('Welcome to');
        $response->assertSee('No publishing activity yet.');
        $response->assertSee('Nothing scheduled yet.');
        $response->assertSee('No connection activity yet.');
    }

    public function test_metrics_only_count_the_current_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $connected = Website::factory()->connected()->for($user)->create();
        Website::factory()->for($user)->create();

        $otherWebsites = Website::factory()->count(3)->connected()->for($other)->create();

        BlogPost::factory()->for($user)->for($connected)->scheduled()->create();
        BlogPost::factory()->count(2)->for($user)->for($connected)->create();
        BlogPost::factory()->count(4)->for($other)->for($otherWebsites->first())->published()->create();

        $otherWebsites->first()->connectionLogs()->create([
            'action' => 'connect',
            'status' => 'success',
        ]);

        $metrics = app(DashboardService::class)->metricsFor($user);

        $this->assertSame(2, $metrics['websites_total']);
        $this->assertSame(1, $metrics['websites_connected']);
        $this->assertSame(3, $metrics['posts_total']);
        $this->assertSame(1, $metrics['posts_by_status'][PostStatus::Scheduled->value]);
        $this->assertSame(2, $metrics['posts_by_status'][PostStatus::Draft->value]);
        $this->assertSame(0, $metrics['posts_by_status'][PostStatus::Published->value]);

        $this->assertCount(0, $metrics['recent_publishing']);
        $this->assertCount(1, $metrics['upcoming_posts']);
        $this->assertCount(0, $metrics['recent_connections']);
    }

    public function test_upcoming_posts_are_ordered_soonest_first(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->for($user)->create();

        $later = BlogPost::factory()->for($user)->for($website)->scheduled(now()->addDays(5))->create();
        $sooner = BlogPost::factory()->for($user)->for($website)->scheduled(now()->addDay())->create();

        $metrics = app(DashboardService::class)->metricsFor($user);

        $this->assertSame(
            [$sooner->id, $later->id],
            $metrics['upcoming_posts']->pluck('id')->all()
        );
    }

    public function test_recent_publishing_activity_shows_current_users_logs(): void
    {
        $user = User::factory()->create();
        $website = Website::factory()->connected()->for($user)->create();
        $post = BlogPost::factory()->published()->for($user)->for($website)->create();

        $website->publishingLogs()->create([
            'post_id' => $post->id,
            'attempt' => 1,
            'status' => 'success',
            'http_status' => 201,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee($post->title);
        $response->assertSee('HTTP 201');
    }
}
