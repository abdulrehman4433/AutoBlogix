<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiProviderException;

/**
 * A source of AI-written post content. Implementations must be forgiving
 * about their input and strict about their output: whatever the model
 * returned, the returned array always has the documented shape (the
 * defensive parsing lives behind this interface).
 */
interface AiProviderInterface
{
    /**
     * Produce post content for a generation attempt.
     *
     * @param  array{
     *     title: string,
     *     topic: ?string,
     *     keywords: array<int, string>,
     *     tone: string,
     *     length_words: int,
     * }  $input  Post facts the prompt was built from (the development
     *            provider derives content from these directly).
     * @return array{
     *     content: string,
     *     excerpt: string,
     *     tags: array<int, string>,
     *     keywords: array<int, string>,
     *     meta_description: string|null,
     *     tokens_used: int|null,
     * }
     *
     * @throws AiProviderException On any failure; `friendly` is user-facing.
     */
    public function generate(string $systemPrompt, string $userPrompt, array $input): array;

    /**
     * Provider identifier for ai_logs (`development`, `openai`, ...).
     */
    public function name(): string;

    /**
     * Model identifier for ai_logs, when the provider has one.
     */
    public function model(): ?string;
}
