<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WikipediaClient
{
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
        $search = Http::timeout(10)
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
            $summary = Http::timeout(10)
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
}
