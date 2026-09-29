# AutoBlogix — Architecture

## Stack

Laravel 13 · PHP 8.4 · Blade + Tailwind CSS 4 + Alpine.js (server-rendered,
no SPA) · database queue/cache/sessions · PHPUnit · MySQL 8 (MariaDB 12 in
local development, sqlite in-memory in tests).

## Layers

```
HTTP request
  └─ routes/web.php  (auth + verified)   routes/api.php (HMAC + throttle)
       └─ Controller            thin: validate → call service → redirect/render
            ├─ Form Request     write-input validation (422 → field errors)
            ├─ Policy           ownership second layer (Gate::authorize)
            └─ Service          business rules, transactions, state machines
                 └─ Model       casts, relationships, enum statuses

Queue:   controller/service → dispatch(queued job) → queue:work → service
Cron:    schedule:run → posts:run-scheduler (due + sweep) + queue:work --stop-when-empty
```

Rules of thumb: no business logic in controllers or Blade; no repositories;
services are flat classes in `app/Services/` injected via constructor;
`declare(strict_types=1)` + PSR-12 everywhere (Pint enforces).

## Directory map

```
app/
  Console/Commands/     posts:run-scheduler
  Http/
    Controllers/        resource + feature controllers (thin)
    Requests/           Form Requests for every write endpoint
  Enums/                WebsiteStatus, PostStatus, PostSource,
                        PublishingLogStatus, ConnectionLogStatus, AiLogStatus
  Jobs/                 PublishPostJob (tries = 1; failures are data)
  Models/               User, Website, BlogPost, ConnectionLog,
                        PublishingLog, AiProvider, AiLog, PromptTemplate
  Policies/             WebsitePolicy, BlogPostPolicy
  Services/             WebsiteService, WebsiteCredentialService,
                        BlogPostService, PublishingService (+ sweep),
                        ScheduledPublishingService, AiContentService,
                        AiProviderManager, AiResponseParser,
                        WordPressPublishingClient, HmacSigner, UrlNormalizer,
                        HtmlSanitizer, DashboardService (+ providers)
  Support/              HtmlSanitizer, UrlNormalizer, rules
routes/  web.php (UI) · api.php (plugin, signed) · console.php (schedule)
resources/views/        layouts/ components/ + one folder per module
database/               migrations, factories, seeders
docs/                   setup, process, architecture, ai-providers,
                        scheduling, wordpress-api, modules/01…08
tests/Feature/          one test class per module (UiTest-style suites)
```

## Post status state machine

```
draft ──save(scheduled_at)──▶ scheduled ──scheduler/requestPublish──▶ publishing ──▶ published
  │                              ▲     │                                  │
  │                              │     └──────────(failure)────────────▶ failed ──retry──┐
  ├──generate──▶ generating ──▶ generated ──(save with schedule / Publish now)──▶ scheduled│
  │                  │                                                            │
  │                  └──(error: restore previous status, ai_log failed)◀─────────┘
  └── cancelled (reserved)
```

- **Save rule** (`BlogPostService`): only draft ↔ scheduled changes on edit;
  publishing/AI statuses survive edits.
- **Idempotent publish entry** (`PublishingService::requestPublish`):
  transaction + `lockForUpdate`; only `PostStatus::publishable()`
  (scheduled/failed — drafts/`generated` first pass through scheduled) may
  enter `publishing`; returns `queued | already_publishing | published |
  not_publishable`, never throws. A **pending** `publishing_logs` row opens
  with the request.
- **Attempt** (`PublishPostJob` → `executeAttempt`): `Cache::lock` per post,
  re-check status under row lock, log → `processing`, HTTP outside
  transactions, then the shared `applyOutcome()` finalizer:
  `published` (+ fields, clear reason) or `failed` (friendly
  `failure_reason`), closing the log exactly once
  (`pending → processing → success | failed`).
- **Sweep** (`sweepTimedOutPublishing`, every scheduler run): posts still
  `publishing` whose open log `started_at` (fallback: post `updated_at`) is
  older than `WORDPRESS_API_TIMEOUT + 60 s` are marked `failed` with a
  friendly timeout reason; technical detail lands in the log.
