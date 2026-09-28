<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebsiteStatus;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
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
            'name' => fake()->company().' site',
            'url' => 'https://'.fake()->unique()->domainName(),
            'api_key' => null,
            'encrypted_api_secret' => null,
            'status' => WebsiteStatus::Pending,
            'timezone' => 'UTC',
        ];
    }

    /**
     * Indicate that the website has credentials generated but is not connected yet.
     */
    public function withCredentials(): static
    {
        return $this->state(fn (array $attributes) => [
            'api_key' => 'abx_'.Str::lower(Str::random(32)),
            'encrypted_api_secret' => 'abxs_'.Str::random(40),
        ]);
    }

    /**
     * Indicate that the website is connected.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebsiteStatus::Connected,
            'last_connected_at' => now(),
            'wordpress_version' => '6.7',
            'plugin_version' => '1.0.0',
        ]);
    }
}
