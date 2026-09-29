<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostStatus;
use App\Enums\PublishingLogStatus;
use App\Jobs\PublishPostJob;
use App\Models\BlogPost;
use App\Models\PublishingLog;
use App\Models\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Publishing orchestration.
 *
 * Inbound (Phase 3): recordPluginResult() — the plugin's /publish-result
 * callback, idempotent under double delivery.
 * Outbound (Phase 5): requestPublish() — the idempotent entry point used by
 * the "Publish now" button and, from Phase 7, the scheduler — and
 * executeAttempt() — the locked HTTP attempt run by PublishPostJob.
 * Both directions share applyOutcome() so the final state always wins.
 */
class PublishingService
{
    public function __construct(private readonly WordPressPublishingClient $client) {}

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

            $this->applyOutcome(
                $post,
                success: $data['success'],
                wordpressPostId: $data['wordpress_post_id'] ?? null,
                url: $data['url'] ?? null,
                message: $data['message'] ?? null,
            );

            return $this->resultFor($post, alreadyRecorded: false);
        });

        return $result;
    }

    /**
     * Idempotent entry point for publishing: "Publish now" now, Phase 7's
     * scheduler later. Returns a result string — never throws.
     *
     * Rules (spec): within a transaction and row lock, a draft first moves
     * to scheduled (the user explicitly asked for immediate publishing) so
     * that only `PostStatus::publishable()` states (scheduled/failed) can
     * enter `publishing`; anything else is a no-op.
     *
     * @return 'queued'|'already_publishing'|'published'|'not_publishable'
     */
    public function requestPublish(BlogPost $post): string
    {
        $outcome = DB::transaction(function () use ($post): string {
            $locked = BlogPost::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PostStatus::Publishing) {
                return 'already_publishing';
            }

            if ($locked->status === PostStatus::Published) {
                return 'published';
            }

            /*
             * "Publish now" on a draft — or on AI-generated content —
             * records the immediate schedule and enters publishing through
             * the regular scheduled state, keeping the "only
             * scheduled/failed may publish" rule intact.
             */
            if (in_array($locked->status, [PostStatus::Draft, PostStatus::Generated], true)) {
                $locked->scheduled_at ??= now();
                $locked->status = PostStatus::Scheduled;
                $locked->save();
            }

            if (! in_array($locked->status, PostStatus::publishable(), true)) {
                return 'not_publishable';
            }

            // Stable across retries: a retry after a lost response must not
            // create a second post on WordPress.
            $locked->publish_idempotency_key ??= (string) Str::uuid();
            $locked->status = PostStatus::Publishing;
            $locked->save();

            $this->openPublishingLog($locked);

            return 'queued';
        });

        if ($outcome === 'queued') {
            PublishPostJob::dispatch($post->id);
        }

        return $outcome;
    }

    /**
     * Run one publish attempt (called by PublishPostJob). Never throws —
     * every failure mode ends as data: `failed` status + closed log.
     */
    public function executeAttempt(int $postId): void
    {
        $lock = Cache::lock(
            'autoblogix:publish:'.$postId,
            (int) config('wordpress.timeout') + 30,
        );

        if (! $lock->get()) {
            return; // another attempt is already in flight
        }

        try {
            $post = DB::transaction(function () use ($postId): ?BlogPost {
                $locked = BlogPost::query()->whereKey($postId)->lockForUpdate()->first();

                if ($locked === null || $locked->status !== PostStatus::Publishing) {
                    return null;
                }

                $log = $this->currentLog($locked);
                $log->status = PublishingLogStatus::Processing;
                $log->save();

                return $locked;
            });

            if ($post === null) {
                return;
            }

            try {
                $result = $this->client->publish($post->website, $this->payloadFor($post));
            } catch (\Throwable $exception) {
                Log::error('WordPress publish attempt crashed', [
                    'post_id' => $postId,
                    'website_id' => $post->website_id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $result = [
                    'ok' => false,
                    'http_status' => null,
                    'wordpress_post_id' => null,
                    'url' => null,
                    'failure_reason' => 'Publishing failed unexpectedly. Try again.',
                    'technical' => $exception::class.': '.$exception->getMessage(),
                ];
            }

            $this->finalize($postId, $result);
        } finally {
            $lock->release();
        }
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
     * Record the outbound attempt's outcome on the post and its log.
     *
     * @param  array{
     *     ok: bool,
     *     http_status: int|null,
     *     wordpress_post_id: int|null,
     *     url: string|null,
     *     failure_reason: string|null,
     *     technical: string|null,
     * }  $result
     */
    private function finalize(int $postId, array $result): void
    {
        if (! $result['ok']) {
            Log::warning('WordPress publish attempt failed', [
                'post_id' => $postId,
                'http_status' => $result['http_status'],
                'technical' => $result['technical'],
            ]);
        }

        DB::transaction(function () use ($postId, $result): void {
            $post = BlogPost::query()->whereKey($postId)->lockForUpdate()->first();

            // Deleted mid-flight (logs cascade), or the plugin's callback
            // already finalized this attempt — the final state wins.
            if ($post === null || $post->status !== PostStatus::Publishing) {
                return;
            }

            $this->applyOutcome(
                $post,
                success: $result['ok'],
                wordpressPostId: $result['wordpress_post_id'],
                url: $result['url'],
                message: $result['failure_reason'],
                httpStatus: $result['http_status'],
                technical: $result['technical'],
            );
        });
    }

    /**
     * Apply a final outcome to a post and close its in-flight log. Shared
     * by the sync outbound response and the plugin's publish-result callback
     * (caller must hold the row lock).
     */
    private function applyOutcome(
        BlogPost $post,
        bool $success,
        ?int $wordpressPostId = null,
        ?string $url = null,
        ?string $message = null,
        ?int $httpStatus = null,
        ?string $technical = null,
    ): void {
        if ($success) {
            $post->status = PostStatus::Published;
            $post->published_at ??= now();
            $post->wordpress_post_id = $wordpressPostId;
            $post->wordpress_url = $url;
            $post->failure_reason = null;
        } else {
            $post->status = PostStatus::Failed;
            $post->failure_reason = $message;
        }

        $post->save();

        $this->closePublishingLog($post, $success, $message, $httpStatus, $technical);
    }

    /**
     * Close the latest in-flight publishing attempt (creating a row when the
     * plugin reports back before one existed), so exactly one terminal log
     * entry exists per attempt. Technical detail lands in `error_message`;
     * the friendly wording lives on the post's `failure_reason`.
     */
    private function closePublishingLog(
        BlogPost $post,
        bool $success,
        ?string $message,
        ?int $httpStatus = null,
        ?string $technical = null,
    ): void {
        $summary = $success ? "Published as WordPress post {$post->wordpress_post_id}." : null;
        $error = $success ? null : ($technical ?? $message);

        $log = $post->publishingLogs()->latest('id')->first();

        if ($log === null) {
            $post->publishingLogs()->create([
                'website_id' => $post->website_id,
                'attempt' => 1,
                'status' => $success ? PublishingLogStatus::Success : PublishingLogStatus::Failed,
                'http_status' => $httpStatus,
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

        $log->status = $success ? PublishingLogStatus::Success : PublishingLogStatus::Failed;
        $log->http_status = $httpStatus ?? $log->http_status;
        $log->response_summary = $summary;
        $log->error_message = $error;
        $log->completed_at = now();
        $log->save();
    }

    /**
     * Open the attempt row when the publish is requested (pending), so the
     * history shows the request even if the worker never picks it up.
     */
    private function openPublishingLog(BlogPost $post): PublishingLog
    {
        return $post->publishingLogs()->create([
            'website_id' => $post->website_id,
            'attempt' => $this->nextAttempt($post),
            'status' => PublishingLogStatus::Pending,
            'started_at' => now(),
        ]);
    }

    /**
     * The attempt row this run owns: the still-open one, or a fresh one.
     */
    private function currentLog(BlogPost $post): PublishingLog
    {
        $log = $post->publishingLogs()->latest('id')->first();

        if ($log !== null && in_array($log->status, [PublishingLogStatus::Pending, PublishingLogStatus::Processing], true)) {
            return $log;
        }

        return $this->openPublishingLog($post);
    }

    private function nextAttempt(BlogPost $post): int
    {
        return (int) ($post->publishingLogs()->max('attempt') ?? 0) + 1;
    }

    /**
     * JSON payload for the plugin's publish endpoint. Nullable fields are
     * omitted rather than sent as null; content is the raw HTML destined
     * for WordPress (the UI sanitizer never touches this path).
     *
     * @return array<string, mixed>
     */
    private function payloadFor(BlogPost $post): array
    {
        $payload = [
            'idempotency_key' => $post->publish_idempotency_key,
            'post_id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'category' => $post->category,
            'tags' => array_values((array) ($post->tags ?? [])),
            'keywords' => array_values((array) ($post->keywords ?? [])),
            'meta_description' => $post->meta_description,
        ];

        return array_filter(
            $payload,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }
}
