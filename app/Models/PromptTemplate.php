<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PromptTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global, seeded prompt texts. Resolution order: database row first,
 * config('ai.defaults.*') as fallback — so generation works even before
 * the seeder ran.
 *
 * @extends Builder<PromptTemplate>
 */
class PromptTemplate extends Model
{
    /** @use HasFactory<PromptTemplateFactory> */
    use HasFactory;

    /**
     * Attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'system_prompt',
        'user_prompt',
    ];

    /**
     * Resolve a template by key with its config fallback.
     *
     * @return array{key: string, name: string, system: string, user: string}
     */
    public static function resolve(string $key): array
    {
        $row = static::query()->where('key', $key)->first();

        if ($row instanceof self) {
            return [
                'key' => $row->key,
                'name' => $row->name,
                'system' => $row->system_prompt,
                'user' => $row->user_prompt,
            ];
        }

        /** @var array{name?: string, system?: string, user?: string}|mixed $default */
        $default = config('ai.defaults.'.$key);

        if (is_array($default) && isset($default['system'], $default['user'])) {
            return [
                'key' => $key,
                'name' => (string) ($default['name'] ?? $key),
                'system' => (string) $default['system'],
                'user' => (string) $default['user'],
            ];
        }

        // Last resort: a minimal working prompt (config should define it).
        return [
            'key' => $key,
            'name' => $key,
            'system' => 'You are a blog writer. Respond with a single JSON object only.',
            'user' => 'Write a blog post titled :title (topic: :topic, tone: :tone, about :length_words words). '
                .'Keys: "content", "excerpt", "tags", "keywords", "meta_description".',
        ];
    }
}
