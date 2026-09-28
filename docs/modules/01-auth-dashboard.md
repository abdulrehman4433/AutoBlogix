# Module 01 — Auth, Dashboard Shell & Seeding

Status: complete

## 1. Purpose and place in the process

Covers **complete-process steps 1** (user registers or logs in; seeded user available)
and **14** (dashboard shows metrics, activity and upcoming posts). Everything in
phases 2–7 renders inside this module's sidebar shell.

This phase is also the **project audit**: inspect what the existing project already
has (auth scaffolding, Tailwind, Alpine, layouts, components, env) and add only
what is missing.

## 2. Tables / columns / relationships touched

Audit found missing foundation tables required for real dashboard metrics, so this
module creates them (feature code for websites/posts/logs arrives in phases 2–7):

- `websites` — id, user_id (FK cascade), name, url (normalized), api_key (unique,
  nullable until credentials are generated), encrypted_api_secret (nullable),
  status (pending/connected/disconnected/error), wordpress_version, plugin_version,
  last_connected_at, last_sync_at, timezone (default UTC), timestamps.
  Unique (user_id, url); index (user_id, status).
- `blog_posts` — id, user_id (FK cascade), website_id (FK cascade), title, slug,
  topic, excerpt, content, status (draft/generating/generated/scheduled/publishing/
  published/failed/cancelled), source (manual/ai), category, tags (json),
  meta_description, keywords (json), ai_provider, ai_model, scheduled_at,
  published_at, wordpress_post_id, wordpress_url, failure_reason,
  publish_idempotency_key (unique, nullable), timestamps.
  Index (user_id, website_id, status, scheduled_at).
- `connection_logs` — id, website_id (FK cascade), action, status
  (success/failed), message, ip_address, timestamps. Index (website_id, created_at).
- `publishing_logs` — id, website_id (FK cascade), post_id (FK cascade), attempt,
  status (pending/processing/success/failed), http_status, response_summary,
  error_message, started_at, completed_at. Index (website_id, created_at),
  index (post_id, status).

Statuses are **PHP backed enums** castable from string columns (flagged
improvement: prevents invalid states at the DB boundary; spec listed strings).

Assumption: deleting a website cascades its posts and logs (simplest integrity
rule for an MVP; revisit with restrict-on-delete if post history must survive).

Relationships: User hasMany Websites/BlogPosts; Website belongsTo User, hasMany
BlogPosts/Schedules/ConnectionLogs/PublishingLogs; BlogPost belongsTo User+Website;
ConnectionLog/PublishingLog belongTo Website; PublishingLog belongsTo BlogPost.

## 3. Routes

| Method | URI | Name | Notes |
| --- | --- | --- | --- |
| GET | `/` | (none) | redirect to `/dashboard` (auth middleware then sends guests to login with intended URL) |
| GET | `/dashboard` | `dashboard` | DashboardController@index, middleware `auth`, `verified` |

Existing Breeze routes (login, register, password reset, verification, profile)
are kept unchanged.

## 4. Files to create / change

Create:
- `database/migrations/xxxx_create_websites_table.php`
- `database/migrations/xxxx_create_blog_posts_table.php`
- `database/migrations/xxxx_create_connection_logs_table.php`
- `database/migrations/xxxx_create_publishing_logs_table.php`
- `app/Enums/WebsiteStatus.php`, `PostStatus.php`, `PostSource.php`,
  `PublishingLogStatus.php`, `ConnectionLogStatus.php`
