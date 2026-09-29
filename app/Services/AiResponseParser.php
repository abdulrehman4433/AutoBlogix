<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Str;

/**
 * Defensive parser for model output: real responses arrive as JSON, as
 * ```json fenced blocks, or as prose around a JSON object — and keys can
 * have the wrong types. Everything is normalized to the documented shape;
 * anything unparseable becomes a friendly AiProviderException.
 */
class AiResponseParser
{
    /**
     * @return array{
     *     content: string,
     *     excerpt: string,
     *     tags: array<int, string>,
     *     keywords: array<int, string>,
     *     meta_description: string|null,
     *     tokens_used: int|null,
     * }
     *
     * @throws AiProviderException When the payload cannot be understood.
     */
    public static function parse(string $raw): array
    {
        $decoded = self::extract($raw);

        // Some models use content_html for the body.
        $content = $decoded['content'] ?? $decoded['content_html'] ?? null;
        $content = is_string($content) ? trim($content) : '';

        if ($content === '') {
            throw new AiProviderException(
                'The AI returned an empty post. Try again.',
                'missing/empty content: '.Str::limit($raw, 300),
            );
        }

        $excerpt = $decoded['excerpt'] ?? null;
        $excerpt = is_string($excerpt) && trim($excerpt) !== ''
            ? trim($excerpt)
            : Str::limit(strip_tags($content), 200);

        $meta = $decoded['meta_description'] ?? null;
        $meta = is_string($meta) && trim($meta) !== ''
            ? Str::limit(trim($meta), 300, '')
            : null;

        return [
            'content' => $content,
            'excerpt' => Str::limit($excerpt, 2000),
            'tags' => self::stringList($decoded['tags'] ?? null, 5),
            'keywords' => self::stringList($decoded['keywords'] ?? null, 10),
            'meta_description' => $meta,
            'tokens_used' => null,
        ];
    }

    /**
     * Pull the JSON object out of whatever the model returned.
     *
     * @return array<string, mixed>
     *
     * @throws AiProviderException
     */
    private static function extract(string $raw): array
    {
        $text = trim($raw);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $text, $matches) === 1) {
            $text = trim($matches[1]);
        }

        // Outermost braces cut away any surrounding prose.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new AiProviderException(
                'The AI returned an unexpected response format. Try again.',
                'json_decode: '.(json_last_error_msg() ?: 'not an object')
                    .' | snippet: '.Str::limit($raw, 300),
            );
        }

        return $decoded;
    }

    /**
     * Coerce a value into a clean, unique string list (arrays or a
     * comma-separated string accepted; junk entries dropped).
     *
     * @return array<int, string>
     */
    private static function stringList(mixed $value, int $max): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if (is_int($entry) || is_float($entry)) {
                $entry = (string) $entry;
            }

            if (! is_string($entry)) {
                continue;
            }

            $entry = Str::limit(trim($entry), 50, '');

            if ($entry === '') {
                continue;
            }

            $entries[$entry] = $entry;

            if (count($entries) >= $max) {
                break;
            }
        }

        return array_values($entries);
    }
}
