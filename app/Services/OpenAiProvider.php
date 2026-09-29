<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenAI-compatible chat-completions client (works with OpenAI and any
 * endpoint speaking the same protocol via `base_url`). Bearer auth, one
 * HTTP attempt per generation, every failure mapped to a friendly
 * AiProviderException with the technical detail kept for the logs.
 */
class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly string $apiKey,
        private readonly int $timeout,
    ) {}

    public function generate(string $systemPrompt, string $userPrompt, array $input): array
    {
        $url = rtrim($this->baseUrl, '/').'/chat/completions';

        $body = json_encode([
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.7,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            throw new AiProviderException(
                'The AI request could not be prepared. Try again.',
                'json_encode: '.json_last_error_msg(),
            );
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new AiProviderException(
                'Could not reach the AI provider. Check your connection and try again.',
                $exception::class.': '.$exception->getMessage(),
            );
        }

        return $this->interpret($response);
    }

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Map the HTTP response to the documented content shape.
     *
     * @return array{
     *     content: string,
     *     excerpt: string,
     *     tags: array<int, string>,
     *     keywords: array<int, string>,
     *     meta_description: string|null,
     *     tokens_used: int|null,
     * }
     *
     * @throws AiProviderException
     */
    private function interpret(Response $response): array
    {
        $status = $response->status();
        $technical = 'HTTP '.$status.': '.Str::limit($response->body(), 500);

        if ($status === 401 || $status === 403) {
            throw new AiProviderException('The AI provider rejected the API key. Check it on the AI Providers page.', $technical);
        }

        if ($status === 404) {
            throw new AiProviderException('The AI provider endpoint or model was not found. Check the model and base URL.', $technical);
        }

        if ($status === 429) {
            throw new AiProviderException('The AI provider is rate limiting this account. Try again in a minute.', $technical);
        }

        if ($status >= 500) {
            throw new AiProviderException("The AI provider returned a server error (HTTP {$status}). Try again later.", $technical);
        }

        if ($status < 200 || $status >= 300) {
            throw new AiProviderException("The AI provider rejected the request (HTTP {$status}).", $technical);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new AiProviderException('The AI provider returned an unexpected response.', $technical);
        }

        $content = $json['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new AiProviderException('The AI provider returned an unexpected response.', $technical);
        }

        $parsed = AiResponseParser::parse($content);

        $tokens = (int) ($json['usage']['total_tokens'] ?? 0);
        $parsed['tokens_used'] = $tokens > 0 ? $tokens : null;

        return $parsed;
    }
}
