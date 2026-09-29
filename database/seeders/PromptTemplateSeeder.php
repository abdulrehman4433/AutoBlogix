<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

/**
 * Copies the built-in prompt texts (config/ai.defaults) into
 * prompt_templates. Idempotent: re-running updates existing rows instead
 * of duplicating them.
 */
class PromptTemplateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /** @var array<string, array{name?: string, system?: string, user?: string}> $defaults */
        $defaults = (array) config('ai.defaults', []);

        foreach ($defaults as $key => $template) {
            PromptTemplate::query()->updateOrCreate(
                ['key' => (string) $key],
                [
                    'name' => (string) ($template['name'] ?? $key),
                    'system_prompt' => (string) ($template['system'] ?? ''),
                    'user_prompt' => (string) ($template['user'] ?? ''),
                ],
            );
        }
    }
}
