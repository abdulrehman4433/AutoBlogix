# AutoBlogix — Build Progress

Resumable state for this project. Each session reads this file first.

## How work proceeds (user instruction)

Module-wise DOCUMENT → BUILD loop: create `docs/modules/NN-<module>.md` first,
then implement the module completely, tick its acceptance checklist, add an
"As built" section, update this file, and make one git commit
`Phase N: <name>`. Then stop and wait for "continue".

## Environment (verified)

- PHP 8.4.22 (CLI, pdo_mysql/openssl/mbstring/pdo_sqlite), Composer 2.4.1
- Database server: **MariaDB 12.3.2** at `C:\zampp` (XAMPP), root, no password.
  Databases: `auto_blogix`, `auto_blogix_testing` (both utf8mb4_unicode_ci).
  - Assumption: local dev runs MariaDB (MySQL-compatible); production targets MySQL 8.
    Avoid MySQL-8-only SQL; Laravel query builder handles portability.
- Laravel **13** (`laravel/framework ^13.17`), Breeze 2.4 (Blade), Laravel Boost 2.10.
- Frontend: Tailwind CSS 4 + `@tailwindcss/vite`, Vite 8 (rolldown), Alpine.js 3.
  - **Node**: host machine has Node 20.11 (< Vite 8 floor 20.19). Portable
    Node **22.23.3** installed at `C:\Users\Abdul Rehman\.local\node22\node-v22.23.3-win-x64`
    — prefix shell commands with
    `$env:PATH = "C:\Users\Abdul Rehman\.local\node22\node-v22.23.3-win-x64;$env:PATH"`
    before `npm install` / `npm run build`. README must document Node ≥20.19.
  - npm bug workaround already applied: `@rolldown/binding-win32-x64-msvc@1.2.11`
    installed with `--no-save` (if node_modules is wiped, re-run that after npm i).
- Test runner: **PHPUnit 12** (tests use sqlite in-memory via phpunit.xml).
- Git repo initialized (first commit = Phase 1).
- Dev server may be running: `php artisan serve` on 127.0.0.1:8000.

## Phases / modules

| # | Module | Doc | Status |
|---|--------|-----|--------|
| 1 | Project audit + auth + dashboard shell + seeding | docs/modules/01-auth-dashboard.md | ✅ complete (commit: Phase 1) |
| 2 | Websites + WebsiteCredentialService + policies + connection logs | docs/modules/02-websites.md | ✅ complete (commit: Phase 2) |
| 3 | WordPress API (HMAC middleware, endpoints, rate limits, nonce store) + docs/wordpress-api.md | docs/modules/03-wordpress-api.md | ✅ complete (commit: Phase 3) |
| 4 | Blog posts module (CRUD, filters, preview, statuses) | docs/modules/04-blog-posts.md | ✅ complete (commit: Phase 4) |
| 5 | WordPress publishing (service, job, idempotency, logs, retry) | docs/modules/05-publishing.md | ✅ complete (commit: Phase 5) |
| 6 | AI layer (interface, manager, providers, prompts + seeder, UI, logs) | docs/modules/06-ai.md | ✅ complete (commit: Phase 6) |
| 7 | Schedules + scheduler + queue jobs + timezone logic | docs/modules/07-schedules.md | pending |
| 8 | Settings, logs UI, polish, final docs (README, process.md, architecture, ai-providers, scheduling), full test run | docs/modules/08-settings-logs.md | pending |

Old Phase 0 (setup) is folded into Phase 1's audit; its doc is `docs/setup.md`.

## What exists now (after Phase 6)

### Phase 1–2 (unchanged)

- Foundation tables: `websites`, `blog_posts`, `connection_logs`, `publishing_logs`,
  plus users/jobs/cache/sessions.
- Enums (backed, string): `App\Enums\{WebsiteStatus, PostStatus, PostSource,
  PublishingLogStatus, ConnectionLogStatus}` — includes `PostStatus::publishable()`
  (scheduled/failed only, per idempotency rule).
- Models: User (+websites, blogPosts relations), Website, BlogPost,
  ConnectionLog, PublishingLog. Credentials (`api_key`, `encrypted_api_secret`)
  are intentionally NOT mass assignable.
- Factories: WebsiteFactory (`withCredentials`, `connected`),
  BlogPostFactory (`scheduled($at)`, `published`), UserFactory (Breeze).
- Services: `DashboardService::metricsFor(User)`; **WebsiteService**
  (create/update/delete, returns one-time secret); **WebsiteCredentialService**
  (generateApiKey/ApiSecret, issue/rotate/revoke/verify/findWebsiteByKey).
