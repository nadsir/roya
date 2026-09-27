<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::query()
            ->with(['author', 'categories:id,name,slug', 'products:id,name,slug', 'vehicles:id,name,slug', 'brands:id,name,slug'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);

        return response()->json($articles);
    }

    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->with([
                'author:id,name',
                'categories:id,name,slug',
                'products:id,name,slug',
                'vehicles:id,name,slug',
                'brands:id,name,slug',
            ])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('slug', $slug)
            ->first();

        if (! $article) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        return response()->json(new ArticleResource($article));
    }
}