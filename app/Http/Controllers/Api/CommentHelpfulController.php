<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentHelpfulController extends Controller
{
    /**
     * Toggle the authenticated customer's "helpful" vote for a comment.
     */
    public function toggle(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->status !== 'approved') {
            return response()->json(['message' => 'این تجربه در دسترس نیست.'], 404);
        }

        $userId = $request->user()->id;
        $existing = $comment->helpfulVotes()
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $comment->helpfulVotes()->create(['user_id' => $userId]);
        }

        return response()->json([
            'helpful_count' => $comment->helpfulVotes()->count(),
            'is_helpful' => $existing === null,
        ]);
    }
}
