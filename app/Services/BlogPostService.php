<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Blog post lifecycle. Controllers stay thin; status transitions follow the
 * Phase 4 rule (draft ↔ scheduled only — publish/AI statuses are owned by
 * Phases 5 and 6 and are never overwritten by editing).
 */
class BlogPostService
{
    /**
     * @param  array<string, mixed>  $attributes  Validated input.
     */
    public function create(User $user, array $attributes): BlogPost
    {
        $website = Website::query()
            ->whereKey($attributes['website_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $post = new BlogPost;
        $post->user()->associate($user);
        $post->website()->associate($website);
        $post->title = $attributes['title'];
        $post->slug = $this->uniqueSlug($website, $attributes['title']);
        $post->topic = $attributes['topic'] ?? null;
        $post->excerpt = $attributes['excerpt'] ?? null;
        $post->content = $attributes['content'] ?? null;
        $post->category = $attributes['category'] ?? null;
        $post->meta_description = $attributes['meta_description'] ?? null;
        $post->tags = $attributes['tags'] ?? [];
        $post->keywords = $attributes['keywords'] ?? [];
        $post->source = PostSource::Manual;
        $post->scheduled_at = $this->scheduleToUtc($attributes['scheduled_at'] ?? null, $website);
        $post->status = $post->scheduled_at !== null
            ? PostStatus::Scheduled
            : PostStatus::Draft;
        $post->save();

        return $post;
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated input.
     */
    public function update(BlogPost $post, array $attributes): BlogPost
    {
        if (
            array_key_exists('website_id', $attributes)
            && (int) $attributes['website_id'] !== $post->website_id
        ) {
            // Clear the cached relation so the schedule is re-interpreted
            // against the new website's timezone (as validation did).
            $post->unsetRelation('website');
            $post->website_id = (int) $attributes['website_id'];
        }

        foreach (['title', 'topic', 'excerpt', 'content', 'category', 'meta_description', 'tags', 'keywords'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $post->{$field} = $attributes[$field];
            }
        }

        if (array_key_exists('scheduled_at', $attributes)) {
            $post->scheduled_at = $this->scheduleToUtc($attributes['scheduled_at'], $post->website);
        }

        if ($post->slug === null || $post->slug === '') {
            $post->slug = $this->uniqueSlug($post->website, $post->title);
        }

        // Status only moves between draft and scheduled; publish outcomes
        // (Phase 5) and AI states (Phase 6) survive edits untouched.
        if (in_array($post->status, [PostStatus::Draft, PostStatus::Scheduled], true)) {
            $post->status = $post->scheduled_at !== null
                ? PostStatus::Scheduled
                : PostStatus::Draft;
        }

        $post->save();

        return $post;
    }

    /**
     * Delete a post; publishing logs cascade at the database level.
     */
    public function delete(BlogPost $post): void
    {
        $post->delete();
    }

    /**
     * Interpret the wall time in the website's timezone and store it in UTC
     * (validation has already proven it parses and is a valid schedule).
     */
    private function scheduleToUtc(?string $scheduledAt, Website $website): ?Carbon
    {
        if ($scheduledAt === null || $scheduledAt === '') {
            return null;
        }

        return Carbon::parse($scheduledAt, $website->timezone ?: config('app.timezone'))
            ->setTimezone('UTC');
    }

    /**
     * Title-derived slug, unique per website (suffix loop). Local display
     * only — WordPress assigns the final slug when the post is published.
     */
    private function uniqueSlug(Website $website, string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'post-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        while ($website->blogPosts()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
