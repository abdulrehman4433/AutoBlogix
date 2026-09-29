<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScheduledPublishingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:run-scheduler')]
#[Description('Publish posts whose scheduled time has arrived and recover attempts stuck by a stopped queue worker')]
class RunScheduler extends Command
{
    /**
     * Execute the console command.
     *
     * Registered in routes/console.php to run every minute (cron calls
     * `schedule:run`; dev can use `schedule:work`). Dispatches only — the
     * scheduled `queue:work --stop-when-empty` entry finishes the jobs.
     */
    public function handle(ScheduledPublishingService $scheduler): int
    {
        $result = $scheduler->run();

        $this->info(sprintf(
            'Due: %d (queued %d, already handled %d, skipped %d); stuck recovered: %d.',
            $result['due'],
            $result['queued'],
            $result['already'],
            $result['skipped'],
            $result['swept'],
        ));

        return self::SUCCESS;
    }
}
