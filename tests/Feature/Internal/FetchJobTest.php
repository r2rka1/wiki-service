<?php

namespace Tests\Feature\Internal;

use App\Jobs\FetchSpaceArticlesJob;
use App\Models\Article;
use App\Models\FetchJob;
use App\Services\WikipediaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class FetchJobTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(int $userId = 42): array
    {
        return [
            'X-Internal-Secret' => 'test-secret',
            'X-User-Id'         => (string) $userId,
        ];
    }

    public function test_dispatch_creates_pending_fetch_job(): void
    {
        Bus::fake();

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/internal/jobs/fetch')
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['id', 'status']]);

        $this->assertDatabaseHas('fetch_jobs', [
            'user_id' => 42,
            'status'  => FetchJob::STATUS_PENDING,
        ]);

        Bus::assertDispatched(FetchSpaceArticlesJob::class);
    }

    public function test_show_returns_only_own_job(): void
    {
        $job = FetchJob::create([
            'id'      => '11111111-1111-1111-1111-111111111111',
            'user_id' => 7,
            'status'  => FetchJob::STATUS_PENDING,
        ]);

        $this->withHeaders($this->authHeaders(99))
            ->getJson('/api/internal/jobs/' . $job->id)
            ->assertStatus(404);

        $this->withHeaders($this->authHeaders(7))
            ->getJson('/api/internal/jobs/' . $job->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $job->id);
    }

    public function test_job_handle_persists_articles_and_marks_done(): void
    {
        $job = FetchJob::create([
            'id'      => '22222222-2222-2222-2222-222222222222',
            'user_id' => 5,
            'status'  => FetchJob::STATUS_PENDING,
        ]);

        $fakeWiki = Mockery::mock(WikipediaClient::class);
        $fakeWiki->shouldReceive('fetchSpaceArticles')
            ->once()
            ->andReturn([
                [
                    'pageid'     => 1001,
                    'title'      => 'Mars',
                    'extract'    => 'The fourth planet.',
                    'content'    => '<p>Mars</p>',
                    'source_url' => 'https://en.wikipedia.org/wiki/Mars',
                ],
                [
                    'pageid'     => 1002,
                    'title'      => 'Saturn',
                    'extract'    => 'A gas giant.',
                    'content'    => '<p>Saturn</p>',
                    'source_url' => 'https://en.wikipedia.org/wiki/Saturn',
                ],
            ]);
        $this->app->instance(WikipediaClient::class, $fakeWiki);

        (new FetchSpaceArticlesJob($job->id))->handle($fakeWiki);

        $this->assertDatabaseCount('articles', 2);
        $this->assertDatabaseHas('articles', ['user_id' => 5, 'title' => 'Mars']);
        $this->assertSame(FetchJob::STATUS_DONE, $job->fresh()->status);
    }

    public function test_articles_index_returns_only_own_articles(): void
    {
        Article::create([
            'user_id'     => 1,
            'external_id' => '500',
            'title'       => 'Mine',
            'summary'     => 's',
            'source_url'  => 'https://x',
            'fetched_at'  => now(),
        ]);
        Article::create([
            'user_id'     => 2,
            'external_id' => '600',
            'title'       => 'Theirs',
            'summary'     => 's',
            'source_url'  => 'https://x',
            'fetched_at'  => now(),
        ]);

        $this->withHeaders($this->authHeaders(1))
            ->getJson('/api/internal/articles')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
