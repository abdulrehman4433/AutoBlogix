<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-user AI provider configuration. The API key uses the `encrypted`
 * cast: it is readable for outbound calls but only a masked hint is ever
 * rendered, and it is never mass assigned.
 *
 * @property-read string|null $api_key
 *
 * @extends Builder<AiProvider>
 */
class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory;

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'model',
        'base_url',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Masked hint of the API key (last 4 characters) — the full key is
     * never re-displayed after saving.
     */
    public function maskedKeyHint(): ?string
    {
        $key = $this->api_key;

        if (! is_string($key) || $key === '') {
            return null;
        }

        return '••••'.substr($key, -4);
    }
}