- Support/Rules: **UrlNormalizer** (normalize + isPrivateHost + isAllowedUrl —
  SSRF rules in ONE place); **ValidWebsiteUrl** rule.
- Policies: `WebsitePolicy` (owner-only; auto-discovered).
- Controllers: Dashboard, Websites (resource), WebsiteCredential (rotate/revoke).
- Owner-scoped `Route::bind('website')` in AppServiceProvider → cross-user IDs
  return **404** (never 403). Use the same pattern for future {schedule} etc.
- Views: `layouts/{app,guest,navigation}`, `dashboard/index`,
  `websites/{index,create,edit,show}`, components: alert, button, badge, input,
  textarea, select, table, modal (+ Breeze extras).
- Routes: `/` → redirect `/dashboard`; `dashboard`; `websites.*` resource +
  `websites.credentials.rotate|revoke` (create/store registered BEFORE resource!);
  Breeze auth + profile. Navigation guards links with `Route::has()`.
- Seeders: UserSeeder (admin@autoblogix.test / password, updateOrCreate,
  production guard via `config('app.seed_admin_password')`). No demo websites.

### Phase 3 — WordPress API (inbound, HMAC-signed)

- `routes/api.php` enabled via `withRouting(api:)` (prefix `/api`, group
  `api`, JSON always). Five POST routes `/api/v1/wordpress/{connect, verify,
  disconnect, heartbeat, publish-result}`, each `throttle:<named>` FIRST then
  `VerifyWordPressSignature`.
- Signature: `hex(hmac_sha256(secret, "METHOD\nPATH\nTIMESTAMP\nNONCE\nsha256hex(body)"))`,
  headers `X-ABX-Key/Timestamp/Nonce/Signature`; ±300 s window; nonce
  single-use via atomic `Cache::add` (600 s TTL), consumed only after a valid
  signature; `hash_equals`; dummy HMAC for unknown keys.
- Services: **HmacSigner** (canonical payload/sign/verify — shared with
  Phase 5 outbound), **WordPressAuthenticationService** (throws
  `App\Exceptions\WordPressAuthenticationException` with stable codes:
  missing_headers, invalid_nonce, unknown_key, stale_timestamp,
  invalid_signature, replayed_nonce — all 401), **WordPressConnectionService**
  (connect/verify/disconnect/heartbeat state machine + connection_logs;
  heartbeat logs only on status change), **PublishingService::recordPluginResult()**
  (lockForUpdate + already_recorded no-op + closes publishing_logs once —
  Phase 5 extends it with outbound publishing).
- Rate limiters (AppServiceProvider, each per key AND per IP): connect
  10/10 · verify 30/30 · wordpress-api (disconnect/heartbeat) 60/120 ·
  publish-result 60/60 per minute.
- Form Requests: `WordPressApiRequest` base (422 → `error: validation_failed`),
  Connect/Heartbeat (version pattern `^[A-Za-z0-9._-]+$`, max 32),
  PublishResult (conditional fields from boolean `success`).
- `docs/wordpress-api.md`: full plugin contract with computed worked example,
  error-code + rate-limit tables, retry rules, sign-request.php script.
- Statuses: pending/disconnected/error → connected (connect/heartbeat),
  → disconnected (disconnect); `error` reserved for Phase 5 outbound test
  failures. Verified live: connect 200, bad signature 401, stale 401,
  verify 200, DB rows correct.
- Phase 3 verified: **112 tests / 389 assertions**, Pint clean, live HTTP
  smoke (temporary website created, exercised, deleted).

### Phase 4 — Blog posts (CRUD, filters, preview, statuses)

- Routes: `posts.*` resource with `/posts/create` + POST `/posts` registered
  BEFORE the `{post}` wildcard; owner-scoped `Route::bind('post')` (foreign
  IDs → **404**) + `BlogPostPolicy` (view/update/delete = owner) as second
  layer — same pattern as websites.
- `PostsController` (thin) + **BlogPostService** (create/update/delete,
  slug generated once, unique per website via suffix loop, never regenerated
  on edit; status rule: only draft ↔ scheduled ever change on save —
  publishing/AI statuses are owned by Phases 5/6 and survive edits).
