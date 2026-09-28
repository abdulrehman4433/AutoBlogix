<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebsiteStatus;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'url',
    'status',
    'wordpress_version',
    'plugin_version',
    'last_connected_at',
    'last_sync_at',
    'timezone',
])]
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebsiteStatus::class,
            'last_connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'encrypted_api_secret' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<BlogPost, $this> */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    /** @return HasMany<ConnectionLog, $this> */
    public function connectionLogs(): HasMany
    {
        return $this->hasMany(ConnectionLog::class);
    }

    /** @return HasMany<PublishingLog, $this> */
    public function publishingLogs(): HasMany
    {
        return $this->hasMany(PublishingLog::class);
    }

    /**
     * Masked hint of the API secret (last 4 characters) — the full secret is
     * only ever shown once at creation/rotation.
     */
    public function maskedSecretHint(): ?string
    {
        $secret = $this->encrypted_api_secret;

        if ($secret === null || $secret === '') {
            return null;
        }

        return '••••'.substr($secret, -4);
    }
}
