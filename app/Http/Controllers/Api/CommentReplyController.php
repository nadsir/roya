<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommentReplyController extends Controller
{
    public function store(Request $request, Comment $comment): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
        ]);

        if ($comment->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'پاسخ به پاسخ مجاز نیست؛ فقط می‌توانید به نظر اصلی پاسخ دهید.',
            ]);
        }

        if (! in_array($comment->commentable_type, [Product::class, Article::class], true)) {
            throw ValidationException::withMessages([
                'commentable_type' => 'پاسخ به این نظر مجاز نیست.',
            ]);
        }

        if ($comment->status === 'rejected') {
            throw ValidationException::withMessages([
                'status' => 'پاسخ به نظر رد شده مجاز نیست.',
            ]);
        }

        $reply = $comment->replies()->create([
            'user_id' => $request->user()->id,
            'commentable_type' => $comment->commentable_type,
            'commentable_id' => $comment->commentable_id,
            'parent_id' => $comment->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'rating' => null,
            'status' => 'pending',
        ]);

        $payload = [
            'id' => $reply->id,
            'title' => $reply->title,
            'body' => $reply->body,
            'created_at' => $reply->created_at?->toISOString(),
            'user' => [
                'id' => $reply->user->id,
                'name' => $reply->user->name,
            ],
        ];

        if ($comment->commentable_type === Product::class) {
            $payload['rating'] = $reply->rating;
        }

        $payload['replies'] = [];
        $payload['commentable_type'] = $reply->commentable_type;
        $payload['commentable_id'] = $reply->commentable_id;
        $payload['parent_id'] = $reply->parent_id;
        $payload['status'] = $reply->status;

        return response()->json(['data' => $payload], 201);
    }
}