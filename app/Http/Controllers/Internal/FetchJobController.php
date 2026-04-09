<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Resources\FetchJobResource;
use App\Jobs\FetchSpaceArticlesJob;
use App\Models\FetchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FetchJobController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $userId = (int) $request->header('X-User-Id');

        $job = FetchJob::create([
            'id'      => (string) Str::uuid(),
            'user_id' => $userId,
            'status'  => FetchJob::STATUS_PENDING,
        ]);

        FetchSpaceArticlesJob::dispatch($job->id);

        return (new FetchJobResource($job))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Request $request, string $id): FetchJobResource
    {
        $userId = (int) $request->header('X-User-Id');

        $job = FetchJob::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        return new FetchJobResource($job);
    }
}
