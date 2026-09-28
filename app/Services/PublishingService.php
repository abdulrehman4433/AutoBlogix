<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Enums\PublishingLogStatus;
use App\Models\BlogPost;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

/**
 * Publishing orchestration. Phase 3 owns the inbound /publish-result
 * callback (idempotent status recording); Phase 5 adds outbound publishing.
 */
class PublishingService
{
    /**
     * Record a publish outcome reported by the WordPress plugin.
     *
     * Idempotent under plugin retries and double-delivery: the post row is
     * locked, an already-final post returns `already_recorded` without any
     * further writes, and the publishing log is closed exactly once.
     *
     * @param  array{
     *     idempotency_key: string,
     *     success: bool,
     *     wordpress_post_id?: int,
     *     url?: string,
     *     message?: string,
     * }  $data
     * @return array<string, mixed>|null Null when no post matches the key for
     *                                   this website (caller maps to 422).
     */
    public function recordPluginResult(Website $website, array $data): ?array
    {
        /** @var array<string, mixed>|null $result */
        $result = DB::transaction(function () use ($website, $data): ?array {
            $post = BlogPost::query()
                ->where('website_id', $website->id)
                ->where('publish_idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($post === null) {
                return null;
            }

            // Already-final posts are never touched again (double delivery).
            if ($post->status === PostStatus::Published) {
                return $this->resultFor($post, alreadyRecorded: true);
            }

            if (! $data['success'] && $post->status === PostStatus::Failed) {
                return $this->resultFor($post, alreadyRecorded: true);
            }

            if ($data['success']) {
                $post->status = PostStatus::Published;
                $post->published_at ??= now();
                $post->wordpress_post_id = $data['wordpress_post_id'];
                $post->wordpress_url = $data['url'];
                $post->failure_reason = null;
            } else {
                $post->status = PostStatus::Failed;
                $post->failure_reason = $data['message'];
            }

            $post->save();

            $this->closePublishingLog($post, $data);

            return $this->resultFor($post, alreadyRecorded: false);
        });

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFor(BlogPost $post, bool $alreadyRecorded): array
    {
        return [
            'post_id' => $post->id,
            'status' => $post->status->value,
            'wordpress_post_id' => $post->wordpress_post_id,
            'url' => $post->wordpress_url,
            'failure_reason' => $post->failure_reason,
            'already_recorded' => $alreadyRecorded,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Close the latest in-flight publishing attempt (creating a row when the
     * plugin reports back before one existed), so exactly one terminal log
     * entry exists per attempt.
     *
     * @param  array<string, mixed>  $data
     */
    private function closePublishingLog(BlogPost $post, array $data): void
    {
        $status = $data['success'] ? PublishingLogStatus::Success : PublishingLogStatus::Failed;
        $summary = $data['success'] ? 'Publish result reported by the WordPress plugin.' : null;
        $error = $data['success'] ? null : $data['message'];

        $log = $post->publishingLogs()->latest('id')->first();

        if ($log === null) {
            $post->publishingLogs()->create([
                'website_id' => $post->website_id,
                'attempt' => 1,
                'status' => $status,
                'response_summary' => $summary,
                'error_message' => $error,
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            return;
        }

        if (! in_array($log->status, [PublishingLogStatus::Pending, PublishingLogStatus::Processing], true)) {
            return;
        }

        $log->status = $status;
        $log->response_summary = $summary;
        $log->error_message = $error;
        $log->completed_at = now();
        $log->save();
    }
}
