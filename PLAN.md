# wiki-service — Implementation Plan

Laravel 10 (PHP 8.2) microservice that fetches Wikipedia articles about Space and
serves them to the main-api over a private internal HTTP interface.

> **Important**: this service must run on PHP **8.2** (pinned via `.php-version`).
> Do not upgrade Laravel to a version that requires PHP 8.3+.

## Stack

- PHP 8.2 (pinned via `.php-version`)
- Laravel 10.x  (last LTS branch fully supporting PHP 8.2)
- MySQL 8 (database `wiki_service`, same MySQL host as main-api)
- Queue: `database` driver (no Redis dependency)
- HTTP client: Laravel Http facade (Guzzle)
- PHPUnit/Pest

## Responsibilities

- Receive job dispatch requests from main-api (internal endpoint)
- Run a queued job that calls Wikipedia REST API for "Space" category articles
- Persist articles tagged with the requesting user id
- Expose internal endpoints to read job status & articles

## Database (`wiki_service`)

- `fetch_jobs`:
  - `id` (uuid), `user_id` (int, no FK — different DB), `status`,
    `started_at`, `finished_at`, `error`
- `articles`:
  - `id`, `user_id`, `external_id` (Wikipedia page id), `title`,
    `summary`, `content`, `source_url`, `fetched_at`
  - unique (`user_id`, `external_id`)
- `jobs`, `failed_jobs` (Laravel queue tables)

## Configuration

```
DB_CONNECTION=mysql_wiki
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=wiki_service
DB_USERNAME=app
DB_PASSWORD=secret

QUEUE_CONNECTION=database

INTERNAL_SHARED_SECRET=<must match main-api>
WIKIPEDIA_API_BASE=https://en.wikipedia.org/api/rest_v1
WIKIPEDIA_CATEGORY=Space
WIKIPEDIA_FETCH_LIMIT=10
```

## Internal endpoints

All require `X-Internal-Secret` and `X-User-Id` headers; rejected with 401 otherwise.

| Method | Path | Purpose |
|---|---|---|
| POST | `/internal/jobs/fetch` | Create FetchJob row, dispatch queued job, return id |
| GET  | `/internal/jobs/{id}` | Job status (must belong to X-User-Id) |
| GET  | `/internal/articles?page=N` | Paginated articles for X-User-Id |
| GET  | `/internal/articles/{id}` | Single article (must belong to X-User-Id) |

## Layout (key files)

```
app/
  Http/
    Middleware/
      VerifyInternalSecret.php   # checks X-Internal-Secret
    Controllers/
      Internal/
        FetchJobController.php
        ArticlesController.php
    Resources/
      ArticleResource.php
      FetchJobResource.php
  Jobs/
    FetchSpaceArticlesJob.php    # queued job
  Models/
    FetchJob.php
    Article.php
  Services/
    WikipediaClient.php          # talks to Wikipedia REST API
database/migrations/
  *_create_fetch_jobs_table.php
  *_create_articles_table.php
  *_create_jobs_table.php        # artisan queue:table
routes/web.php                   # internal routes group
config/services.php              # wikipedia config
tests/Feature/Internal/*.php
tests/Unit/WikipediaClientTest.php
docker/{Dockerfile,nginx.conf,php.ini}
```

## FetchSpaceArticlesJob (sketch)

```php
public function handle(WikipediaClient $wiki): void {
    $job = FetchJob::findOrFail($this->jobId);
    $job->update(['status' => 'running', 'started_at' => now()]);
    try {
        $items = $wiki->fetchCategory(config('services.wikipedia.category'),
                                      config('services.wikipedia.limit'));
        foreach ($items as $item) {
            Article::updateOrCreate(
                ['user_id' => $job->user_id, 'external_id' => $item['pageid']],
                [
                    'title'      => $item['title'],
                    'summary'    => $item['extract'],
                    'content'    => $item['content'] ?? null,
                    'source_url' => $item['source_url'],
                    'fetched_at' => now(),
                ],
            );
        }
        $job->update(['status' => 'done', 'finished_at' => now()]);
    } catch (\Throwable $e) {
        $job->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
        throw $e;
    }
}
```

## Implementation steps

1. **Scaffold**: `composer create-project laravel/laravel:^10.0 wiki-service`
2. **Pin PHP**: `.php-version` set to 8.2; ensure `composer.json` requires `"php": "^8.2"`
3. **Migrations**: `fetch_jobs`, `articles`, `queue:table`
4. **VerifyInternalSecret middleware** + register in routes group
5. **WikipediaClient service** with retry + timeout
6. **FetchSpaceArticlesJob** queued job
7. **Controllers** for internal endpoints
8. **Tests**:
   - Unit: WikipediaClient against fake HTTP
   - Feature: middleware rejects without secret
   - Feature: dispatch + run job synchronously, asserts articles persisted
9. **Dockerfile**: php:8.2-fpm + nginx + composer + queue worker entrypoint
10. **README** with run + worker instructions

## Worker

Run alongside the web container:
```
php artisan queue:work --queue=default --tries=3 --timeout=120
```
In docker-compose this is a separate `wiki-worker` service using the same image.

## Done criteria

- `php artisan test` green
- `php artisan migrate` against `wiki_service` DB succeeds
- Posting to `/internal/jobs/fetch` with valid headers enqueues + runs job
- `php -v` reports 8.2.x in CI/build
- Articles appear in DB tagged by `user_id`
