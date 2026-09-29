# Module 05 — WordPress Publishing (outbound, job, idempotency, logs, retry)

Status: complete (Phase 5)

## 1. Purpose and place in the process

Turns a stored post into a live WordPress post. The user clicks **Publish now**
(or **Retry** after a failure) on the post page; the app signs an outbound
request to the plugin's publish endpoint, records the outcome exactly once in
`blog_posts` + `publishing_logs`, and surfaces friendly errors.

Phase boundaries:

- **This phase** owns `publishing → published/failed`, the job, the idempotent
  entry rules, attempt logs, and manual retry.
- **Phase 7's** scheduler calls the same `PublishingService::requestPublish()`
  when a post's `scheduled_at` arrives — no new publishing code there.
- The **inbound** `publish-result` callback (Phase 3) stays valid: a plugin
  that re-reports an outcome lands in the same finalization path
  (`already_recorded` semantics unchanged).

## 2. Tables / columns touched

- `blog_posts`: status becomes `publishing` (in flight), then `published`
  (`wordpress_post_id`, `wordpress_url`, `published_at`, `failure_reason`
  cleared) or `failed` (`failure_reason` = friendly message).
  `publish_idempotency_key` (uuid, unique, nullable) is generated on the
  **first** attempt and kept stable across retries — a retry after a lost
  response must not create a second post on WordPress.
- `publishing_logs`: one row per attempt, lifecycle
  `pending → processing → success | failed`; `attempt` = running count + 1;
  `started_at` set when the attempt is requested, `completed_at` when it is
  closed; `http_status` + `response_summary` (success) or `error_message`
  (failure) filled from the HTTP exchange.

## 3. Routes (web, inside `auth` + `verified`)

| Method | URI | Name | Handler |
| --- | --- | --- | --- |
| POST | `/posts/{post}/publish` | `posts.publish` | PostPublishController@store |

No input payload — the post is identified by the owner-scoped route bind.
Registered after the resource (URI is more specific, no wildcard conflict).

## 4. Files to create / change

Create:

- `docs/modules/05-publishing.md` (this file)
- `config/wordpress.php` — `timeout` (from `WORDPRESS_API_TIMEOUT`, default
  30) and `publish_path`; the env var exists in `.env.example` since Phase 1
  but was never read — this config makes it real
- `app/Services/WordPressPublishingClient.php` — outbound signed HTTP +
  response parsing + friendly error mapping
- `app/Jobs/PublishPostJob.php` — queued, `tries = 1`
- `app/Http/Controllers/PostPublishController.php`
- `tests/Feature/PublishingTest.php`
- §2 addition in `docs/wordpress-api.md`: the outbound `publish` endpoint
  contract (path, payload, response, plugin verification rules)

Change:

- `app/Services/PublishingService.php` — add `requestPublish()` (idempotent
  entry) + `executeAttempt()` (lock → HTTP → finalize) and refactor the
  shared finalize/close logic so the sync response and the plugin callback
  apply identical outcome rules
- `app/Policies/BlogPostPolicy.php` — `publish` (owner-only)
- `routes/web.php`
- `resources/views/posts/show.blade.php` — publish/retry/publishing actions
- `PROGRESS.md`

## 5. Business rules / edge cases

- **Idempotent entry** (spec): `DB::transaction` + `lockForUpdate`. A `draft`
  first moves to `scheduled` (`scheduled_at ??= now`) because the user asked
  for immediate publishing; only `PostStatus::publishable()` states
  (scheduled/failed — evaluated after that transition) may enter
  `publishing`. Anything else returns a result string, never an exception:
  `queued` · `already_publishing` · `published` · `not_publishable`.
- **Concurrency:** `Cache::lock("autoblogix:publish:{post}")` around the HTTP
  attempt (non-blocking — if another attempt holds it, this one exits
  quietly); the job re-checks `status === publishing` under `lockForUpdate`
  before doing any work.
