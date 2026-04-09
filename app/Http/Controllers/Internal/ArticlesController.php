<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticlesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = (int) $request->header('X-User-Id');

        $articles = Article::where('user_id', $userId)
            ->orderByDesc('fetched_at')
            ->paginate(15);

        return ArticleResource::collection($articles);
    }

    public function show(Request $request, int $id): ArticleResource
    {
        $userId = (int) $request->header('X-User-Id');

        $article = Article::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        return new ArticleResource($article);
    }
}
