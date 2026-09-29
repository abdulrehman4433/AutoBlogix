<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use Illuminate\Support\Collection;

/**
 * The scheduler's due-work step (Phase 7).
 *
 * Picks the posts whose scheduled time has arrived and hands each one to
 * PublishingService::requestPublish() — the same idempotent entry point the
 * "Publish now" button uses, so no publishing rule is ever re-implemented
 * here. The stuck-attempt sweep lives in PublishingService (it owns the
 * finalization path) and is run alongside by run().
 */
class ScheduledPublishingService
{
    public function __construct(private readonly PublishingService $publishing) {}

    /**
     * Publish every post whose scheduled time (UTC) has arrived. The query
     * is system-wide: a scheduler run is an infrastructure action, not a
     * user-scoped one, so all users' due posts are processed — exactly what
     * the cron would do.
     *
     * @return array{due: int, queued: int, already: int, skipped: int}
     */
    public function publishDue(): array
    {
        /** @var Collection<int, BlogPost> $due */
        $due = BlogPost::query()
            ->where('status', PostStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $result = ['due' => $due->count(), 'queued' => 0, 'already' => 0, 'skipped' => 0];

        foreach ($due as $post) {
            $outcome = $this->publishing->requestPublish($post);

            if ($outcome === 'queued') {
                $result['queued']++;
            } elseif ($outcome === 'not_publishable') {
                $result['skipped']++;
            } else {
                $result['already']++;
            }
        }

        return $result;
    }

    /**
     * One full scheduler step: publish what is due, then recover attempts
     * stuck in `publishing` after a stopped queue worker. Never throws.
     *
     * @return array{due: int, queued: int, already: int, skipped: int, swept: int}
     */
    public function run(): array
    {
        $result = $this->publishDue();
        $result['swept'] = $this->publishing->sweepTimedOutPublishing();

        return $result;
    }
}
