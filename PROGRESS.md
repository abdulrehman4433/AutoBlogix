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
| 3 | WordPress API (HMAC middleware, endpoints, rate limits, nonce store) + docs/wordpress-api.md | docs/modules/03-wordpress-api.md | pending — next |
| 4 | Blog posts module (CRUD, filters, preview, statuses) | docs/modules/04-blog-posts.md | pending |
| 5 | WordPress publishing (service, job, idempotency, logs, retry) | docs/modules/05-publishing.md | pending |
| 6 | AI layer (interface, manager, providers, prompts + seeder, UI, logs) | docs/modules/06-ai.md | pending |
| 7 | Schedules + scheduler + queue jobs + timezone logic | docs/modules/07-schedules.md | pending |
| 8 | Settings, logs UI, polish, final docs (README, process.md, architecture, ai-providers, scheduling), full test run | docs/modules/08-settings-logs.md | pending |

Old Phase 0 (setup) is folded into Phase 1's audit; its doc is `docs/setup.md`.

## What exists now (after Phase 2)

- Foundation tables: `websites`, `blog_posts`, `connection_logs`, `publishing_logs`
  (feature code arrives in later phases), plus users/jobs/cache/sessions.
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
  (generateApiKey/ApiSecret, issue/rotate/revoke/verify — Phase 3 middleware
  will call `verifyCredentials(key, secret): ?Website`).
- Support/Rules: **UrlNormalizer** (normalize + isPrivateHost + isAllowedUrl —
  SSRF rules in ONE place for validation AND Phase 3 outbound calls);
  **ValidWebsiteUrl** rule.
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
- Phase 2 verified: 77 tests passing, Pint clean, build clean, HTTP smoke of
  full create → secret-once → masked flow against the running dev server.

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
- `publish_idempotency_key`: uuid column, unique, nullable — generated per
  publish attempt (phase 5).
- Owner-scoped `Route::bind()` → cross-user 404, policies as second layer.
- Laravel 13 CSRF middleware class is `PreventRequestForgery`
  (`ValidateCsrfToken` is a deprecated subclass); test-mode CSRF bypass is
  disabled when a test switches `app['env']` → use `withoutMiddleware` there.
- Website host charset limited to `[a-z0-9._-]` (IDN must be punycode).
- Route registration order: explicit `/websites/create` + POST before the
  resource's `{website}` wildcard.

## Assumptions (one-liners)

- Local MySQL-compatible server is MariaDB 12 (see above).
- APP_URL `http://localhost:8000` via `php artisan serve`.
- Website URL normalized form will be defined in phase 2 (scheme+host, no
  trailing slash) — unique constraint is (user_id, url).

## Open issues / TODO

- [ ] Phase 3: write docs/modules/03-wordpress-api.md FIRST, then implement.
  Build on: `WebsiteCredentialService::verifyCredentials()`,
  `UrlNormalizer` (re-check DNS/IP before outbound calls), connection_logs
  actions `connect|verify|disconnect|heartbeat|publish-result`, website status
  transitions pending → connected / error, `last_connected_at`/`last_sync_at`/
  `wordpress_version`/`plugin_version` columns, named rate limiters,
  docs/wordpress-api.md (HMAC spec, both directions, curl/PHP examples).
- [ ] README does not exist yet as AutoBlogix README (Phase 8 rewrites it).
- [ ] Remember Node 22 portable PATH prefix for npm commands.
- [ ] User model: add `aiProviders()` / `promptTemplates()` relations in Phase 6.