- `StorePostRequest` shared for store+update: `website_id` must exist **in
  the user's own websites**; tags/keywords comma-list → array (max 20/10,
  50 chars each); `scheduled_at` must be in the future when interpreted in
  the website's timezone, but a value equal to the stored one always passes
  (so editing an already-scheduled-then-missed post isn't stuck). Raw local
  input is kept for `old()`; UTC conversion happens in
  `BlogPostService::scheduleToUtc()` after validation.
- `BlogPost` model: `scheduledAtSiteTime()` / `publishedAtSiteTime()` —
  input entered in website tz, stored UTC, displayed back in website tz.
- **HtmlSanitizer** (`app/Support/`): allowlist `strip_tags` + `on*`
  attribute removal + `javascript:`/`vbscript:`/`data:text/html` scrub —
  show-page preview renders through it (AI/user HTML can never execute).
  Flagged: swap in a purifier package if rich-HTML needs grow.
- Views: `posts/{index,create,edit,show}` + shared `posts/partials/form`.
  Index: filter bar (q/status/website → `withQueryString`, 15/page; invalid
  filter values ignored, never 422), status/source badges, times in website
  tz, delete confirm modal, empty states (no posts / no website → CTA).
  Create page without websites → "Add a website first" alert. Show:
  sanitized preview + meta sidebar + "Publishing history" table (empty until
  Phase 5). `scheduled_at` field disabled unless status ∈ {draft, scheduled}.
- Phase 4 verified: **128 tests / 484 assertions** (16 new in PostsTest),
  Pint clean, npm build ok, live HTTP smoke **22/22** (incl. DB check
  `09:30 America/New_York` → `2030-06-15 13:30:00` UTC); DB restored to
  seeded state (0 rows in websites/blog_posts/*_logs).

### Phase 5 — WordPress publishing (outbound, job, idempotency, retry)

- Route `POST /posts/{post}/publish` → `posts.publish` (auth+verified,
  `BlogPostPolicy::publish` = owner, owner-scoped bind → 404). One endpoint
  serves **Publish now** (draft/scheduled) and **Retry publish** (failed);
  publishing posts show a disabled "Publishing…" button, published posts no
  button (index has no publish action — show page owns it).
- `PublishingService` (three entry points, one shared finalizer):
  - `requestPublish()` — idempotent entry (spec): transaction +
    `lockForUpdate`; draft first → `scheduled` (`scheduled_at ??= now`) on
    explicit click, then only `PostStatus::publishable()` may enter
    `publishing`; returns `queued|already_publishing|published|not_publishable`
    (never throws); opens a **pending** `publishing_logs` row (attempt =
    running count +1); dispatches `PublishPostJob` after the transaction.
  - `executeAttempt()` (job, `tries = 1`) — `Cache::lock` per post
    (non-blocking), re-checks `status === publishing` under row lock, flips
    log → processing, HTTP **outside** transactions, then finalizes; never
    throws (failures are data); deleted post mid-flight → no-op.
  - `recordPluginResult()` (Phase 3) + sync response both go through
    `applyOutcome()`/`closePublishingLog()` → final state wins, exactly one
    terminal log row per attempt; `already_recorded` semantics unchanged.
- `WordPressPublishingClient` — signs with `HmacSigner` over the **exact**
  bytes (`json_encode` once → `withBody`, never re-encoded); path includes
  the site's subdirectory (`/blog/wp-json/autoblogix/v1/publish`); timeout
  `config('wordpress.timeout')` ← new `config/wordpress.php`
  (`WORDPRESS_API_TIMEOUT` was a dead env var until now); friendly error map
  (unreachable / 401-403 credentials / 404-405 plugin missing / 429 / 5xx /
  bad JSON / plugin `message`-`code`); technical detail (status + body
  snippet) → `publishing_logs.error_message` + `Log::warning` — never the
  secret/signature.
- `publish_idempotency_key` (uuid): generated on **first** attempt, stable
  across retries (refines the Phase 1 "per attempt" wording — a retry after
  a lost response must not duplicate the post on WordPress; documented in
  docs/wordpress-api.md §7).
- `docs/wordpress-api.md` **§7 added**: outbound publish endpoint contract
  (payload, response shapes, idempotent dedupe requirement, plugin
  verification against `REQUEST_URI`, how AutoBlogix interprets replies).
- Phase 5 verified: **PublishingTest 14 tests / 109 assertions**, full
  suite **142 tests / 593 assertions** (Phase 3 API tests green after the
  refactor), Pint clean, `npm run build` ok, live HTTP smoke **27/27**
  (real queue worker + real NXDOMAIN → friendly `Could not reach
  smoke-test.example.com…`, retry → attempts `1,2` with the same key,
  technical `ConnectionException` only in the log); DB restored to seeded
  state (0,0,0,0,0).

### Phase 6 — AI layer (providers, prompts, generation, logs)

- Routes (auth+verified): `ai.generate` (GET `/ai-content` composer),
  `ai.store`, `posts.generate` (POST `/posts/{post}/generate`),
  `ai.providers` + `ai.providers.store|activate|destroy`. `{provider}` uses the
  owner-scoped `Route::bind` (foreign → 404); no policy needed — the row is
  always resolved through the session user's relation.
- Tables: `ai_providers` (provider, **api_key `encrypted` cast** (never mass
  assigned), model, base_url SSRF-checked via `UrlNormalizer::isAllowedUrl`,
  `is_active` — exactly one active row per user, swapped in a transaction);
  `prompt_templates` (global unique `key`, seeded idempotently from
  `config('ai.defaults.*')` by `PromptTemplateSeeder`, DB row wins, config =
  fallback so generation works pre-seeder); `ai_logs` (per attempt:
  processing → success/failed, provider/model/tokens_used/error_message/
  started_at/completed_at). New enum `AiLogStatus` (+`label()`/`badge()`).
- Services (flat in `app/Services/`): `AiProviderInterface` —
  `generate(string $system, string $user, array $input)` returns
  content/excerpt/tags/keywords/meta_description/tokens_used, plus `name()`
  and `model()`; `DevelopmentAiProvider` (deterministic, offline, default);
  `OpenAiProvider` (bearer auth, `config/ai.php` timeout ← **AI_TIMEOUT=30**
  newly in `.env.example`, HTTP status → friendly message map, response parsed
  by `AiResponseParser`); `AiProviderManager::forUser()` — active DB row →
  env `AI_PROVIDER` → development fallback (+`Log::warning` for unusable
  config); `AiResponseParser` (defensive: markdown fences, prose around the
  outermost `{…}`, `content_html` alias, type coercion, ≤5/≤10 list caps,
  excerpt derivation, friendly exception on anything unparseable);
  `AiContentService` — `compose()` (create draft via
  `BlogPostService::create(..., PostSource::Ai)` then generate) and
  `generateForPost()` (lockForUpdate status gate {draft, generating,
  generated} → `generating` + open ai_log → provider call OUTSIDE the
  transaction → success: write 5 columns + `HtmlSanitizer::sanitize` at ingest
  + `ai_provider`/`ai_model` + status `generated` + log success; failure:
  restore previous status + close log `failed` with technical detail +
  friendly flash — never a 500; closes interrupted `processing` rows first,
  which is the crash-recovery path for a visible `generating` state).
- `config/ai.php`: provider/api_key/model/base_url/timeout + default prompt
  texts with `:title :topic :keywords :tone :length_words` placeholders.
- UI: `ai-content/index` (composer; "Add a website first" CTA),
  `ai-providers/index` (env-fallback vs active alert, table with masked
  `••••1234` hint — full key never rendered —, activate/remove, add form),
  posts.show: **Generate content** (empty draft) / **Regenerate with AI**
  (confirm modal, draft+generated) / disabled **Generating…** + new **AI
  generation history** table; publish button and edit-form schedule field now
  include `generated`.
- Bridge edits into Phase 4/5 code: `PublishingService::requestPublish()`
  accepts `generated` (→ scheduled → publishing, same idempotent entry);
  `BlogPostService::create()` gained `PostSource $source = PostSource::Manual`
  and `update()` flips `generated + schedule → scheduled`; new
  `BlogPostPolicy::generate`; `User::aiProviders()/aiLogs()`,
  `BlogPost::aiLogs()`, show controller passes `aiLogs`.
- Verified: **AiTest 18/148**, **AiResponseParserTest 7/24**, full suite
  **167 tests / 765 assertions OK**, Pint clean, `npm run build` ok, live
  HTTP smoke **28/28** (composer → Generated → regenerate → 2 log rows →
  provider store/encrypted/masked/delete → env fallback), DB restored to
  seeded state (0,0,0,0).

## Decisions made

- Latest stable Laravel = 13; Breeze Blade for auth; PHPUnit (not Pest).
- Tailwind 4 kept instead of Breeze's Tailwind 3 downgrade (same utility classes;
  avoids conflict with the skeleton's `@tailwindcss/vite`).
- Statuses = PHP backed enums castable from string columns (flagged improvement:
  prevents invalid states at the DB boundary).
- `SEED_ADMIN_PASSWORD` read via config(), not env() (works with config:cache).
- Foundation migrations created in Phase 1 because the real-metrics dashboard
  needs those tables; phases 2–7 focus on feature code.
- Cascade deletes: user → websites → posts/logs (MVP integrity rule).
- `publish_idempotency_key`: uuid column, unique, nullable — generated on
  the first publish attempt and kept stable across retries (Phase 5 refinement).
- Owner-scoped `Route::bind()` → cross-user 404, policies as second layer.
- Laravel 13 CSRF middleware class is `PreventRequestForgery`
  (`ValidateCsrfToken` is a deprecated subclass); test-mode CSRF bypass is
  disabled when a test switches `app['env']` → use `withoutMiddleware` there.
- Website host charset limited to `[a-z0-9._-]` (IDN must be punycode).
- Route registration order: explicit `/websites/create` + POST before the
  resource's `{website}` wildcard; same for `/posts/create` (Phase 4).

## Assumptions (one-liners)

- Local MySQL-compatible server is MariaDB 12 (see above).
- APP_URL `http://localhost:8000` via `php artisan serve`.
- Website URL is normalized by `UrlNormalizer` to scheme+host+path with no
  trailing slash (subdirectory installs supported); unique per (user, url).

## Open issues / TODO

- [x] Phase 3 done: doc, 5 signed endpoints, rate limiters, docs/wordpress-api.md,
  35 new tests, live smoke. connection_logs actions are
  connect|verify|disconnect|heartbeat|authenticate (publish outcomes live in
  publishing_logs, not connection_logs).
- [x] Phase 4 done: doc, posts CRUD/filters/sanitized preview/status
  transitions, 16 new tests, live smoke 22/22, DB clean. **Phase 5 handoff:**
  `posts.show` already renders "Publishing history" (publishing_logs, empty)
  and the PostStatus badge — add "Publish now"/retry actions there and on
  index; extend `PublishingService` (has `recordPluginResult()` from
  Phase 3) with outbound publishing that signs requests via `HmacSigner`
  per `docs/wordpress-api.md`; generate `publish_idempotency_key` per
  attempt; `PostStatus::publishable()` (scheduled/failed) gates the
  transition into `publishing`; failed posts show `failure_reason` alert on
  show (already styled).
- [x] Phase 5 done: doc, `posts.publish` route + policy, `requestPublish()`
  idempotent entry + `PublishPostJob` + `WordPressPublishingClient`,
  `config/wordpress.php`, show-page publish/retry/publishing states,
  docs/wordpress-api.md §7, 14 new tests (suite 142/593), live smoke 27/27,
  DB clean. **Phase 6 handoff:** AI layer touches statuses
  generating/generated (owned by `BlogPostService` save rule — do not let
  edits clobber them); publishing already sends `content`/`excerpt`/`tags`/
  `keywords`/`meta_description` to the plugin — AI output only needs to
  write those columns. **Phase 7 handoff:** scheduler calls
  `PublishingService::requestPublish($post)` at `scheduled_at` (all
  idempotency rules already enforced there); add a sweep recovering posts
  stuck in `publishing` (pending/processing log older than
  `WORDPRESS_API_TIMEOUT`, status still publishing) → mark failed; a
  `queue:work` runner must exist for jobs to finish (UI shows a disabled
  "Publishing…" until then).
- [x] Phase 6 done: doc, AI layer (interface/manager/providers/parser/service,
  prompts + idempotent seeder, composer + providers UI + show actions + AI
  history), 25 new tests (suite 167/765), live smoke 28/28, DB clean.
  Resolved earlier TODO: `User::aiProviders()` / `User::aiLogs()` added;
  `prompt_templates` are GLOBAL (no `User::promptTemplates()` — dropped,
  flagged for a Phase 8 prompt editor in Settings).
  **Phase 7 handoff:** scheduler calls
  `PublishingService::requestPublish($post)` at `scheduled_at` (works for
  `scheduled`; `generated` posts reach `scheduled` via edit-schedule or
  Publish now — both already implemented); add the sweep for posts stuck in
  `publishing` (pending/processing log older than `WORDPRESS_API_TIMEOUT`,
  status still publishing → mark failed with friendly reason); a
  `queue:work` runner must exist for jobs to finish (show page renders a
  disabled "Publishing…" until then); keep AI statuses (`generating`,
  `generated`) out of the scheduler's path — only `scheduled` fires.
- [ ] README does not exist yet as AutoBlogix README (Phase 8 rewrites it).
- [ ] Remember Node 22 portable PATH prefix for npm commands.
