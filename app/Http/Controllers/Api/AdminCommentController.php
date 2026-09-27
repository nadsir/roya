<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AdminCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'commentable_type' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Comment::query()
            ->with('user:id,name')
            ->latest('created_at')->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['commentable_type'])) {
            $type = $this->normalizeCommentableType($validated['commentable_type']);

            if ($type === null) {
                throw ValidationException::withMessages([
                    'commentable_type' => ['نوع نظر باید product یا article باشد.'],
                ]);
            }

            $query->where('commentable_type', $type);
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $comments = $query->paginate(20)->through(function (Comment $comment) {
            return [
                'id' => $comment->id,
                'user' => $comment->user ? [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                ] : null,
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'parent_id' => $comment->parent_id,
                'title' => $comment->title,
                'body' => $comment->body,
                'rating' => $comment->rating,
                'status' => $comment->status,
                'created_at' => $comment->created_at?->toISOString(),
            ];
        });

        return response()->json($comments);
    }

    public function updateStatus(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $comment->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'وضعیت نظر با موفقیت به‌روزرسانی شد.',
            'comment' => [
                'id' => $comment->id,
                'status' => $comment->fresh()->status,
            ],
        ]);
    }

    public function destroy(Comment $comment): Response
    {
        $comment->delete();

        return response()->noContent();
    }

    protected function normalizeCommentableType(string $type): ?string
    {
        return match (strtolower($type)) {
            'product', 'products' => Product::class,
            'article', 'articles' => Article::class,
            default => str_contains($type, '\\') ? $type : null,
        };
    }
}