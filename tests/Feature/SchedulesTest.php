<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\PublishingLogStatus;
use App\Models\BlogPost;
use App\Models\PublishingLog;
use App\Models\User;
use App\Models\Website;
use DateTimeInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchedulesTest extends TestCase
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
    // Access & index page
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_from_the_schedules_pages(): void
    {
        $this->get(route('schedules.index'))->assertRedirect(route('login'));
        $this->post(route('schedules.run'))->assertRedirect(route('login'));
    }

    public function test_navigation_shows_the_schedules_link(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Schedules');
    }

    public function test_index_lists_only_my_scheduled_posts_ordered_with_site_time_and_overdue_badge(): void
    {
        $this->scheduledPost('Overdue post alpha', now()->subHours(2));
        $upcoming = $this->scheduledPost('Upcoming post beta', now()->addDays(2)->setTime(10, 30));
        $this->scheduledPost('Draft post gamma', null, PostStatus::Draft);

        $otherUser = User::factory()->create();
        BlogPost::factory()->scheduled(now()->addHour())->create([
            'user_id' => $otherUser->id,
            'website_id' => $this->website->id,
            'title' => 'Foreign post delta',
        ]);

        $response = $this->actingAs($this->user)->get(route('schedules.index'));

        $response->assertOk()
            ->assertSee('Overdue post alpha')
            ->assertSee('Upcoming post beta')
            ->assertSee('2 hours ago') // overdue row shows relative time
            ->assertDontSee('Draft post gamma')
            ->assertDontSee('Foreign post delta');

        // Website timezone rendering (default website timezone is UTC).
        $response->assertSee($upcoming->scheduledAtSiteTime()->format('M j, Y H:i'));

        // Overdue rows sort before upcoming ones.
        $content = (string) $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Upcoming post beta'),
            strpos($content, 'Overdue post alpha'),
        );
    }

    public function test_index_shows_the_empty_state_when_nothing_is_scheduled(): void
    {
        $this->scheduledPost('Just a draft', null, PostStatus::Draft);

        $this->actingAs($this->user)
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Nothing scheduled yet.')
            ->assertDontSee('Just a draft');
    }

    // ------------------------------------------------------------------
    // Manual scheduler run (due publishing)
    // ------------------------------------------------------------------

    public function test_run_publishes_due_posts_system_wide_and_reports_exact_counts(): void
    {
        Http::fake(['*' => Http::response($this->successBody(), 200)]);

        $due = $this->scheduledPost('Due post now', now()->subMinutes(5));
        $future = $this->scheduledPost('Future post later', now()->addDay());

        $otherUser = User::factory()->create();
        $otherWebsite = Website::factory()->withCredentials()->create([
            'user_id' => $otherUser->id,
            'url' => 'https://other-user.example.com',
        ]);
        $otherDue = BlogPost::factory()->scheduled(now()->subMinutes(1))->create([
            'user_id' => $otherUser->id,
            'website_id' => $otherWebsite->id,
            'title' => 'Foreign due post',
        ]);

        $this->actingAs($this->user)
            ->post(route('schedules.run'))
            ->assertRedirect(route('schedules.index'))
            ->assertSessionHas(
                'success',
                'Scheduler ran: 2 due post(s), 2 queued, 0 stuck post(s) recovered.',
            );

        $due->refresh();
        $this->assertSame(PostStatus::Published, $due->status);
        $this->assertSame(123, $due->wordpress_post_id);
        $this->assertNotNull($due->published_at);
        $this->assertNull($due->failure_reason);

        // The scheduler is system-wide: another user's due post publishes too.
        $this->assertSame(PostStatus::Published, $otherDue->refresh()->status);
        $this->assertSame(PostStatus::Scheduled, $future->refresh()->status);
    }

    public function test_run_leaves_every_non_scheduled_status_untouched(): void
    {
        Http::fake();

        $statuses = [
            PostStatus::Draft,
            PostStatus::Generating,
            PostStatus::Generated,
            PostStatus::Failed,
            PostStatus::Publishing,
            PostStatus::Published,
        ];

        $expected = [];

        foreach ($statuses as $status) {
            $post = BlogPost::factory()->create([
                'user_id' => $this->user->id,
                'website_id' => $this->website->id,
                'status' => $status,
                'title' => 'Post with status '.$status->value,
                'scheduled_at' => now()->subHour(),
            ]);

            $expected[$post->id] = $status;
        }

        $this->actingAs($this->user)
            ->post(route('schedules.run'))
            ->assertRedirect(route('schedules.index'))
            ->assertSessionHas(
                'success',
                'Scheduler ran: 0 due post(s), 0 queued, 0 stuck post(s) recovered.',
            );

        foreach ($expected as $postId => $status) {
            $this->assertSame($status, BlogPost::query()->findOrFail($postId)->status);
        }

        $this->assertSame(0, PublishingLog::query()->count());
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Stuck-attempt sweep
    // ------------------------------------------------------------------

    public function test_sweep_recovers_a_post_stuck_with_a_stale_pending_log(): void
    {
        Http::fake();

        $post = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => PostStatus::Publishing,
            'title' => 'Stuck post',
            'publish_idempotency_key' => 'stuck-key-123',
        ]);
        $log = PublishingLog::query()->create([
            'post_id' => $post->id,
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Pending,
            'started_at' => now()->subSeconds(200), // timeout 30 + grace 60
        ]);

        $this->actingAs($this->user)
            ->post(route('schedules.run'))
            ->assertSessionHas(
                'success',
                'Scheduler ran: 0 due post(s), 0 queued, 1 stuck post(s) recovered.',
            );

        $post->refresh();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertStringContainsString('Publishing timed out after 30 seconds', (string) $post->failure_reason);
        $this->assertStringContainsString('Retry publish', (string) $post->failure_reason);
        $this->assertStringNotContainsString('Swept', (string) $post->failure_reason); // technical stays in the log

        $log->refresh();
        $this->assertSame(PublishingLogStatus::Failed, $log->status);
        $this->assertStringContainsString('Swept:', (string) $log->error_message);
        $this->assertStringContainsString('grace', (string) $log->error_message);
        $this->assertNotNull($log->completed_at);
        $this->assertSame('stuck-key-123', $post->publish_idempotency_key); // retry reuses it
        Http::assertNothingSent();
    }

    public function test_sweep_ignores_fresh_attempts_and_recently_closed_logs(): void
    {
        Http::fake();

        $inFlight = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => PostStatus::Publishing,
            'title' => 'In-flight post',
        ]);
        $openLog = PublishingLog::query()->create([
            'post_id' => $inFlight->id,
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Processing,
            'started_at' => now()->subSeconds(10), // well inside timeout + grace
        ]);

        $closed = BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => PostStatus::Publishing,
            'title' => 'Closed-log post',
        ]);
        $terminalLog = PublishingLog::query()->create([
            'post_id' => $closed->id,
            'website_id' => $this->website->id,
            'attempt' => 1,
            'status' => PublishingLogStatus::Failed,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
        ]);
        $originalCompletedAt = $terminalLog->completed_at;

        $this->actingAs($this->user)
            ->post(route('schedules.run'))
            ->assertSessionHas(
                'success',
                'Scheduler ran: 0 due post(s), 0 queued, 0 stuck post(s) recovered.',
            );

        $this->assertSame(PostStatus::Publishing, $inFlight->refresh()->status);
        $this->assertSame(PublishingLogStatus::Processing, $openLog->refresh()->status);

        $terminalLog->refresh();
        $this->assertSame(PostStatus::Publishing, $closed->refresh()->status);
        $this->assertSame(PublishingLogStatus::Failed, $terminalLog->status);
        $this->assertTrue($terminalLog->completed_at->equalTo($originalCompletedAt));
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Command & schedule registration
    // ------------------------------------------------------------------

    public function test_scheduler_command_runs_the_flow_and_prints_the_summary(): void
    {
        Http::fake(['*' => Http::response($this->successBody(), 200)]);

        $due = $this->scheduledPost('Command due post', now()->subMinutes(3));

        $this->artisan('posts:run-scheduler')
            ->expectsOutputToContain('Due: 1 (queued 1, already handled 0, skipped 0); stuck recovered: 0.')
            ->assertSuccessful();

        $this->assertSame(PostStatus::Published, $due->refresh()->status);
    }

    public function test_the_schedule_registers_the_scheduler_and_the_queue_runner(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();

        $commands = collect($this->app->make(Schedule::class)->events())
            ->map(static fn (object $event): string => (string) ($event->command ?? ''))
            ->implode(' ');

        $this->assertStringContainsString('posts:run-scheduler', $commands);
        $this->assertStringContainsString('queue:work --stop-when-empty', $commands);
    }

    // ------------------------------------------------------------------

    private function scheduledPost(string $title, ?DateTimeInterface $at, PostStatus $status = PostStatus::Scheduled): BlogPost
    {
        return BlogPost::factory()->create([
            'user_id' => $this->user->id,
            'website_id' => $this->website->id,
            'status' => $status,
            'title' => $title,
            'scheduled_at' => $at,
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
