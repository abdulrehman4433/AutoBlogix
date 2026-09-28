# Module 03 — WordPress API (HMAC-signed endpoints)

Status: complete

## 1. Purpose and place in the process

Covers **complete-process step 5** (plugin calls
`POST /api/v1/wordpress/connect` with an HMAC-signed request; AutoBlogix
verifies it and marks the website "connected"; heartbeats keep status and
versions fresh) and the **`publish-result` callback** of step 11 (plugin
reports back the publish outcome). Provides the shared HMAC machinery that
Phase 5's outbound requests (publish, test connection) will reuse so both
directions sign identically.

Deliverable of this phase also includes **`docs/wordpress-api.md`** — the
plugin-facing contract complete enough for another developer to build the
WordPress plugin independently.

## 2. Tables / columns / relationships touched

- `websites`: reads `api_key`, `encrypted_api_secret` (auth); writes `status`
  (pending/disconnected/error → connected on connect/heartbeat; → disconnected
  on disconnect), `wordpress_version`, `plugin_version` (connect/heartbeat),
  `last_connected_at` (connect, reconnecting heartbeat), `last_sync_at`
  (connect/heartbeat).
- `connection_logs`: writes `action` ∈ {connect, verify, disconnect, heartbeat,
  authenticate} with `status` success/failed, `message` (friendly, no
  secrets), `ip_address`. Publish outcomes are recorded in `publishing_logs`,
  not here.
- `blog_posts` + `publishing_logs`: publish-result updates post status,
  `wordpress_post_id`, `wordpress_url`, `published_at`, `failure_reason`
  (keyed by the unique `publish_idempotency_key`, scoped to the calling
  website) and closes the latest publishing attempt.
- **Nonce store**: no new table — atomic `Cache::add()` with a 600 s TTL
  (5 min timestamp window + 5 min grace). Uses the configured cache store
  (database in dev, Redis-ready).

## 3. Routes (api — enabled via `withRouting(api:)`, prefix `/api`, group `api`)

| Method | URI | Name | Middleware | Handler |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/wordpress/connect` | `api.wordpress.connect` | `throttle:wordpress-connect` + signature | Api\WordPressApiController@connect |
| POST | `/api/v1/wordpress/verify` | `api.wordpress.verify` | `throttle:wordpress-verify` + signature | @verify |
| POST | `/api/v1/wordpress/disconnect` | `api.wordpress.disconnect` | `throttle:wordpress-api` + signature | @disconnect |
| POST | `/api/v1/wordpress/heartbeat` | `api.wordpress.heartbeat` | `throttle:wordpress-api` + signature | @heartbeat |
| POST | `/api/v1/wordpress/publish-result` | `api.wordpress.publish-result` | `throttle:wordpress-publish-result` + signature | @publishResult |

**Signature (identical in both directions):**

```
X-ABX-Signature = hex( HMAC_SHA256( secret,
      METHOD  "\n"  PATH  "\n"  TIMESTAMP  "\n"  NONCE  "\n"  HEX(SHA256(raw_body)) ) )
