# wiki-service

Laravel 10 microservice on **PHP 8.2** (strict). Pairs with
[`main-api`](https://github.com/r2rka1/main-api): receives internal HTTP
requests, runs queued jobs to fetch Wikipedia articles about Space, and
exposes them via internal endpoints.

Frontend: [`frontend-angular`](https://github.com/r2rka1/frontend-angular).

See [PLAN.md](./PLAN.md) for the full implementation plan.

## Prerequisites

- PHP **8.2** (`php -v` should report 8.2.x — pinned via `.php-version`)
- Composer
- One of:
  - MySQL 8 reachable on `127.0.0.1:3306` with a `wiki_service` database, **or**
  - SQLite (set `DB_CONNECTION=sqlite` in `.env`)

> The shared MySQL host is also used by `main-api`, but the two services use
> different databases (`wiki_service` here, `main_api` there). They never read
> each other's tables — all cross-service communication is over HTTP.

## Setup

```bash
git clone git@github.com:r2rka1/wiki-service.git
cd wiki-service

composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at minimum:

```dotenv
DB_DATABASE=wiki_service
DB_USERNAME=<your mysql user>
DB_PASSWORD=<your mysql password>

# This MUST match WIKI_SERVICE_SECRET in main-api/.env
INTERNAL_SHARED_SECRET=some-long-random-string
```

If using MySQL, create the database first:

```bash
mysql -uroot -p -e "CREATE DATABASE wiki_service CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then run migrations:

```bash
php artisan migrate
```

## Run locally

In two terminals:

```bash
# terminal 1 — HTTP server
php artisan serve --port=8001

# terminal 2 — queue worker (runs the FetchSpaceArticlesJob)
php artisan queue:work --tries=3 --timeout=120
```

The service is now reachable at `http://localhost:8001`. It is **not** intended
to be called directly from the browser — only from `main-api`.

## Run tests

```bash
php artisan test
```

Tests use **sqlite in-memory**, so no MySQL is required to run them. You should
see 7 passing tests.

## Smoke test the internal API

You can hit the internal endpoint directly with curl. Both headers are required:

```bash
SECRET=$(grep INTERNAL_SHARED_SECRET .env | cut -d= -f2)

# Dispatch a fetch job for user id 1
curl -i -X POST http://localhost:8001/api/internal/jobs/fetch \
  -H "X-Internal-Secret: $SECRET" \
  -H "X-User-Id: 1"

# List articles for user id 1
curl -s http://localhost:8001/api/internal/articles \
  -H "X-Internal-Secret: $SECRET" \
  -H "X-User-Id: 1" | jq
```

Without the queue worker running, jobs will sit in `pending` state — start the
worker to process them.

## Project layout

- `app/Http/Middleware/VerifyInternalSecret.php` — gate on `X-Internal-Secret` + `X-User-Id`
- `app/Http/Controllers/Internal/` — internal HTTP endpoints
- `app/Jobs/FetchSpaceArticlesJob.php` — queued worker job
- `app/Services/WikipediaClient.php` — outbound calls to Wikipedia REST API
- `app/Models/{FetchJob,Article}.php`
- `database/migrations/` — `fetch_jobs`, `articles`, `jobs` (queue), `failed_jobs`

## PHP version

Pinned to **8.2** via `.php-version`. The wrapper installed in this dev
environment automatically picks `/usr/bin/php8.2` when invoked from this
directory. Do not upgrade Laravel past 10.x — newer Laravel requires PHP 8.3+.
