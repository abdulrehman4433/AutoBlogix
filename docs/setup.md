# AutoBlogix — Local Setup & Project Structure (Phase 0)

This document describes how to install and run AutoBlogix locally, and the baseline
project structure every module builds on.

## Requirements

| Tool   | Version                              |
| ------ | ------------------------------------ |
| PHP    | 8.4+ (extensions: pdo_mysql, mbstring, openssl, tokenizer, curl, xml) |
| Composer | 2.x                               |
| MySQL  | 8+ (development environment may use a MySQL-compatible server such as MariaDB 12) |
| Node   | 20+ (npm, for Vite + Tailwind build) |

Framework: **Laravel 13** (latest stable at time of writing), auth scaffolding via
**Laravel Breeze (Blade stack)**, frontend via **Blade + Tailwind CSS 4 + Alpine.js**.

## Install

```sh
# 1. Create the databases (MySQL/MariaDB shell)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS auto_blogix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE IF NOT EXISTS auto_blogix_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Adjust DB credentials in .env if needed:
#    DB_DATABASE=auto_blogix, DB_USERNAME=root, DB_PASSWORD=...

# 4. Migrate + seed
php artisan migrate:fresh --seed

# 5. Frontend build
npm install
npm run build        # or: npm run dev (watch mode)

# 6. Run the app (two terminals)
php artisan serve            # http://localhost:8000
php artisan queue:work        # queue worker (database driver)

# 7. Scheduler (development: run every minute)
php artisan schedule:work
# production cron:
# * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Environment (`.env.example`)

Key values committed to `.env.example` (never commit `.env` itself):

```dotenv
APP_NAME=AutoBlogix
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auto_blogix
DB_USERNAME=
DB_PASSWORD=
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
AI_PROVIDER=development
AI_API_KEY=
WORDPRESS_API_TIMEOUT=30
```

- Queue: `database` in development, Redis-ready in production (no driver-specific code).
- Cache: `database` in development; atomic locks use `Cache::lock()` (Redis-ready).
- Tests use `auto_blogix_testing` (MySQL) or sqlite in-memory — configured in `phpunit.xml`.

## Frontend stack

- **Tailwind CSS 4** via `@tailwindcss/vite` (already wired in `vite.config.js`).
- **Alpine.js** for lightweight interactions only: modals, dropdowns, mobile sidebar.
  Loaded once from `resources/js/app.js`.
- No React/Vue/Inertia/Livewire.

## Folder structure (conventions used by all modules)

```
app/
  Http/
    Controllers/          # thin: validate + call service + redirect/render
    Requests/             # Form Requests for every write endpoint
    Middleware/            # API HMAC auth, rate limiting helpers
  Models/                 # Eloquent models, casts, scopes, relationships
  Policies/               # one policy per resource (ownership checks)
  Services/               # business logic (WebsiteService, PublishingService, ...)
    AI/                   # AiManager, Contracts/AiProviderInterface, Providers/
  Jobs/                   # queue jobs ($tries, $timeout, backoff, failed())
  Events/ / Listeners/    # domain events where useful
  Notifications/          # email notifications where useful
  Providers/
resources/
  views/
    layouts/              # app.php (sidebar shell), guest.php (auth pages)
    components/           # reusable Blade components (alert, button, modal, ...)
    dashboard/ websites/ posts/ schedules/ ai/ logs/ settings/ auth/
routes/
  web.php                 # authenticated UI routes
  api.php                 # WordPress plugin API (HMAC-signed)
database/
  migrations/ factories/ seeders/
docs/                     # per-module documentation (this file first)
tests/                    # Feature/ + Unit/ (PHPUnit)
```

## Coding standards

- PSR-12, `declare(strict_types=1)` in every PHP file, explicit return types.
- Thin controllers, small methods, dependency injection, no repositories.
- Mass-assignment protection (`$fillable`), Form Requests for all input,
  Policies for every resource.
- No business logic in controllers or Blade views.
