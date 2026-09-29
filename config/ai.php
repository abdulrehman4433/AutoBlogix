<?php

return [
    /*
     * Environment fallback used when the user has no active AI provider
     * row: `development` (built-in stub) or `openai` (OpenAI-compatible
     * API using AI_API_KEY). Unknown values safely fall back to development.
     */
    'provider' => env('AI_PROVIDER', 'development'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
    'timeout' => (int) env('AI_TIMEOUT', 30),

    /*
     * Built-in prompt texts. The PromptTemplateSeeder copies these into
     * prompt_templates (DB rows win at runtime; config is the fallback).
     * Keys are template keys; variables use :placeholder syntax.
     */
    'defaults' => [
        'blog_post_generation' => [
            'name' => 'Blog post generation',
            'system' => 'You are AutoBlogix, an expert blog writer. You write clear, '
                .'engaging, well-structured HTML blog posts that read naturally and '
                ."respect the reader's time. You always respond with exactly one valid "
                .'JSON object and nothing else — no markdown fences, no commentary '
                .'before or after it.',
            'user' => 'Write a blog post for our website.
Title: :title
Topic: :topic
Target keywords (weave in naturally where they fit): :keywords
Tone: :tone
Target length: approximately :length_words words of body text.

Respond with a single JSON object using exactly these keys:
"content" — the post body as HTML (<p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em> are welcome; no <html>, <head> or <body> tags),
"excerpt" — a plain-text summary of at most 200 characters,
"tags" — an array of 3 to 5 short topic tags,
"keywords" — an array of up to 10 SEO keywords actually used in the text,
"meta_description" — a plain-text search-engine description of at most 155 characters.',
        ],
    ],
];
