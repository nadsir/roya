<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCommentController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request, Product $product): JsonResponse
    {
        $userId = $request->user()?->id;

        $comments = $product->comments()
            ->with('user:id,name')
            ->withCount('helpfulVotes')
            ->with(['helpfulVotes' => fn ($query) => $query->where('user_id', $userId)])
            ->with(['replies' => function ($query) use ($userId) {
                $query->where('status', 'approved')
                    ->with('user:id,name')
                    ->withCount('helpfulVotes')
                    ->with(['helpfulVotes' => fn ($voteQuery) => $voteQuery->where('user_id', $userId)])
                    ->latest('created_at');
            }])
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->latest('created_at')
            ->paginate(self::PER_PAGE)
            ->through(fn (Comment $comment) => $this->commentPayload($comment));

        $payload = $comments->toArray();
        $payload['rating_summary'] = $this->ratingSummary($product);

        return response()->json($payload);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $comment = $product->comments()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'rating' => $data['rating'] ?? null,
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
            'rating' => $comment->rating,
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
                    'rating' => $reply->rating,
                    'created_at' => $reply->created_at?->toISOString(),
                    'user' => $reply->user ? [
                        'id' => $reply->user->id,
                        'name' => $reply->user->name,
                    ] : null,
                    'replies' => [],
                    'helpful_count' => (int) ($reply->helpful_votes_count ?? 0),
                    'is_helpful' => $reply->relationLoaded('helpfulVotes')
                        ? $reply->helpfulVotes->isNotEmpty()
                        : false,
                ];
            })->values(),
            'helpful_count' => (int) ($comment->helpful_votes_count ?? 0),
            'is_helpful' => $comment->relationLoaded('helpfulVotes')
                ? $comment->helpfulVotes->isNotEmpty()
                : false,
        ];
    }

    protected function ratingSummary(Product $product): array
    {
        $ratings = $product->comments()
            ->where('status', 'approved')
            ->whereNotNull('rating')
            ->pluck('rating');

        $distribution = array_combine(['5', '4', '3', '2', '1'], [0, 0, 0, 0, 0]);

        foreach ($ratings as $rating) {
            $distribution[(string) $rating]++;
        }

        $count = $ratings->count();

        return [
            'average' => $count > 0 ? round((float) $ratings->avg(), 1) : null,
            'count' => $count,
            'distribution' => $distribution,
        ];
    }
}