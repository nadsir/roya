<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Brake Pad',
            'slug' => 'brake-pad-' . fake()->unique()->numberBetween(1, 999999999),
            'price' => 100,
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    private function article(): Article
    {
        return Article::create([
            'title' => 'Brake Guide',
            'slug' => 'brake-guide-' . fake()->unique()->numberBetween(1, 999999999),
            'content' => 'Article content',
        ]);
    }

    private function comment(User $user, Product|Article $commentable, array $overrides = []): Comment
    {
        return $commentable->comments()->create(array_merge([
            'user_id' => $user->id,
            'body' => 'Nice product',
            'rating' => null,
            'status' => 'approved',
        ], $overrides));
    }

    // ── 1-3. Product comments ──────────────────────────────────

    public function test_user_can_create_product_comment_with_rating(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $other = $this->customer();
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/products/{$product->id}/comments", [
            'title' => 'Great',
            'body' => 'Brakes work perfectly',
            'rating' => 5,
            'user_id' => $other->id,
            'status' => 'approved',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Brakes work perfectly')
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'commentable_type' => Product::class,
            'commentable_id' => $product->id,
            'body' => 'Brakes work perfectly',
            'rating' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_product_comment_accepts_valid_rating_bounds(): void
    {
        $user = $this->customer();
        $product = $this->product();
        Sanctum::actingAs($user, ['customer']);

        foreach ([1, 3, 5] as $rating) {
            $this->postJson("/api/products/{$product->id}/comments", [
                'body' => 'Comment ' . $rating,
                'rating' => $rating,
            ])->assertCreated();
        }

        $this->assertDatabaseCount('comments', 3);
    }

    public function test_product_comment_rating_out_of_range_is_rejected(): void
    {
        $user = $this->customer();
        $product = $this->product();
        Sanctum::actingAs($user, ['customer']);

        foreach ([0, 6, -1, 'high', 3.5] as $rating) {
            $this->postJson("/api/products/{$product->id}/comments", [
                'body' => 'Comment',
                'rating' => $rating,
            ])->assertUnprocessable()->assertJsonValidationErrors('rating');
        }

        $this->assertDatabaseCount('comments', 0);
    }

    // ── 4-5. Article comments ─────────────────────────────────

    public function test_article_comment_without_rating_is_created(): void
    {
        $user = $this->customer();
        $article = $this->article();
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/articles/{$article->id}/comments", [
            'body' => 'Great article',
        ])->assertCreated()
            ->assertJsonPath('data.body', 'Great article')
            ->assertJsonMissingPath('data.rating');

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'commentable_type' => Article::class,
            'commentable_id' => $article->id,
            'body' => 'Great article',
            'rating' => null,
            'status' => 'pending',
        ]);
    }

    public function test_article_comment_with_rating_is_rejected(): void
    {
        $user = $this->customer();
        $article = $this->article();
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/articles/{$article->id}/comments", [
            'body' => 'Great article',
            'rating' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('rating');

        $this->assertDatabaseCount('comments', 0);
    }

    // ── 6-7. Auth, pending default ────────────────────────────

    public function test_guest_cannot_create_comment(): void
    {
        $product = $this->product();
        $article = $this->article();

        $this->postJson("/api/products/{$product->id}/comments", ['body' => 'x'])->assertUnauthorized();
        $this->postJson("/api/articles/{$article->id}/comments", ['body' => 'x'])->assertUnauthorized();
        $this->postJson('/api/comments/1/replies', ['body' => 'x'])->assertUnauthorized();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_public_store_always_sets_pending_and_ignores_request_status(): void
    {
        $user = $this->customer();
        $product = $this->product();
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/products/{$product->id}/comments", [
            'body' => 'Pending by default',
            'status' => 'approved',
        ])->assertCreated();

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    // ── 8-9. Replies ──────────────────────────────────────────

    public function test_reply_inherits_commentable_from_parent(): void
    {
        $user = $this->customer();
        $root = $this->comment($user, $this->product());
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/comments/{$root->id}/replies", [
            'body' => 'Admin reply here',
        ])->assertCreated()
            ->assertJsonPath('data.parent_id', $root->id)
            ->assertJsonPath('data.commentable_type', $root->commentable_type)
            ->assertJsonPath('data.commentable_id', $root->commentable_id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'commentable_type' => $root->commentable_type,
            'commentable_id' => $root->commentable_id,
            'parent_id' => $root->id,
            'status' => 'pending',
        ]);
    }

    public function test_reply_to_reply_is_rejected(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $root = $this->comment($user, $product);
        $reply = $this->comment($user, $product, ['parent_id' => $root->id]);
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/comments/{$reply->id}/replies", ['body' => 'nested'])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->assertDatabaseCount('comments', 2);
    }

    public function test_reply_to_rejected_parent_is_not_created(): void
    {
        $user = $this->customer();
        $root = $this->comment($user, $this->product(), ['status' => 'rejected']);
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/comments/{$root->id}/replies", ['body' => 'reply'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('comments', 1);
    }

    public function test_reply_to_non_product_or_article_comment_is_rejected(): void
    {
        $user = $this->customer();
        $orphan = Comment::create([
            'user_id' => $user->id,
            'commentable_type' => 'Some\Unknown\Model',
            'commentable_id' => 1,
            'body' => 'orphan',
            'status' => 'approved',
        ]);
        Sanctum::actingAs($user, ['customer']);

        $this->postJson("/api/comments/{$orphan->id}/replies", ['body' => 'reply'])
            ->assertUnprocessable()->assertJsonValidationErrors('commentable_type');

        $this->assertDatabaseCount('comments', 1);
    }

    // ── 10-12. Public listing ────────────────────────────────

    public function test_public_product_listing_only_shows_approved_comments(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $approved = $this->comment($user, $product, ['status' => 'approved']);
        $this->comment($user, $product, ['status' => 'pending']);
        $this->comment($user, $product, ['status' => 'rejected']);

        $this->getJson("/api/products/{$product->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonPath('data.0.user.id', $user->id)
            ->assertJsonPath('data.0.user.name', $user->name);
    }

    public function test_public_listing_only_shows_approved_replies(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $root = $this->comment($user, $product, ['status' => 'approved']);
        $this->comment($user, $product, ['status' => 'approved', 'parent_id' => $root->id]);
        $this->comment($user, $product, ['status' => 'pending', 'parent_id' => $root->id]);
        $this->comment($user, $product, ['status' => 'rejected', 'parent_id' => $root->id]);

        $response = $this->getJson("/api/products/{$product->id}/comments")->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonCount(1, 'data.0.replies');
        $this->assertCount(1, $response->json('data.0.replies'));
        $this->assertDatabaseHas('comments', ['parent_id' => $root->id, 'status' => 'pending']);
        $this->assertDatabaseHas('comments', ['parent_id' => $root->id, 'status' => 'rejected']);
    }

    public function test_product_rating_summary_only_counts_approved_ratings(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $this->comment($user, $product, ['rating' => 5, 'status' => 'approved']);
        $this->comment($user, $product, ['rating' => 5, 'status' => 'approved']);
        $this->comment($user, $product, ['rating' => 4, 'status' => 'approved']);
        $this->comment($user, $product, ['rating' => 1, 'status' => 'pending']);
        $this->comment($user, $product, ['rating' => 2, 'status' => 'rejected']);

        $response = $this->getJson("/api/products/{$product->id}/comments")->assertOk();

        $this->assertSame(4.7, $response->json('rating_summary.average'));
        $this->assertSame(3, $response->json('rating_summary.count'));
        $this->assertSame(2, $response->json('rating_summary.distribution.5'));
        $this->assertSame(1, $response->json('rating_summary.distribution.4'));
        $this->assertSame(0, $response->json('rating_summary.distribution.3'));
        $this->assertSame(0, $response->json('rating_summary.distribution.2'));
        $this->assertSame(0, $response->json('rating_summary.distribution.1'));
    }

    // ── 13-14. Admin moderation ──────────────────────────────

    public function test_admin_can_update_comment_status(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin, ['admin']);
        $user = $this->customer();
        $comment = $this->comment($user, $this->product(), ['status' => 'pending']);

        $this->patchJson("/api/admin/comments/{$comment->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('comment.status', 'approved');

        $this->assertSame('approved', $comment->fresh()->status);

        $this->patchJson("/api/admin/comments/{$comment->id}/status", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('comment.status', 'rejected');

        $this->patchJson("/api/admin/comments/{$comment->id}/status", ['status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $user = $this->customer();
        $comment = $this->comment($user, $this->product());
        Sanctum::actingAs($user, ['customer']);

        $this->getJson('/api/admin/comments')->assertForbidden();
        $this->patchJson("/api/admin/comments/{$comment->id}/status", ['status' => 'approved'])->assertForbidden();
        $this->deleteJson("/api/admin/comments/{$comment->id}")->assertForbidden();
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/admin/comments')->assertUnauthorized();
        $this->patchJson('/api/admin/comments/1/status', ['status' => 'approved'])->assertUnauthorized();
        $this->deleteJson('/api/admin/comments/1')->assertUnauthorized();
    }

    public function test_admin_can_delete_comment_with_replies(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin, ['admin']);
        $user = $this->customer();
        $product = $this->product();
        $root = $this->comment($user, $product);
        $this->comment($user, $product, ['parent_id' => $root->id, 'status' => 'approved']);

        $this->deleteJson("/api/admin/comments/{$root->id}")->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $root->id]);
        $this->assertDatabaseMissing('comments', ['parent_id' => $root->id]);
    }

    public function test_admin_index_filters_status_search_and_type(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin, ['admin']);
        $user = $this->customer();
        $product = $this->product();
        $article = $this->article();
        $this->comment($user, $product, ['body' => 'UNIQUE-QUERY-WORD', 'status' => 'pending']);
        $this->comment($user, $product, ['status' => 'approved']);
        $this->comment($user, $article, ['status' => 'pending']);

        $this->getJson('/api/admin/comments?status=pending')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/comments?status=approved')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/comments?commentable_type=product')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/comments?commentable_type=article')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/comments?search=UNIQUE-QUERY')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/comments?commentable_type=bogus')->assertUnprocessable();
    }

    // ── 15. Pagination ───────────────────────────────────────

    public function test_public_product_comments_are_paginated(): void
    {
        $user = $this->customer();
        $product = $this->product();
        for ($i = 0; $i < 20; $i++) {
            $this->comment($user, $product, ['status' => 'approved']);
        }

        $this->getJson("/api/products/{$product->id}/comments")
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('total', 20)
            ->assertJsonPath('per_page', 15);

        $this->getJson("/api/products/{$product->id}/comments?page=2")
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    // ── Contract shapes ──────────────────────────────────────

    private function assertHasKeys(array $keys, $value): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $value);
        }
    }

    public function test_product_comment_listing_contract_shape(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $root = $this->comment($user, $product, ['rating' => 4, 'status' => 'approved']);
        $this->comment($user, $product, ['status' => 'approved', 'parent_id' => $root->id]);

        $response = $this->getJson("/api/products/{$product->id}/comments")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'body', 'rating', 'created_at',
                    'user' => ['id', 'name'], 'replies',
                ]],
                'current_page', 'last_page', 'per_page', 'total',
                'rating_summary' => ['average', 'count', 'distribution' => ['5', '4', '3', '2', '1']],
            ]);

        // comment and reply MUST share the exact same shape so Vue uses one model
        $this->assertSame(
            ['id', 'title', 'body', 'rating', 'created_at', 'user', 'replies'],
            array_keys($response->json('data.0'))
        );
        $this->assertSame(
            ['id', 'title', 'body', 'rating', 'created_at', 'user', 'replies'],
            array_keys($response->json('data.0.replies.0'))
        );
        $this->assertSame([], $response->json('data.0.replies.0.replies'));
    }

    public function test_article_comment_listing_contract_shape(): void
    {
        $user = $this->customer();
        $article = $this->article();
        $root = $this->comment($user, $article, ['status' => 'approved']);
        $this->comment($user, $article, ['status' => 'approved', 'parent_id' => $root->id]);

        $response = $this->getJson("/api/articles/{$article->id}/comments")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'body', 'created_at', 'user' => ['id', 'name'], 'replies']],
                'current_page', 'last_page', 'per_page', 'total',
            ]);

        $this->assertSame(
            ['id', 'title', 'body', 'created_at', 'user', 'replies'],
            array_keys($response->json('data.0'))
        );
        $this->assertSame(
            ['id', 'title', 'body', 'created_at', 'user', 'replies'],
            array_keys($response->json('data.0.replies.0'))
        );
        $response->assertJsonMissingPath('data.0.rating');
        $this->assertArrayNotHasKey('rating_summary', $response->json());
    }

    public function test_reply_creation_returns_listing_compatible_shape(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $root = $this->comment($user, $product);
        Sanctum::actingAs($user, ['customer']);

        $response = $this->postJson("/api/comments/{$root->id}/replies", ['body' => 'ok'])->assertCreated();

        $this->assertHasKeys(['id', 'title', 'body', 'rating', 'created_at', 'user', 'replies'], $response->json('data'));
        $this->assertArrayHasKey('id', $response->json('data.user'));
        $this->assertArrayHasKey('name', $response->json('data.user'));
        $this->assertSame([], $response->json('data.replies'));

        $user2 = $this->customer();
        $article = $this->article();
        $articleRoot = $this->comment($user2, $article);
        Sanctum::actingAs($user2, ['customer']);
        $this->postJson("/api/comments/{$articleRoot->id}/replies", ['body' => 'ok'])
            ->assertCreated()
            ->assertJsonMissingPath('data.rating');
    }

    public function test_admin_comment_listing_contract_shape(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin, ['admin']);
        $user = $this->customer();
        $this->comment($user, $this->product(), ['status' => 'pending']);

        $this->getJson('/api/admin/comments')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'body', 'status', 'rating',
                    'user' => ['id', 'name'],
                    'commentable_type', 'commentable_id',
                    'parent_id', 'created_at',
                ]],
            ]);
    }
}