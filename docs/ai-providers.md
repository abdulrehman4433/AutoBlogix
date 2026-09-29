# AutoBlogix — AI Providers & Prompts

How AutoBlogix chooses an AI provider, how to configure one, and how prompt
texts are resolved and parsed.

## Provider resolution (per generation)

`AiProviderManager::forUser($user)` picks the first usable source:

1. **Active database row** — the user's `ai_providers` row with
   `is_active = 1` (created/managed on the **AI Providers** page). Exactly
   one row is active at a time; saving a new one swaps activation inside a
   transaction.
2. **Environment fallback** — `AI_PROVIDER` from `.env` (via
   `config/ai.php`):
   - `openai` — any OpenAI-compatible API using `AI_API_KEY`
     (+ `AI_MODEL`, `AI_BASE_URL`, `AI_TIMEOUT`);
   - `development` (default) — the built-in deterministic stub;
   - unknown values → development, with a `Log::warning`.
3. **Development stub** — always works, zero network, deterministic output
   (used by default and by the test suite).

All three paths implement `AiProviderInterface`:

```php
generate(string $system, string $user, array $input): array
// → content, excerpt, tags[], keywords[], meta_description, tokens_used
name(): string   model(): string
```

## Configuring a provider

**Option A — environment** (simplest, whole-app default):

```dotenv
AI_PROVIDER=openai
AI_API_KEY=sk-...
AI_MODEL=gpt-4o-mini            # default
AI_BASE_URL=https://api.openai.com/v1   # default; any compatible endpoint
AI_TIMEOUT=30                   # seconds
```

**Option B — AI Providers page** (per-user, survives env changes):

- Single provider type `openai` (OpenAI-compatible), optional `model` and
  `base_url` override; the key is stored with Laravel's `encrypted` cast and
  **never rendered back** (masked `••••1234` hint only).
- `base_url` is SSRF-checked (`UrlNormalizer::isAllowedUrl`) before saving.
- Activate/remove buttons switch between stored rows and the env fallback;
  removing the active row falls back to `AI_PROVIDER` automatically.

Errors from real providers are mapped to friendly messages (bad key, quota,
rate limit, timeout, unreachable, bad JSON) for the flash; technical detail
goes to `ai_logs.error_message` + the application log.

## Prompt templates

Two texts drive generation (system + user), with `:placeholder`
substitution (`:title`, `:topic`, `:keywords`, `:tone`, `:length_words`):

1. **Database row** — `prompt_templates` keyed `blog_post_generation`
   (seeded idempotently by `PromptTemplateSeeder` from config). DB wins.
2. **Config fallback** — `config('ai.php') → defaults.blog_post_generation`,
   so generation works even before the seeder ran.
3. **Settings → AI prompt template** — edit both texts (validated
   `required|string|max:12000`) or **Reset to default** (deletes the row,
   config takes over again). Templates are **global**: with the single-admin
   MVP they affect every account — documented on the page.

## Output contract & parsing

The user prompt demands **one JSON object** with exactly
`content`, `excerpt`, `tags`, `keywords`, `meta_description`.
`AiResponseParser` then defends against real-model drift: markdown fences,
prose around the outermost `{…}`, a `content_html` alias, type coercion
(strings ↔ arrays), list caps (5 tags / 10 keywords), excerpt derivation —
and raises a friendly `AiProviderException` when nothing parseable remains.
Sanitization (`HtmlSanitizer`) runs at ingest, so stored AI HTML can never
execute in previews.

## Generation flow & logs

`AiContentService`:

- **Compose** — creates a draft (`PostSource::Ai`) then generates.
- **Generate/Regenerate on a post** — `lockForUpdate` gate
  `{draft, generating, generated}` → `generating` + `ai_logs` row
  (`processing`) → provider call **outside** the transaction →
  success: write content/excerpt/tags/keywords/meta_description +
  `ai_provider`/`ai_model` + status `generated` + log `success`
  (tokens counted); failure: previous status restored, log `failed` with
  technical detail, friendly flash — never a 500. Interrupted `processing`
  rows are closed first (crash recovery for a post visibly stuck in
  `generating`).

Token counts, provider, and model are recorded per attempt in `ai_logs`
(see **Activity logs** and the post's **AI generation history** table).
