<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiLogStatus;
use Database\Factories\AiLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI generation attempt: processing → success | failed, with the
 * technical failure detail kept here (never on the post itself).
 *
 * @extends Builder<AiLog>
 */
class AiLog extends Model
{
    /** @use HasFactory<AiLogFactory> */
    use HasFactory;

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'post_id',
        'prompt_key',
        'provider',
        'model',
        'status',
        'tokens_used',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiLogStatus::class,
            'tokens_used' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }
}
