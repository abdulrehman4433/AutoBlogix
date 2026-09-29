<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiProviderException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Resolves which AiProviderInterface serves a user.
 *
 * Resolution order: the user's active `ai_providers` row (real key from
 * the database) → the environment (`config('ai.provider')`). Anything
 * unusable (unknown name, empty key) safely falls back to the built-in
 * development provider, logged once per resolution.
 */
class AiProviderManager
{
    public function forUser(User $user): AiProviderInterface
    {
        $config = $user->aiProviders()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($config !== null) {
            $key = $config->api_key;

            if (! is_string($key) || $key === '') {
                throw new AiProviderException(
                    'The saved AI provider has no API key. Save it again on the AI Providers page.',
                    'ai_provider '.$config->id.' ('.$config->provider.') has an empty key',
                );
            }

            if ($config->provider === 'development') {
                return new DevelopmentAiProvider;
            }

            return new OpenAiProvider(
                baseUrl: $config->base_url ?: (string) config('ai.base_url'),
                model: $config->model ?: (string) config('ai.model'),
                apiKey: $key,
                timeout: (int) config('ai.timeout'),
            );
        }

        return $this->fromEnvironment();
    }

    /**
     * Environment fallback (AI_PROVIDER / AI_API_KEY).
     */
    private function fromEnvironment(): AiProviderInterface
    {
        $name = (string) config('ai.provider');
        $key = config('ai.api_key');

        if ($name === 'openai' && is_string($key) && $key !== '') {
            return new OpenAiProvider(
                baseUrl: (string) config('ai.base_url'),
                model: (string) config('ai.model'),
                apiKey: $key,
                timeout: (int) config('ai.timeout'),
            );
        }

        if ($name !== 'development') {
            Log::warning('AI provider configuration unusable; using the development provider.', [
                'provider' => $name,
                'has_api_key' => is_string($key) && $key !== '',
            ]);
        }

        return new DevelopmentAiProvider;
    }
}
