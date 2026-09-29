<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiLogStatus;
use App\Models\AiLog;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiLog>
 */
class AiLogFactory extends Factory
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
            'post_id' => BlogPost::factory(),
            'prompt_key' => 'blog_post_generation',
            'provider' => 'development',
            'model' => null,
            'status' => AiLogStatus::Processing,
            'tokens_used' => null,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate a completed attempt.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiLogStatus::Success,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate a failed attempt.
     */
    public function failed(string $error = 'Connection timed out.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiLogStatus::Failed,
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
