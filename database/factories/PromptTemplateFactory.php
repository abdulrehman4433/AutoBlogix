<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PromptTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromptTemplate>
 */
class PromptTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'template-'.Str::lower(Str::random(10)),
            'name' => ucfirst(Str::words(Str::sentence(3), 4)),
            'system_prompt' => 'You are a blog writer. Respond with a single JSON object only.',
            'user_prompt' => 'Write a blog post titled :title (tone: :tone).',
        ];
    }
}
