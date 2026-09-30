# AutoBlogix

AI-assisted WordPress blogging from one dashboard: connect your WordPress
sites through the AutoBlogix plugin (HMAC-signed API), draft or AI-generate
posts, schedule them by the site's timezone, and publish — with a complete
audit trail of every connection, publish attempt, and AI generation.

> **Scope:** this repository is the Laravel application; the WordPress
> connector plugin lives outside it, installed on the local dev site at
> `C:\zampp\htdocs\autoblogix-site\wp-content\plugins\autoblogix-connector`
> (not part of Laravel's `app/` or autoload).
> Its API contract is [`docs/wordpress-api.md`](docs/wordpress-api.md).

## WordPress plugin

The connector plugin (v1.0.0) is installed and active on the local site
**http://127.0.0.1/autoblogix-site** at
**`C:\zampp\htdocs\autoblogix-site\wp-content\plugins\autoblogix-connector`**
(outside this repository). To ship it to another WordPress site, zip the
folder from `wp-content/plugins`:

```sh
cd C:\zampp\htdocs\autoblogix-site\wp-content\plugins
zip -r autoblogix-connector.zip autoblogix-connector/   # one folder = the zip root
```

Install that same ZIP on each WordPress site (Plugins → Add New → Upload),
then open **AutoBlogix → Connection** in wp-admin and enter the Portal URL
plus the API key/secret issued when the site was added in this portal.
Full build notes, the checked contract, and the acceptance checklist are in
`C:\zampp\htdocs\autoblogix-site\wp-content\plugins\autoblogix-connector\DEVELOPMENT.md`.

## Features (MVP)

- **Websites** — connect WordPress sites, one-time API secret (shown once,
  then a masked hint), rotate/revoke, per-site connection activity log.
- **Posts** — CRUD with per-site slugs, filters/search, sanitized HTML
  preview, status lifecycle (`draft → scheduled → publishing →
  published | failed`, plus AI's `generating → generated`).
- **Publishing** — signed outbound requests from a queued job, a stable
  `publish_idempotency_key` (a retry can never duplicate a post on
  WordPress), per-attempt logs, friendly error messages, manual retry.
- **Schedules** — schedule input in the website's timezone (stored UTC), a
  Schedules page with overdue highlighting, an every-minute scheduler that
  publishes what is due and recovers attempts stranded by a stopped worker,
  and a scheduled queue runner.
- **AI content** — compose/generate/regenerate posts with pluggable
  providers (OpenAI-compatible API or the built-in deterministic development
  stub), global prompt templates editable under Settings, per-attempt logs
  with token counts.
- **Activity logs** — one cross-type feed over connections, publishing
  attempts, and AI generations.
- **Security** — HMAC-signed plugin API (±300 s window, single-use nonces,
  named rate limiters), owner-scoped route bindings + policies (foreign IDs
  → 404), encrypted credentials, HTML sanitization before rendering,
  SSRF-checked URLs.

## Requirements

| Tool | Version |
| --- | --- |
| PHP | 8.4+ (pdo_mysql, mbstring, openssl, tokenizer, curl, xml) |
| Composer | 2.x |
| MySQL | 8+ (development may use a MySQL-compatible server such as MariaDB 12) |
| Node | **≥ 20.19** (Vite 8 floor; npm, for the Tailwind build) |

Framework: **Laravel 13**, auth via **Laravel Breeze (Blade)**, frontend
**Blade + Tailwind CSS 4 + Alpine.js** (no SPA framework).

## Quick start

```sh
# 1. Databases (MySQL/MariaDB shell)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS auto_blogix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS auto_blogix_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Environment + migrate + seed
cp .env.example .env
php artisan key:generate
# adjust DB_* in .env if needed
php artisan migrate:fresh --seed

# 3. Frontend
npm install
npm run build        # or: npm run dev
```

Full environment reference: [`docs/setup.md`](docs/setup.md).

**Login:** `admin@autoblogix.test` / `password` (seeded; override the
password with `SEED_ADMIN_PASSWORD` before seeding a real environment).

## Running it (three terminals)

```sh
php artisan serve         # 1. the app            → http://localhost:8000
php artisan queue:work    # 2. publish jobs (without it, posts stay "Publishing…")
php artisan schedule:work # 3. scheduler + queue runner, every minute
```

On a server, cron replaces step 3 with a single line:

```cron
* * * * * cd /path/to/autoblogix && php artisan schedule:run >> /dev/null 2>&1
```

Details, Windows Task Scheduler steps, and troubleshooting:
[`docs/scheduling.md`](docs/scheduling.md).

## Tests

```sh
php artisan test          # or: vendor/bin/phpunit
```

Feature tests run against **sqlite in-memory** with the sync queue, array
cache, and `Http::fake()` — no network, no real queue worker. The suite
covers all eight modules (189 tests / 923 assertions at the end of
Phase 8).

## Documentation

| Document | Contents |
| --- | --- |
| [`docs/setup.md`](docs/setup.md) | Local install, environment, folder structure, coding standards |
| [`docs/process.md`](docs/process.md) | The document-then-build phase process used to build this app |
| [`docs/architecture.md`](docs/architecture.md) | Layers, state machines, data model, security model, testing strategy |
| [`docs/ai-providers.md`](docs/ai-providers.md) | Provider resolution, configuration, prompt templates, parsing rules |
| [`docs/scheduling.md`](docs/scheduling.md) | Scheduler + queue runner setup, sweep rules, troubleshooting |
| [`docs/wordpress-api.md`](docs/wordpress-api.md) | The plugin API contract (signing, endpoints, payloads, errors) |
| [`docs/modules/01…08`](docs/modules) | Per-module design docs with acceptance checklists and "As built" notes |
| [`PROGRESS.md`](PROGRESS.md) | Resumable build state, decisions, and handoffs |