- `app/Models/Website.php`, `BlogPost.php`, `ConnectionLog.php`, `PublishingLog.php`
- `database/factories/WebsiteFactory.php`, `BlogPostFactory.php`
- `app/Services/DashboardService.php` — all metric queries (thin controller)
- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard/index.blade.php`
- `database/seeders/UserSeeder.php`
- `tests/Feature/DashboardTest.php`, `tests/Feature/UserSeederTest.php`

Change:
- `routes/web.php` — `/` redirect, dashboard → controller
- `database/seeders/DatabaseSeeder.php` — call UserSeeder
- `tests/Feature/ExampleTest.php` — `/` now redirects
- Delete: `resources/views/welcome.blade.php` (Laravel splash, replaced by redirect)

## 5. Business & security rules / edge cases

- DashboardService queries only `auth()->user()`'s rows — metrics never leak
  across users (test: cross-user data does not appear).
- UserSeeder uses `updateOrCreate` on email → idempotent; exactly one user.
- Production guard: when `app()->environment('production')` and
  `SEED_ADMIN_PASSWORD` is empty → skip seeding the default account with a clear
  console warning (never writes the default password in production).
- Guest hitting `/` or `/dashboard` lands on login and returns to dashboard after.
- Counts are aggregate queries (no loading whole tables); log lists `->latest()->take()`.
- Views escape by default (`{{ }}`); no secrets anywhere in views/logs.

## 6. UI pages and states

- Dashboard cards: Websites (total, connected), Posts (total, draft, scheduled,
  published, failed) with links to filtered indexes where the route exists yet
  (guarded so phase 1 has no dead links).
- Panels: Recent publishing activity, Upcoming scheduled posts, Recent connection
  activity — each with a friendly empty state when there is no data.
- Loading: server-rendered (no client state needed); flash messages and
  validation errors come from the Phase 0 shell.

## 7. Acceptance criteria

- [x] Audit documented in PROGRESS.md (what existed, what was added)
- [x] `php artisan migrate:fresh --seed` succeeds against `auto_blogix`
- [x] Seeder creates exactly one user: admin@autoblogix.test / password
- [x] Running the seeder twice does not duplicate the user
- [x] Production guard refuses the default password
- [x] `/` redirects; guest → login → back to dashboard
- [x] Registration, login, logout, password reset, email verification work
- [x] Dashboard shows real DB-backed metrics for the logged-in user only
- [x] Empty states render on a fresh account
- [x] Frontend build succeeds (`npm run build`)
- [x] Pint passes; full test suite passes

## 8. Tests to be written

- `DashboardTest`: guest redirected to login; user sees dashboard; metrics show
  only own rows (second user's websites/posts not counted); empty state.
- `UserSeederTest`: seeds exactly one user with expected attributes; idempotent
  on second run; production guard skips when SEED_ADMIN_PASSWORD missing.
- Breeze's existing auth feature tests (registration, login, reset, verification)
  must keep passing; `ExampleTest` updated for the `/` redirect.

## As built

### Audit result (what already existed vs what was added)

Existed and kept: Laravel 13 + Breeze Blade auth (register/login/logout/reset/
verification/confirm, ProfileController), Tailwind 4 + Vite 8 wiring, Phase 0
layouts (`layouts/app` sidebar shell, `layouts/guest`) and components
(alert, button, badge, input, textarea, select, table, modal + Breeze extras),
`.env.example` keys, `docs/setup.md`, `PROGRESS.md`.

Added/fixed in this phase:
- Toolchain repair: host Node 20.11 < Vite 8's floor (20.19+); winget install
  blocked (no elevation), so a **portable Node 22.23.3** lives at
  `C:\Users\Abdul Rehman\.local\node22\node-v22.23.3-win-x64` and is used for
  builds (`README` documents Node ≥20.19 requirement). Also worked around the
  npm optional-deps bug by installing `@rolldown/binding-win32-x64-msvc` manually.
- Removed the stray `app.Services/` directory (typo) and Laravel splash
  (`welcome.blade.php`); `/` now redirects to `/dashboard`.

### Final file list

- Migrations: `create_websites_table`, `create_blog_posts_table`,
  `create_connection_logs_table`, `create_publishing_logs_table`
- Enums: `App\Enums\{WebsiteStatus, PostStatus, PostSource, PublishingLogStatus, ConnectionLogStatus}`
- Models: `Website`, `BlogPost`, `ConnectionLog`, `PublishingLog`; `User` gained
  `websites()` / `blogPosts()` relations
- Factories: `WebsiteFactory` (states: `withCredentials`, `connected`),
  `BlogPostFactory` (states: `scheduled`, `published`)
- Services: `App\Services\DashboardService` (all metric queries)
- Controllers: `DashboardController`
- Views: `dashboard/index.blade.php`
- Seeders: `UserSeeder`, `DatabaseSeeder`
- Config: `config/app.php` → `seed_admin_password` + APP_NAME default `AutoBlogix`
- Routes: `Route::redirect('/', '/dashboard')`, `dashboard` → controller
- Tests: `DashboardTest` (6), `UserSeederTest` (4), `ExampleTest` (updated)

### Verification output

```
php artisan migrate:fresh --seed   →  7 migrations DONE, "Seeded admin user admin@autoblogix.test."
php artisan test                   →  Tests: 35 passed (97 assertions)
vendor/bin/pint --dirty            →  {"result":"passed"}
npm run build                      →  ✓ built in 7.07s (app.css 60 kB, app.js 54 kB)
HTTP smoke                         →  / ⇒ 302 /dashboard; /login ⇒ 200 "AutoBlogix" + assets 200
```

### Assumptions / deviations

- Foundation tables (websites/blog_posts/connection_logs/publishing_logs) were
  created here because "dashboard with real metrics" requires them; phases 2–7
  build feature code on top and note "tables created in Phase 1".
- Statuses are backed enums instead of free strings (flagged improvement).
- `SEED_ADMIN_PASSWORD` is read via `config('app.seed_admin_password')` instead
  of `env()` (flagged improvement: works with `config:cache`).
- Deleting a website cascades its posts and logs (MVP integrity rule).
- `db:seed` in production tests requires `--force` (framework prompt), handled
  in the test itself.
