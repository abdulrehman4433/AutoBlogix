<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostSource;
use App\Enums\PostStatus;
use App\Models\BlogPost;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'user_id' => User::factory(),
            'website_id' => Website::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'topic' => fake()->words(3, true),
            'excerpt' => fake()->paragraph(),
            'content' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'status' => PostStatus::Draft,
            'source' => PostSource::Manual,
        ];
    }

    /**
     * Indicate that the post is scheduled for future publishing.
     *
     * @param  \DateTimeInterface|null  $at
     */
    public function scheduled($at = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $at ?? now()->addDay(),
        ]);
    }

    /**
     * Indicate that the post was published to WordPress.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Published,
            'published_at' => now()->subHour(),
            'wordpress_post_id' => fake()->numberBetween(1, 100000),
            'wordpress_url' => 'https://example.com/'.fake()->slug(),
        ]);
    }
}
