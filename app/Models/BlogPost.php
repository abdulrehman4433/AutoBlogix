<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'title',
    'slug',
    'topic',
    'excerpt',
    'content',
    'status',
    'source',
    'category',
    'tags',
    'meta_description',
    'keywords',
    'ai_provider',
    'ai_model',
    'scheduled_at',
    'published_at',
    'wordpress_post_id',
    'wordpress_url',
    'failure_reason',
])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'source' => PostSource::class,
            'tags' => 'array',
            'keywords' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'wordpress_post_id' => 'integer',
            'publish_idempotency_key' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /** @return HasMany<PublishingLog, $this> */
    public function publishingLogs(): HasMany
    {
        return $this->hasMany(PublishingLog::class, 'post_id');
    }

    /** @return HasMany<AiLog, $this> */
    public function aiLogs(): HasMany
    {
        return $this->hasMany(AiLog::class, 'post_id');
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published && $this->wordpress_post_id !== null;
    }

    /**
     * The scheduled time converted to the owning website's local timezone
     * (input is entered and stored per this same timezone).
     */
    public function scheduledAtSiteTime(): ?Carbon
    {
        return $this->scheduled_at?->setTimezone($this->website->timezone ?: config('app.timezone'));
    }

    /**
     * The published time converted to the owning website's local timezone.
     */
    public function publishedAtSiteTime(): ?Carbon
    {
        return $this->published_at?->setTimezone($this->website->timezone ?: config('app.timezone'));
    }
}
