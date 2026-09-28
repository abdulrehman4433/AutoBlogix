# Module 02 — Websites, Credentials & Connection Logs

Status: complete

## 1. Purpose and place in the process

Covers **complete-process steps 2–4** (add a WordPress website → AutoBlogix
generates API Key + Secret shown once → user copies them into the separate
WordPress plugin) plus connection bookkeeping that step 5 (Phase 3's
`/connect`) will flip to "connected". Feeds **step 14** (dashboard "recent
connection activity", already reading `connection_logs`).

The `websites` and `connection_logs` tables were created in Phase 1 (foundation
migrations); this module adds all feature code on top of them.

## 2. Tables / columns / relationships touched

- `websites` (Phase 1): writes `name`, `url` (normalized), `timezone`,
  `api_key` (unique), `encrypted_api_secret` (encrypted cast), `status`,
  credential lifecycle timestamps. Reads for index/show/dashboard.
- `connection_logs` (Phase 1): one row per credential lifecycle event —
  `action` ∈ {credentials_created, credentials_rotated, credentials_revoked},
  `status` ∈ {success, failed}, `message` (friendly, never secrets),
  `ip_address` (requester IP). Phase 3 adds connect/verify/heartbeat events.
- Relationships used: User hasMany Websites; Website belongsTo User,
  hasMany ConnectionLogs (BlogPosts/Schedules/PublishingLogs exist from Phase 1).

## 3. Routes (web)

| Method | URI | Name | Controller |
| --- | --- | --- | --- |
| GET | `/websites` | `websites.index` | WebsitesController@index |
| GET | `/websites/create` | `websites.create` | WebsitesController@create |
| POST | `/websites` | `websites.store` | WebsitesController@store |
| GET | `/websites/{website}` | `websites.show` | WebsitesController@show |
| GET | `/websites/{website}/edit` | `websites.edit` | WebsitesController@edit |
| PUT | `/websites/{website}` | `websites.update` | WebsitesController@update |
| DELETE | `/websites/{website}` | `websites.destroy` | WebsitesController@destroy |
| POST | `/websites/{website}/credentials/rotate` | `websites.credentials.rotate` | WebsiteCredentialController@rotate |
| POST | `/websites/{website}/credentials/revoke` | `websites.credentials.revoke` | WebsiteCredentialController@revoke |

All under `auth` + `verified` middleware (grouped), each write action authorized
through `WebsitePolicy`.

## 4. Files to create / change

Create:
- `app/Support/UrlNormalizer.php` — normalize + safety checks (shared with
  Phase 3 outbound calls so SSRF rules live in exactly one place)
- `app/Rules/ValidWebsiteUrl.php` — validation rule using the normalizer
- `app/Services/WebsiteService.php` — create/update/delete business logic
- `app/Services/WebsiteCredentialService.php` — generateApiKey(),
  generateApiSecret(), rotateCredentials(), revokeCredentials(),
  verifyCredentials() (used by Phase 3 middleware)
- `app/Policies/WebsitePolicy.php` — view/update/delete/rotateCredentials:
  owner-only
- `app/Http/Requests/StoreWebsiteRequest.php`, `UpdateWebsiteRequest.php`
  (prepareForValidation normalizes URL)
- `app/Http/Controllers/WebsitesController.php`
- `app/Http/Controllers/WebsiteCredentialController.php`
- `resources/views/websites/{index,create,edit,show}.blade.php`
- Tests: `tests/Feature/WebsitesTest.php`,
  `tests/Feature/WebsiteCredentialsTest.php`,
  `tests/Unit/WebsiteCredentialServiceTest.php`,
  `tests/Unit/UrlNormalizerTest.php`

Change:
- `routes/web.php` — website routes in the auth group
- (navigation + dashboard CTA light up automatically via `Route::has()`)

## 5. Business & security rules / edge cases

- **URL normalization** (`UrlNormalizer::normalize`): trim; prepend `https://`
  when scheme missing; scheme must be http/https; host lowercased; reject
  userinfo (`user:pass@`), query strings and fragments; drop default ports and
  trailing slash; keep non-default port and path (subdirectory WordPress);
  result ≤255 chars. Throws `InvalidArgumentException` with a friendly message.
- **SSRF**: reject `localhost` and private/loopback/reserved **literal** IP
  hosts when not in a local environment (`local`/`testing` allowed for local
  WordPress dev — per spec "outside local env"). DNS resolution + final IP check
  happens again right before outbound requests in Phase 3.
- **Credentials**: API key `abx_` + 32 chars (uniqueness loop); API secret
  `abxs_` + 40 chars from `bin2hex(random_bytes(20))`. Stored: key plain
  (unique, indexed), secret with `encrypted` cast. Secret never in URL/logs;
  shown once via `session()->flash()` (server-side, one request only), masked
  hint (`••••` + last 4) afterwards. Not mass-assignable (set by service only).
- **Rotate**: new pair replaces old immediately (old key stops verifying on the
  next plugin call), `status → pending` (plugin must re-enter credentials),
  event logged. **Revoke**: credentials nulled, `status → disconnected`, event
  logged. **Create**: pair generated, `status → pending`, event logged.
- **Uniqueness**: unique (user_id, normalized url) → validation error message
  "You already added this website."
- **Policies everywhere**: another user's website → 404 (via `authorize()` on
  model-bound routes); index/show queries filtered by `user_id`.
