<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Outbound WordPress publishing client. Signs requests with the HmacSigner
 * contract the plugin verifies (docs/wordpress-api.md §1), sends them with
 * the configured timeout, and maps every failure mode to a friendly message
 * for the user. Technical detail travels in the result's `technical` field
 * for the log — the secret and signature are never included.
 */
class WordPressPublishingClient
{
    public function __construct(private readonly HmacSigner $signer) {}

    /**
     * Ask the plugin to create the post on WordPress.
     *
     * @param  array<string, mixed>  $payload  Decoded payload; encoded exactly
     *                                         once and signed over those bytes.
     * @return array{
     *     ok: bool,
     *     http_status: int|null,
     *     wordpress_post_id: int|null,
     *     url: string|null,
     *     failure_reason: string|null,
     *     technical: string|null,
     * }
     */
    public function publish(Website $website, array $payload): array
    {
        $url = rtrim($website->url, '/').config('wordpress.publish_path');
        $path = parse_url($url, PHP_URL_PATH) ?: config('wordpress.publish_path');

        $secret = $website->encrypted_api_secret;

        if ($website->api_key === null || ! is_string($secret) || $secret === '') {
            return $this->failure(
                null,
                'This website has no API credentials. Rotate them on the website page and update the plugin.',
                'website '.$website->id.' has no credentials',
            );
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            return $this->failure(
                null,
                'The post content could not be prepared for publishing.',
                'json_encode failed: '.json_last_error_msg(),
            );
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = $this->signer->sign('POST', $path, $timestamp, $nonce, $body, $secret);

        try {
            $response = Http::timeout((int) config('wordpress.timeout'))
                ->withHeaders([
                    'X-ABX-Key' => $website->api_key,
                    'X-ABX-Timestamp' => $timestamp,
                    'X-ABX-Nonce' => $nonce,
                    'X-ABX-Signature' => $signature,
                    'Accept' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            return $this->failure(
                null,
                sprintf(
                    'Could not reach %s. Check that the site is online and the AutoBlogix plugin is active.',
                    parse_url($website->url, PHP_URL_HOST) ?: $website->url,
                ),
                $exception::class.': '.$exception->getMessage(),
            );
        }

        return $this->interpret($response);
    }

    /**
     * Turn the HTTP response into a uniform outcome result.
     */
    private function interpret(Response $response): array
    {
        $status = $response->status();

        if ($status === 429) {
            return $this->failure($status, 'The website is rate limiting AutoBlogix. Try again in a minute.', $this->technical($response));
        }

        if ($status === 401 || $status === 403) {
            return $this->failure($status, 'WordPress rejected the API credentials. Rotate them on the website page and update the plugin.', $this->technical($response));
        }

        if ($status === 404 || $status === 405) {
            return $this->failure($status, 'The publish endpoint was not found. Check that the AutoBlogix plugin is installed and active on the site.', $this->technical($response));
        }

        if ($status >= 500) {
            return $this->failure($status, "The website returned a server error (HTTP {$status}). Try again later.", $this->technical($response));
        }

        if ($status < 200 || $status >= 300) {
            return $this->failure($status, "WordPress could not publish the post (HTTP {$status}).", $this->technical($response));
        }

        $json = $response->json();

        if (! is_array($json)) {
            return $this->failure($status, 'The website returned an unexpected response.', $this->technical($response));
        }

        if (($json['success'] ?? null) === true) {
            $wordpressPostId = $json['wordpress_post_id'] ?? null;
            $wordpressUrl = $json['url'] ?? null;

            if (! is_int($wordpressPostId) || $wordpressPostId < 1 || ! is_string($wordpressUrl) || $wordpressUrl === '') {
                return $this->failure($status, 'The website returned an unexpected response.', $this->technical($response));
            }

            return [
                'ok' => true,
                'http_status' => $status,
                'wordpress_post_id' => $wordpressPostId,
                'url' => $wordpressUrl,
                'failure_reason' => null,
                'technical' => null,
            ];
        }

        $message = $json['message'] ?? null;
        $code = $json['code'] ?? null;

        $reason = is_string($message) && trim($message) !== ''
            ? Str::limit(trim($message), 400)
            : (is_string($code) && $code !== ''
                ? "WordPress could not publish the post ({$code})."
                : 'WordPress could not publish the post.');

        return $this->failure($status, $reason, $this->technical($response));
    }

    /**
     * @return array{
     *     ok: bool,
     *     http_status: int|null,
     *     wordpress_post_id: int|null,
     *     url: string|null,
     *     failure_reason: string|null,
     *     technical: string|null,
     * }
     */
    private function failure(?int $httpStatus, string $reason, ?string $technical): array
    {
        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'wordpress_post_id' => null,
            'url' => null,
            'failure_reason' => $reason,
            'technical' => $technical,
        ];
    }

    /**
     * Log-safe technical detail: status + body snippet.
     */
    private function technical(Response $response): string
    {
        return 'HTTP '.$response->status().': '.Str::limit($response->body(), 500);
    }
}
