<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'openai',
            'api_key' => 'sk-test-'.Str::lower(Str::random(24)),
            'model' => 'gpt-4o-mini',
            'base_url' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate a stored but inactive provider configuration.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