- **Job:** `tries = 1`; failure is recorded as data (status + log), never
  thrown — the sync test driver and the database driver behave identically.
  A post deleted while the job is in flight → graceful no-op (rows cascade).
- **Client:** body is JSON-encoded **once**, signed over those exact bytes
  and sent with `withBody()` (never re-encoded); signing path = full request
  path including any subdirectory of the site URL; timeout from
  `config('wordpress.timeout')`; friendly messages for: unreachable host,
  401/403 (credentials), 404 (plugin endpoint missing), 429 (rate limit),
  5xx (site error), invalid JSON, and `success: false` from the plugin
  (its `message`/`code`). Technical detail (URL, status, body snippet,
  exception) goes to `Log::warning` only — never the secret or signature.
- **Connection status is not a gate:** publishing is attempted regardless of
  the website's connection state; a rejected key surfaces as the friendly
  credentials message (the handshake lives in Phase 3).
- **Callback parity:** sync response and inbound `publish-result` share one
  `applyOutcome()` — the final state always wins, double delivery is a no-op.
- Flagged improvements (post-MVP): a Phase 7 sweep recovering posts stuck in
  `publishing` after a worker crash; automatic backoff retries instead of
  manual-only retry.

## 6. UI pages and states

- **show page header actions:**
  - `draft` / `scheduled` → **Publish now** (primary submit button)
  - `failed` → **Retry publish** (the failure alert above already explains why)
  - `publishing` → disabled **Publishing…** button (queue worker finishes it)
  - `published` → no button (WordPress link + badge already shown);
    other statuses (`generating`, `generated`, `cancelled`) → no button
- Flash per outcome: started / already publishing / already published /
  cannot publish right now.
- **Publishing history** table (built in Phase 4) fills in: attempt number,
  status badge, HTTP code, started/completed times, response or error text.
- Index page has no publish action (the show page owns publishing).

## 7. Acceptance criteria

- [x] `POST /posts/{post}/publish` registered; guest → login redirect;
      another user's post → 404; policy `publish` denies non-owners
- [x] Publish now on a draft: enters `publishing` (via `scheduled`),
      job dispatched, and with the outcome faked the post ends `published`
      with `wordpress_post_id`, `wordpress_url`, `published_at`,
      `failure_reason` cleared
- [x] Outbound request carries the four `X-ABX-*` headers; signature verifies
      with `HmacSigner` + the website's stored secret over the exact sent
      bytes and path; payload contains the stable `publish_idempotency_key`
- [x] Only scheduled/failed (or draft via the explicit transition) enter
      `publishing`; a `published` post → `not_publishable` with **no** HTTP
      call; a `publishing` post → `already_publishing` with no second job/log
- [x] Each attempt writes exactly one `publishing_logs` row:
      `pending → processing → success|failed`, `attempt` increments on retry,
      `started_at`/`completed_at` and `http_status` set
- [x] Failure outcomes set `status = failed` + friendly `failure_reason`
      (no technical strings) and log the technical detail; verified for
      connection error, 404, 401, 5xx, and malformed success bodies
- [x] Retry reuses the same `publish_idempotency_key` and produces attempt #2
- [x] Show page renders Publish now / Retry / Publishing… states correctly
- [x] Plugin `publish-result` callback still records exactly once after the
      refactor (Phase 3 tests stay green)
- [x] Full suite + Pint pass; live HTTP smoke: publish → worker → friendly
      failure against a non-existent plugin endpoint → retry → attempt #2

## 8. Tests to be written (`tests/Feature/PublishingTest.php`)

- guest → redirected; foreign post → 404 on publish
- with `Queue::fake`: draft publish → `publishing` status, pending log
  (attempt 1), idempotency key generated, job pushed with the post ID
- with sync + `Http::fake` success: draft and scheduled posts end
  `published` (fields set), log `success` + `http_status` 200,
  `Http::assertSent` on URL, JSON body, and signed headers (recomputed
  signature matches)
- non-publishable states: `published` → `not_publishable`, zero HTTP calls;
  `publishing` → `already_publishing`, no extra log
