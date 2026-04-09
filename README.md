# wiki-service

Laravel 10 microservice on **PHP 8.2** (strict). Pairs with
[`main-api`](../main-api): receives internal requests, runs queued jobs to fetch
Wikipedia articles about Space, and exposes them via internal HTTP endpoints.

See [PLAN.md](./PLAN.md) for the full implementation plan.

## Status

Scaffolding only — not yet implemented. Skills available under `.claude/skills/`.

## PHP version

Pinned to **8.2** via `.php-version`. This is intentional — `main-api` runs on 8.3
but this service must remain on 8.2. The shell wrapper in this environment will
automatically pick `/usr/bin/php8.2` when invoked from this directory.

## Database

Uses MySQL database `wiki_service` on the same MySQL host as `main-api`
(which uses database `main_api`). The two services do **not** share tables —
all cross-service communication goes over internal HTTP.

## Quick start (after implementation)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8001     # http://localhost:8001
php artisan queue:work            # in another terminal
```
