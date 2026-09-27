<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerWishlistTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function product(bool $active = true): Product
    {
        return Product::create([
            'name' => 'Brake Pad',
            'slug' => 'brake-pad',
            'sku' => 'BRAKE-001',
            'price' => 100,
            'stock' => 5,
            'is_active' => $active,
        ]);
    }

    public function test_guest_cannot_access_any_wishlist_endpoint(): void
    {
        $this->getJson('/api/customer/wishlist')->assertUnauthorized();
        $this->postJson('/api/customer/wishlist', ['product_id' => 1])->assertUnauthorized();
        $this->deleteJson('/api/customer/wishlist/1')->assertUnauthorized();
        $this->getJson('/api/customer/wishlist/check/1')->assertUnauthorized();
    }

    public function test_customer_can_read_wishlist_with_product_details(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $image = $product->images()->create([
            'path' => 'products/brake.jpg', 'is_primary' => true, 'sort_order' => 0,
        ]);
        $item = $user->wishlistItems()->create(['product_id' => $product->id]);
        Sanctum::actingAs($user, ['customer']);

        $this->getJson('/api/customer/wishlist')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.product.id', $product->id)
            ->assertJsonPath('data.0.product.name', 'Brake Pad')
            ->assertJsonPath('data.0.product.slug', 'brake-pad')
            ->assertJsonPath('data.0.product.price', 100)
            ->assertJsonPath('data.0.product.stock', 5)
            ->assertJsonPath('data.0.product.in_stock', true)
            ->assertJsonPath('data.0.product.images.0.id', $image->id)
            ->assertJsonPath('data.0.product.images.0.is_primary', true);
    }

    public function test_customer_can_add_product_using_bearer_token(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $token = $user->createToken('customer-dashboard', ['customer'])->plainTextToken;

        $this->withToken($token)->postJson('/api/customer/wishlist', ['product_id' => $product->id])
            ->assertCreated()->assertJsonPath('in_wishlist', true);
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_adding_same_product_twice_does_not_duplicate(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        $product = $this->product();
        $first = $this->postJson('/api/customer/wishlist', ['product_id' => $product->id])->assertCreated();
        $this->postJson('/api/customer/wishlist', ['product_id' => $product->id])->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('wishlist_items', 1);
    }

    public function test_customer_can_remove_product(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $user->wishlistItems()->create(['product_id' => $product->id]);
        Sanctum::actingAs($user, ['customer']);

        $this->deleteJson("/api/customer/wishlist/{$product->id}")->assertNoContent();
        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_removing_absent_product_is_idempotent(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        $product = $this->product();
        $this->deleteJson("/api/customer/wishlist/{$product->id}")->assertNoContent();
        $this->deleteJson('/api/customer/wishlist/999999')->assertNoContent();
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_check_returns_true_for_saved_product(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $user->wishlistItems()->create(['product_id' => $product->id]);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/wishlist/check/{$product->id}")
            ->assertOk()->assertExactJson(['in_wishlist' => true]);
    }

    public function test_check_returns_false_for_absent_product(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        $product = $this->product();
        $this->getJson("/api/customer/wishlist/check/{$product->id}")
            ->assertOk()->assertExactJson(['in_wishlist' => false]);
        $this->getJson('/api/customer/wishlist/check/999999')
            ->assertOk()->assertExactJson(['in_wishlist' => false]);
    }

    public function test_customer_cannot_read_check_or_remove_another_customers_items(): void
    {
        $userA = $this->customer();
        $userB = $this->customer();
        $product = $this->product();
        $userB->wishlistItems()->create(['product_id' => $product->id]);
        Sanctum::actingAs($userA, ['customer']);

        $this->getJson('/api/customer/wishlist')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/customer/wishlist/check/{$product->id}")
            ->assertOk()->assertExactJson(['in_wishlist' => false]);
        $this->deleteJson("/api/customer/wishlist/{$product->id}")->assertNoContent();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $userB->id, 'product_id' => $product->id]);

        $this->postJson('/api/customer/wishlist', ['product_id' => $product->id, 'user_id' => $userB->id])
            ->assertCreated();
        $this->assertDatabaseCount('wishlist_items', 2);
        $this->deleteJson("/api/customer/wishlist/{$product->id}")->assertNoContent();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $userB->id, 'product_id' => $product->id]);
        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $userA->id, 'product_id' => $product->id]);
    }

    public function test_invalid_or_missing_product_cannot_be_added(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        foreach ([[], ['product_id' => 999999], ['product_id' => 'invalid']] as $payload) {
            $this->postJson('/api/customer/wishlist', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        }
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_inactive_product_cannot_be_added(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        $product = $this->product(false);
        $this->postJson('/api/customer/wishlist', ['product_id' => $product->id])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('wishlist_items', 0);
    }
}