- **Inbound callback parity:** the plugin's signed `publish-result` and the
  sync response share `applyOutcome()` — the final state wins, double
  delivery is a no-op (`already_recorded`).

Other lifecycles: `connection_logs` actions `connect | verify | disconnect |
heartbeat | authenticate` (website status pending/disconnected/connected;
`error` reserved); `ai_logs` `processing → success | failed` with technical
`error_message` (the post's status is restored on failure — crash recovery
closes interrupted rows).

## Data model (core)

| Table | Key columns |
| --- | --- |
| `websites` | user_id, name, url (unique per user), timezone, status, api_key, encrypted_api_secret, last_sync_at |
| `blog_posts` | user_id, website_id, title, slug (unique per website), status, source, content/excerpt/tags/keywords/meta_description, scheduled_at (UTC), published_at, publish_idempotency_key (uuid, unique), failure_reason |
| `connection_logs` | website_id, action, status, message, ip_address |
| `publishing_logs` | post_id, website_id, attempt, status, http_status, response_summary, error_message, started_at, completed_at |
| `ai_providers` | user_id, provider, api_key (encrypted cast, never mass-assigned), model, base_url (SSRF-checked), is_active (one per user) |
| `prompt_templates` | key (global unique), name, system_prompt, user_prompt |
| `ai_logs` | user_id, post_id, prompt_key, provider, model, status, tokens_used, error_message, started_at, completed_at |

Deletion cascades: user → websites → posts → their logs (MVP integrity
rule). Credentials and API keys are intentionally not mass-assignable.

## Security model

- **Sessions:** Breeze auth + `verified` middleware on every UI route.
- **Tenancy:** owner-scoped `Route::bind()` for `{website}`, `{post}`,
  `{provider}` — another user's ID resolves to **404** — with policies
  (`WebsitePolicy`, `BlogPostPolicy`) as defense in depth.
- **Plugin API (`/api/v1/wordpress/*`):** signature
  `hex(hmac_sha256(secret, "METHOD\nPATH\nTIMESTAMP\nNONCE\nsha256hex(body)"))`
  over the exact body bytes; ±300 s window; nonces single-use via atomic
  `Cache::add`; `hash_equals`; dummy HMAC for unknown keys; named rate
  limiters per key **and** per IP; secrets/signatures never logged.
- **Outbound publishing:** same signer over the exact JSON bytes sent
  (`json_encode` once → `withBody`, never re-encoded).
- **Input/output:** Form Requests everywhere; HTML sanitized through the
  `HtmlSanitizer` allowlist before preview; URLs SSRF-checked by
  `UrlNormalizer` (private hosts rejected outside local/testing env);
  AI/WordPress secrets stored encrypted; friendly vs technical message
  split enforced (tests assert no `cURL`/`Exception` strings in UI fields).

## Queue & scheduler

One cron line (`php artisan schedule:run` every minute, or
`schedule:work` in development) drives the whole publish loop:

1. `posts:run-scheduler` — publishes due `scheduled` posts via
   `requestPublish()` and runs the stuck-attempt sweep;
2. `queue:work --stop-when-empty` — executes the dispatched
   `PublishPostJob`s.

See [`docs/scheduling.md`](scheduling.md) for setup and troubleshooting.

## Testing strategy

- **Feature tests per module** (`tests/Feature/*Test.php`) against sqlite
  in-memory, sync queue, array cache: happy paths, authorization
  (guest redirects, cross-user 404s), state machines, validation, and
  UI rendering.
- **No network:** `Http::fake()`/`Http::sequence()` for provider and
  WordPress calls; `Queue::fake()` when job dispatch itself is the claim.
- **Live smoke scripts** (PowerShell, temp dir) drive the real dev server
  with real scheduler/worker runs at the end of every phase, then restore
  the seeded database state.
- Style: `vendor/bin/pint --dirty --format agent` after PHP edits;
  `npm run build` after view changes.