```

- `METHOD` uppercase (`POST`); `PATH` = full path incl. `/api` prefix
  (`/api/v1/wordpress/connect`); `TIMESTAMP` unix seconds; `NONCE` ≥16 chars
  of `[A-Za-z0-9_-]`, single-use; `raw_body` = exact bytes sent
  (`Content-Type: application/json`); comparison via `hash_equals`.
- Headers: `X-ABX-Key` (`abx_…`), `X-ABX-Timestamp`, `X-ABX-Nonce`,
  `X-ABX-Signature`.

## 4. Files to create / change

Create:
- `routes/api.php` + `bootstrap/app.php` (add `api:` routing)
- `app/Services/HmacSigner.php` — `payload()` + `sign()` + `verify()`
  (single source of truth for the signature format, both directions)
- `app/Exceptions/WordPressAuthenticationException.php` — carries stable
  `errorCode` + HTTP status
- `app/Services/WordPressAuthenticationService.php` — header parsing, format
  checks, ±5 min skew, key lookup (dummy-HMAC timing guard), `hash_equals`
  signature check, atomic nonce consume; logs `authenticate/failed` for a
  known key
- `app/Http/Middleware/VerifyWordPressSignature.php` — converts the service's
  exception into the JSON error envelope
- `app/Services/WordPressConnectionService.php` — connect/verify/disconnect/
  heartbeat state transitions + connection_logs rules
- `app/Services/PublishingService.php` — `recordPluginResult()` (created here
  because publish-result is a Phase 3 endpoint; Phase 5 extends it with
  outbound publishing)
- `app/Http/Controllers/Api/WordPressApiController.php`
- `app/Http/Requests/WordPressApiRequest.php` (base: JSON 422 with
  `error: validation_failed`), `ConnectRequest`, `HeartbeatRequest`,
  `PublishResultRequest`
- `app/Providers/AppServiceProvider.php` — named `RateLimiter`s
- `docs/wordpress-api.md`
- Tests: `tests/Unit/HmacSignerTest.php`,
  `tests/Unit/WordPressAuthenticationServiceTest.php`,
  `tests/Feature/WordPressApiTest.php`

Change:
- `PROGRESS.md` (phase table + notes)

## 5. Business & security rules / edge cases

- **Rejections (JSON, stable `error` codes):**
  `missing_headers` 401 · `invalid_nonce` 401 · `stale_timestamp` 401
  (±300 s) · `unknown_key` 401 (revoked/absent; dummy HMAC computed first so
  timing doesn't leak existence) · `invalid_signature` 401 (`hash_equals`) ·
  `replayed_nonce` 401 (`Cache::add` atomic; consumed only after a fully
  valid signature) · `validation_failed` 422 · rate limit 429 ·
  `unknown_idempotency_key` 422.
- Known-key failures (`invalid_signature`, `stale_timestamp`,
  `replayed_nonce`) write a `connection_logs` row (action `authenticate`,
  status failed, friendly message, IP) — after throttling, never logging
  secrets/signatures/nonces.
- **Rate limits (named, per key AND per IP):** connect 10/min each ·
  verify 30/min each · disconnect/heartbeat (`wordpress-api`) key 60/min,
  IP 120/min · publish-result 60/min each.
- **Status transitions:** connect → connected (+versions, last_connected_at,
  last_sync_at, log `connect`); heartbeat → connected + fresh
  last_sync_at/versions (log `heartbeat` only when the status changed —
  routine heartbeats must not flood logs); disconnect → disconnected (log);
  verify → read-only, no log on success. `error` remains reserved for
  outbound connection-test failures (Phase 5).
- **publish-result is idempotent:** row locked with `lockForUpdate()` inside
  a transaction; if the post is already `published` → return
  `already_recorded: true` without touching anything; the publishing log is
  updated (or created) exactly once. Cross-website key →
  `unknown_idempotency_key`.
- Payloads validated by Form Requests (`wordpress_version`/`plugin_version`
  ≤32 chars; publish-result: uuid key, boolean success, conditional
  `wordpress_post_id`/`url`/`message`); nothing is read from the body before
  validation; every external value bounded.
- Query strings are never read (signature covers path only) — plugin must not
  append query params.
- Session-less routes: no `web` middleware, no CSRF, `api/*` renders JSON.

## 6. UI pages and states

None directly (headless module). Its effects surface in existing UI:
websites.show connection activity + status badge, dashboard metrics.

## 7. Acceptance criteria

- [x] `routes/api.php` enabled; all 5 routes registered with named throttles
- [x] Valid signed request: connect → 200, website `connected`, versions +
      timestamps stored, `connection_logs` row written
- [x] Missing header / unknown or revoked key / bad signature / ±5 min skew /
      reused nonce each rejected with correct status + stable error code
- [x] Nonce cannot be replayed even with a valid signature; timestamp window
      enforced both directions (past and future)
- [x] Failed auth with a known key is logged without secrets
- [x] Rate limits return 429 (connect enforced after 10/min per key)
- [x] verify read-only; disconnect → disconnected; heartbeat refreshes
      versions/last_sync_at and logs only on status change
- [x] publish-result: success marks post published with WP id/URL and closes
      the log; duplicate call is a no-op (`already_recorded`); failure marks
      post failed; foreign website's key → 422
- [x] Unit test pins the signature payload format (fixed vectors)
- [x] `docs/wordpress-api.md` complete: auth, worked PHP example, every
      endpoint, both directions, error codes, signed curl/PHP script
- [x] Real signed HTTP smoke test against `php artisan serve`
- [x] Full suite + Pint pass

## 8. Tests to be written

- `HmacSignerTest` (Unit): deterministic signature for fixed inputs; payload
  uses `\n` separators and body hash; tampered method/path/timestamp/nonce/
  body each produce a different signature; `verify()` true/false.
- `WordPressAuthenticationServiceTest` (Unit): valid request passes and
  returns the website; missing headers, malformed nonce, stale/future
  timestamp, unknown key, wrong secret, replayed nonce each map to the
  documented exception code; dummy-HMAC path exercised.
- `WordPressApiTest` (Feature): the seven acceptance behaviors above through
  real HTTP, signed with a test helper that builds headers exactly like the
  plugin would; validation 422s; publish-result idempotency and
  cross-website isolation.

## As built

Completed in Phase 3. Created:

- `bootstrap/app.php` (+`api:` routing) and `routes/api.php` — five POST
  routes under `/api/v1/wordpress/*`, each `throttle:<named limiter>` **then**
  `VerifyWordPressSignature` (429s happen before any auth logging);
  `api/*` always renders JSON, no session/CSRF.
- `app/Services/HmacSigner.php` — `payload()`/`sign()`/`verify()`; the single
  canonical-string implementation used by both directions.
- `app/Services/WordPressAuthenticationService.php` — header presence → nonce
  format → key lookup (dummy HMAC for unknown keys) → ±300 s timestamp
  (logged when the key is known) → `hash_equals` signature → atomic
  `Cache::add` nonce consume (600 s TTL, only after an authentic signature).
  Failures throw `App\Exceptions\WordPressAuthenticationException` with the
  stable code + status.
- `app/Http/Middleware/VerifyWordPressSignature.php` — converts the exception
  to `{message, error}` JSON; attaches the website via
  `$request->attributes->set('abx_website', …)`; static `website()` helper
  for controllers.
- `app/Providers/AppServiceProvider.php` — four named `RateLimiter`s, each
  returning **two** `Limit`s (per key + per IP): connect 10/10, verify 30/30,
  `wordpress-api` 60/120, publish-result 60/60 (per minute).
- `app/Services/WordPressConnectionService.php` — connect (status + versions +
  `last_connected_at`/`last_sync_at` + log), verify (read-only + log),
  disconnect (status + log), heartbeat (refresh; reconnect if not connected;
  logs **only** when the status changed).
- `app/Services/PublishingService.php` — `recordPluginResult()`: transaction +
  `lockForUpdate()` on the post found by `publish_idempotency_key` scoped to
  the calling website; already-final posts return `already_recorded: true`
  with zero writes; closes the latest in-flight `publishing_logs` row (or
  creates one) exactly once. Phase 5 extends this service with outbound
  publishing.
- `app/Http/Controllers/Api/WordPressApiController.php` + Form Requests
  (`WordPressApiRequest` base renders 422 with `error: validation_failed`;
  `ConnectRequest`/`HeartbeatRequest` constrain versions with a character
  pattern; `PublishResultRequest` derives conditional requirements from the
  boolean `success` flag).
- `docs/wordpress-api.md` — plugin-facing contract: headers, canonical
  string, worked example with **computed** values, PHP signing helper,
  outbound verification steps, retry rules, all five endpoints, error-code
  table, rate-limit table, status lifecycle, `sign-request.php` script.

Deviations from the original sketch (intentional):

- Publish outcomes are **not** written to `connection_logs` (they live in
  `publishing_logs`); `connection_logs` actions are
  {connect, verify, disconnect, heartbeat, authenticate}.
- A successful `verify` does log (it is a user-triggered action, not a poll).

Verification (real output):

```
phpunit → OK (112 tests, 389 assertions)   [was 77 before Phase 3]
pint --dirty → fixed 5 files, suite re-run green
```

Live HTTP smoke against `php artisan serve` (temporary website created,
exercised, then deleted):

```
[good]   HTTP/1.1 200 OK  {"data":{"website_id":1,...,"status":"connected",...}}
[badsig] HTTP/1.1 401     {"message":"The request signature does not match...","error":"invalid_signature"}
[stale]  HTTP/1.1 401     {"message":"The X-ABX-Timestamp header must be within 5 minutes...","error":"stale_timestamp"}
[verify] HTTP/1.1 200 OK  {"data":{...,"wordpress_version":"6.7.1",...}}

DB after smoke:
connect      success  Plugin connected (WordPress 6.7.1, plugin 1.0.0).
authenticate failed   Request signature verification failed.
authenticate failed   Request timestamp outside the allowed window.
verify       success  Credentials verified.
```
