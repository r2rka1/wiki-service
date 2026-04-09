<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\FetchJob;
use App\Services\WikipediaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FetchSpaceArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(public string $jobId)
    {
    }

    public function handle(WikipediaClient $wiki): void
    {
        /** @var FetchJob $job */
        $job = FetchJob::findOrFail($this->jobId);
        $job->update([
            'status'     => FetchJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $items = $wiki->fetchSpaceArticles(config('services.wikipedia.limit'));

            foreach ($items as $item) {
                Article::updateOrCreate(
                    [
                        'user_id'     => $job->user_id,
                        'external_id' => (string) $item['pageid'],
                    ],
                    [
                        'title'      => $item['title'],
                        'summary'    => $item['extract'],
                        'content'    => $item['content'],
                        'source_url' => $item['source_url'],
                        'fetched_at' => now(),
                    ],
                );
            }

            $job->update([
                'status'      => FetchJob::STATUS_DONE,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status'      => FetchJob::STATUS_FAILED,
                'finished_at' => now(),
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
