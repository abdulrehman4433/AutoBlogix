<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one publish attempt for a post. Failures are recorded as data (post
 * status + publishing log), never thrown, so the sync and database queue
 * drivers behave identically — a retry is a fresh PublishNow request.
 */
class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt per dispatch; retrying is a new requestPublish().
     */
    public int $tries = 1;

    public function __construct(public readonly int $postId) {}

    public function handle(PublishingService $publishing): void
    {
        $publishing->executeAttempt($this->postId);
    }
}