- failures (sync + fake): connection exception, 404, 401, 500, 2xx-with-
  garbage → all end `failed` with friendly `failure_reason` (assert absence
  of `cURL`/`Exception`), log `failed` with technical `error_message`
- retry: second publish after failure reuses the key, log `attempt = 2`
- UI: show page button states for draft/scheduled/failed/publishing/published
- policy: `Gate::forUser(other)->allows('publish', $post)` is false

---

## As built (Phase 5)

Everything above shipped as specified. Notable implementation notes:

- **`config/wordpress.php` created** — `WORDPRESS_API_TIMEOUT` existed in
  `.env.example` since Phase 1 but nothing read it; the config now exposes
  `timeout` (30 s) and `publish_path` (`/wp-json/autoblogix/v1/publish`).
- **Idempotency key wording refined:** the Phase 1 decision said "generated
  per publish attempt"; as built, the key is generated on the **first**
  attempt and kept **stable across retries** — that is what actually lets a
  retry after a lost response dedupe on the plugin instead of creating a
  duplicate post. (Stated in §2 and verified by tests.)
- **Signing path includes the subdirectory:** `path =
  parse_url($url, PHP_URL_PATH)` so a site at `…/blog` signs
  `/blog/wp-json/autoblogix/v1/publish`; `docs/wordpress-api.md` §7 tells
  the plugin to verify against `REQUEST_URI`'s path for the same reason.
- **`Http::withBody()`** sends the one `json_encode()` that was signed —
  the body is never re-encoded, per the contract.
- **Shared finalization:** `applyOutcome()` + `closePublishingLog()` are now
  the single place both the sync outbound response and the inbound
  `publish-result` callback (Phase 3) write post status / log rows;
  `already_recorded` semantics unchanged (Phase 3 tests green).
- **Test-driver note:** phpunit runs `QUEUE_CONNECTION=sync`, so a publish
  click executes the whole attempt inline under `Http::fake`; dispatch
  happens *after* the entry transaction commits, so no `afterCommit` is
  needed and both the sync and database drivers behave identically.
- Build fixes during the phase: the default `WebsiteFactory` state has no
  credentials (client correctly refuses to sign — tests use
  `withCredentials()`), a bare class name in `assertPushed()` needs
  `::class`, and calling `Http::fake()` twice does not replace the first
  stub (use one `Http::sequence()`).

### Verification (real output)

- Unit/feature: `PublishingTest` → **OK (14 tests, 109 assertions)**;
  full suite → **OK (142 tests, 593 assertions)** — includes all Phase 1–4
  tests (Phase 3 API suite untouched after the refactor).
- `vendor/bin/pint --dirty` → fixed `PublishingTest` (imports); suite
  re-run green after.
- `npm run build` → built successfully.
- Live HTTP smoke (`phase5_smoke.ps1`, real dev server, real queue worker,
  real DNS failure) → **27 passed, 0 failed**:
  login → create website (`smoke-test.example.com`) → create draft →
  *Publish now* → `publishing` + job row → `queue:work --once` →
  `failed` with friendly `Could not reach smoke-test.example.com…`
  (technical `ConnectionException` only in `publishing_logs.error_message`,
  no `cURL`/`Exception` text on the post) → badge/alert/retry UI verified →
  retry → same `publish_idempotency_key`, attempts `1,2`, both `failed` →
  cleanup restored `0,0,0,0,0` across websites/posts/logs/jobs.

### Handoff to Phase 6/7

- **Phase 7 scheduler:** call `PublishingService::requestPublish($post)`
  when `scheduled_at` arrives — it already enforces every idempotency rule;
  also schedule a sweep recovering posts stuck in `publishing` (worker died
  mid-attempt: pending/processing log older than `WORDPRESS_API_TIMEOUT`,
  status still `publishing`) → mark `failed` so retry becomes possible.
- The show page shows a disabled *Publishing…* button until the queue worker
  finishes the job; running `php artisan queue:work` (or `schedule:run` in
  Phase 7) is what completes it.
