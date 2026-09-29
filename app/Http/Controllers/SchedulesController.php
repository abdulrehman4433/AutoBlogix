<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Services\ScheduledPublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchedulesController extends Controller
{
    public function __construct(private readonly ScheduledPublishingService $scheduler) {}

    /**
     * The session user's scheduled posts: overdue first (missed while the
     * server was down, or simply waiting for the next scheduler tick), then
     * upcoming — times rendered in each website's timezone, same rule as the
     * posts module.
     */
    public function index(Request $request): View
    {
        $now = now();

        $scheduled = $request->user()
            ->blogPosts()
            ->with('website')
            ->where('status', PostStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return view('schedules.index', [
            'overdue' => $scheduled
                ->filter(fn (BlogPost $post): bool => $post->scheduled_at->lessThan($now))
                ->values(),
            'upcoming' => $scheduled
                ->filter(fn (BlogPost $post): bool => $post->scheduled_at->greaterThanOrEqualTo($now))
                ->values(),
        ]);
    }

    /**
     * "Run scheduler now" — the manual equivalent of one cron tick, for
     * environments without a scheduled task set up. System-wide by design
     * (the cron would process every user's due posts); idempotent, DB-only,
     * and never throws.
     */
    public function run(): RedirectResponse
    {
        $result = $this->scheduler->run();

        return redirect()->route('schedules.index')->with('success', sprintf(
            'Scheduler ran: %d due post(s), %d queued, %d stuck post(s) recovered.',
            $result['due'],
            $result['queued'],
            $result['swept'],
        ));
    }
}
