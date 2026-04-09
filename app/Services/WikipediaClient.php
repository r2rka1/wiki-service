<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WikipediaClient
{
    /**
     * Wikipedia's API policy requires a descriptive User-Agent on every
     * request. Requests without one are rejected with HTTP 403.
     * See https://meta.wikimedia.org/wiki/User-Agent_policy
     */
    private const USER_AGENT = 'space-articles-wiki-service/1.0 (+https://github.com/r2rka1/wiki-service) Laravel/Guzzle';

    /**
     * Fetch a list of "Space"-related articles from Wikipedia.
     *
     * Uses the public MediaWiki action API to discover article titles related
     * to the topic, then the REST summary endpoint for each title.
     *
     * @return array<int, array{pageid:int,title:string,extract:string,source_url:string,content:?string}>
     */
    public function fetchSpaceArticles(int $limit): array
    {
        $base = config('services.wikipedia.base_url');

        // 1. discover relevant titles via MediaWiki search
        $search = $this->client()
            ->get('https://en.wikipedia.org/w/api.php', [
                'action'   => 'query',
                'list'     => 'search',
                'srsearch' => 'Space exploration',
                'srlimit'  => $limit,
                'format'   => 'json',
            ])
            ->throw()
            ->json('query.search', []);

        $articles = [];
        foreach ($search as $hit) {
            $title = $hit['title'] ?? null;
            if (! $title) {
                continue;
            }
            $summary = $this->client()
                ->get(rtrim($base, '/') . '/page/summary/' . rawurlencode($title))
                ->json();

            if (! is_array($summary) || empty($summary['pageid'] ?? null)) {
                continue;
            }

            $articles[] = [
                'pageid'     => (int) $summary['pageid'],
                'title'      => (string) ($summary['title'] ?? $title),
                'extract'    => (string) ($summary['extract'] ?? ''),
                'content'    => $summary['extract_html'] ?? null,
                'source_url' => (string) ($summary['content_urls']['desktop']['page']
                    ?? 'https://en.wikipedia.org/wiki/' . rawurlencode($title)),
            ];
        }

        return $articles;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(10)
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept'     => 'application/json',
            ]);
    }
}