- **Delete**: confirmation modal warns that posts and logs cascade; policy-gated.
- Mass-assignment: Form Requests only pass validated fields; `api_key` /
  `encrypted_api_secret` excluded from `$fillable`.

## 6. UI pages and states

- **Index**: table (name, URL, status badge, WordPress/plugin version, last
  connected, actions view/edit/delete), "Add website" button, empty state.
- **Create/Edit**: name, URL, timezone select (`DateTimeZone::listIdentifiers()`),
  inline validation errors, disabled loading submit button.
- **Show**: status card (with pending onboarding steps 1–3 when not connected),
  credentials panel (API key full + copy, secret masked hint + copy of key only),
  one-time plaintext-secret alert with copy button when flashed, recent
  connection activity list, edit/rotate/revoke/delete actions with confirmation
  modals.
- Empty states: no websites; no connection activity yet.

## 7. Acceptance criteria

- [x] User can add a website; receives API key + secret exactly once (flash)
- [x] Secret masked to last-4 afterwards; never appears in URL, logs or HTML later
- [x] Same normalized URL cannot be added twice by the same user
- [x] Invalid/unsafe URLs rejected with friendly message (SSRF rule)
- [x] Rotate issues new pair, invalidates old immediately, logs event, returns to pending
- [x] Revoke clears credentials, sets disconnected, logs event
- [x] Cross-user access (show/edit/update/delete/rotate/revoke) returns 404
- [x] Index only lists the authenticated user's websites
- [x] Delete removes website and shows confirmation before doing so
- [x] Dashboard "Add a website" CTA and sidebar Websites link now work
- [x] Unit tests: credential generation/verification, URL normalization
- [x] Full suite + Pint pass

## 8. Tests to be written

- `UrlNormalizerTest` (Unit): scheme prepending, lowercasing, trailing slash,
  port/path handling, rejects userinfo/query/fragment, private IP + localhost
  blocked outside local env, too long.
- `WebsiteCredentialServiceTest` (Unit): key prefix/format/uniqueness, secret
  prefix/length/uniqueness, verifyCredentials match/mismatch/missing, rotate
  changes both and logs, revoke clears + logs.
- `WebsitesTest` (Feature): guest redirect; index scoping; create happy path
  (credentials + log + flash secret); validation errors (name, bad url, dup);
  update; delete cascades posts; cross-user 404s for every route.
- `WebsiteCredentialsTest` (Feature): secret shown once (second visit masked);
  rotate invalidates old key (verifyCredentials fails with old pair);
  revoke; policy 404 for non-owner.

## As built

### Final file list

