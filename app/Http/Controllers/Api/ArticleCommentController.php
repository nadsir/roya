<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleCommentController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Article $article): JsonResponse
    {
        $comments = $article->comments()
            ->with('user:id,name')
            ->with(['replies' => function ($query) {
                $query->where('status', 'approved')
                    ->with('user:id,name')
                    ->latest('created_at');
            }])
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->latest('created_at')
            ->paginate(self::PER_PAGE)
            ->through(fn (Comment $comment) => $this->commentPayload($comment));

        return response()->json($comments);
    }

    public function store(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'rating' => ['prohibited'],
        ]);

        $comment = $article->comments()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'rating' => null,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->commentPayload($comment)], 201);
    }

    protected function commentPayload(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'title' => $comment->title,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toISOString(),
            'user' => $comment->user ? [
                'id' => $comment->user->id,
                'name' => $comment->user->name,
            ] : null,
            'replies' => $comment->replies->map(function (Comment $reply) {
                return [
                    'id' => $reply->id,
                    'title' => $reply->title,
                    'body' => $reply->body,
                    'created_at' => $reply->created_at?->toISOString(),
                    'user' => $reply->user ? [
                        'id' => $reply->user->id,
                        'name' => $reply->user->name,
                    ] : null,
                    'replies' => [],
                ];
            })->values(),
        ];
    }
}