Created:
- `app/Support/UrlNormalizer.php` — `normalize()` (scheme prepending, host
  lowercasing, charset check `[a-z0-9._-]`, userinfo/query/fragment rejection,
  default-port dropping, trailing-slash strip, ≤255), `isPrivateHost()`
  (literal private/loopback/reserved IPs + `*.localhost`), `isAllowedUrl()`
  (private hosts allowed only in `local`/`testing`)
- `app/Rules/ValidWebsiteUrl.php` — validation rule wrapping the normalizer
- `app/Services/WebsiteCredentialService.php` — `generateApiKey()`,
  `generateApiSecret()`, `issueCredentials()`, `rotateCredentials()`,
  `revokeCredentials()`, `verifyCredentials()` (returns `?Website`,
  `hash_equals` comparison); writes `connection_logs` events
- `app/Services/WebsiteService.php` — `create()` (returns
  `['website' => …, 'secret' => …]`), `update()`, `delete()`
- `app/Policies/WebsitePolicy.php` — view/update/delete/rotateCredentials/
  revokeCredentials, owner-only (auto-discovered)
- `app/Http/Requests/StoreWebsiteRequest.php`, `UpdateWebsiteRequest.php` —
  `prepareForValidation()` normalizes URL; unique rule scoped to the user;
  timezone validated against the real timezone database via closure rule;
  `url.unique` message "You already added this website."
- `app/Http/Controllers/WebsitesController.php`,
  `WebsiteCredentialController.php` — thin; `Gate::authorize` on every show/edit/
  update/destroy/rotate/revoke; secret passed only through `session()->flash()`
- `app/Providers/AppServiceProvider.php` — owner-scoped `Route::bind('website')`
  (other users' IDs resolve to **404**, never 403)
- `resources/views/websites/index.blade.php` (table + empty state + row delete
  modal with dynamic action URL), `create.blade.php`, `edit.blade.php`,
  `show.blade.php` (one-time secret alert with copy button, connection-status
  card with 1-3 onboarding steps, credentials panel with masked hint +
  clipboard copy, connection-activity list, rotate/revoke/delete confirm modals)
- `routes/web.php` — `create`/`store` registered **before** the resource so
  `/websites/create` isn't swallowed by the `{website}` wildcard

Tests (all green):
- `tests/Unit/UrlNormalizerTest.php` (13), `tests/Unit/WebsiteCredentialServiceTest.php` (7),
  `tests/Feature/WebsitesTest.php` (13), `tests/Feature/WebsiteCredentialsTest.php` (6)

### Verification output

```
php artisan route:list --path=websites   → 9 routes (resource + rotate + revoke)
php artisan test                        → Tests: 77 passed (255 assertions)
vendor/bin/pint --dirty --format agent   → {"result":"passed"}
npm run build                           → ✓ built in 4.91s
php artisan migrate:fresh --seed         → "Seeded admin user admin@autoblogix.test."
HTTP smoke (real server, seeded admin):
  login → 200 dashboard · /websites → 200 "No websites yet" · create form → 200
  POST /websites → 302 /websites/2 · show(1): key ✓ secret-plaintext ✓ onboarding ✓
  show(2): secret gone ✓ masked hint "••••b7cf" ✓
```

### Assumptions / deviations

- Owner-scoped `Route::bind('website')` in `AppServiceProvider` implements the
  documented 404-not-403 behavior; policies remain as a second layer.
- Host charset limited to `[a-z0-9._-]` (international domains must be entered
  in punycode) — keeps parse_url's leniency from accepting `exa mple.com`.
- Test note: Laravel 13's CSRF middleware is `PreventRequestForgery`; switching
  `$this->app['env']` in tests disables the test-mode CSRF bypass, so the SSRF
  feature test calls `withoutMiddleware(PreventRequestForgery::class)`.
- The private-host SSRF check re-runs before every outbound request in Phase 3
  (DNS resolution included); literal-IP checks live in one place today.
- Revoked websites can be re-credentialled via the same "rotate" flow
  (button label becomes "Generate credentials" on the show page).
