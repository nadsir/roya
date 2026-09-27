<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function order(User $user, array $overrides = []): Order
    {
        return $user->orders()->create(array_merge([
            'status' => 'pending', 'subtotal' => '200.50', 'discount' => '10.00',
            'shipping_cost' => '20.00', 'total' => '210.50',
            'customer_name' => 'Recipient', 'customer_phone' => '09123456789',
            'customer_email' => 'recipient@example.com', 'shipping_address' => 'Street 12',
            'shipping_postal_code' => '1234567890', 'shipping_city' => 'Tehran',
            'shipping_province' => 'Tehran', 'notes' => 'Call first',
        ], $overrides));
    }

    private function item(Order $order, array $overrides = [])
    {
        return $order->items()->create(array_merge([
            'product_id' => null, 'product_variant_id' => null, 'product_name' => 'Original product',
            'sku' => 'ORIGINAL-SKU', 'quantity' => 2, 'unit_price' => '100.25', 'subtotal' => '200.50',
            'attributes' => [['attribute' => 'Color', 'slug' => 'color', 'label' => 'Black', 'value' => 'black']],
        ], $overrides));
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Current product', 'slug' => fake()->uuid(),
            'price' => '999.00', 'stock' => 10, 'is_active' => true,
        ], $overrides));
    }

    private function variant(Product $product, array $overrides = []): ProductVariant
    {
        return $product->variants()->create(array_merge([
            'sku' => fake()->uuid(), 'combination_key' => fake()->uuid(),
            'price' => '125.50', 'stock' => 5, 'is_active' => true,
        ], $overrides));
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/customer/orders')->assertUnauthorized();
    }

    public function test_guest_cannot_view_order(): void
    {
        $order = $this->order($this->customer());
        $this->getJson("/api/customer/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_customer_lists_only_own_orders_with_counts(): void
    {
        $user = $this->customer();
        $own = $this->order($user);
        $other = $this->order($this->customer());
        $this->item($own);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.total', '210.50')->assertJsonPath('data.0.status', 'pending')
            ->assertJsonMissing(['id' => $other->id])->assertJsonMissingPath('data.0.customer_phone');
    }

    public function test_orders_are_sorted_by_date_then_id_newest_first(): void
    {
        $user = $this->customer();
        $newest = $this->order($user);
        $older = $this->order($user);
        $older->forceFill(['created_at' => $newest->created_at->copy()->subDay()])->save();
        $tie = $this->order($user);
        $tie->forceFill(['created_at' => $newest->created_at])->save();
        Sanctum::actingAs($user, ['customer']);
        $this->getJson('/api/customer/orders')->assertOk()
            ->assertJsonPath('data.0.id', $tie->id)->assertJsonPath('data.1.id', $newest->id)
            ->assertJsonPath('data.2.id', $older->id);
    }

    public function test_customer_can_view_own_order_and_items(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $item = $this->item($order);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.id', $order->id)->assertJsonPath('order.status', 'pending')
            ->assertJsonCount(1, 'order.items')->assertJsonPath('order.items.0.id', $item->id)
            ->assertJsonPath('order.items.0.product_name', 'Original product')
            ->assertJsonPath('order.items.0.quantity', 2)->assertJsonPath('order.items.0.unit_price', '100.25')
            ->assertJsonPath('order.items.0.subtotal', '200.50')
            ->assertJsonPath('order.items.0.attributes.0.label', 'Black')
            ->assertJsonStructure(['order' => ['created_at']]);
    }

    public function test_customer_cannot_view_another_users_order_even_with_query_override(): void
    {
        $owner = $this->customer();
        $order = $this->order($owner);
        $this->item($order);
        Sanctum::actingAs($this->customer(), ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}?user_id={$owner->id}")
            ->assertNotFound()->assertJsonMissingPath('order')
            ->assertDontSee('recipient@example.com')->assertDontSee('Original product');
    }

    public function test_nonexistent_order_returns_not_found(): void
    {
        Sanctum::actingAs($this->customer(), ['customer']);
        $this->getJson('/api/customer/orders/999999')->assertNotFound();
    }

    public function test_shipping_information_is_returned(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.customer_name', 'Recipient')
            ->assertJsonPath('order.customer_phone', '09123456789')
            ->assertJsonPath('order.customer_email', 'recipient@example.com')
            ->assertJsonPath('order.shipping_address', 'Street 12')
            ->assertJsonPath('order.shipping_postal_code', '1234567890')
            ->assertJsonPath('order.shipping_city', 'Tehran')
            ->assertJsonPath('order.shipping_province', 'Tehran')
            ->assertJsonPath('order.notes', 'Call first');
    }

    public function test_financial_totals_are_returned(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.subtotal', '200.50')->assertJsonPath('order.discount', '10.00')
            ->assertJsonPath('order.shipping_cost', '20.00')->assertJsonPath('order.total', '210.50');
    }

    public function test_available_product_variant_and_primary_image_are_returned_without_replacing_snapshot(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $product = $this->product();
        $variant = $product->variants()->create([
            'combination_key' => 'black', 'sku' => 'NEW-SKU', 'price' => 555, 'stock' => 5, 'is_active' => true,
        ]);
        $product->images()->create(['path' => 'secondary.jpg', 'sort_order' => 0, 'is_primary' => false]);
        $product->images()->create(['path' => 'primary.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $this->item($order, ['product_id' => $product->id, 'product_variant_id' => $variant->id]);
        $product->update(['is_active' => false]);
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.items.0.product_id', $product->id)
            ->assertJsonPath('order.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('order.items.0.image', 'primary.jpg')
            ->assertJsonPath('order.items.0.product_name', 'Original product')
            ->assertJsonPath('order.items.0.sku', 'ORIGINAL-SKU')
            ->assertJsonPath('order.items.0.unit_price', '100.25');
    }

    public function test_snapshot_remains_after_product_and_variant_are_deleted(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $product = $this->product();
        $variant = $product->variants()->create(['combination_key' => 'black', 'stock' => 5]);
        $this->item($order, ['product_id' => $product->id, 'product_variant_id' => $variant->id]);
        $product->delete();
        Sanctum::actingAs($user, ['customer']);
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.items.0.product_id', null)
            ->assertJsonPath('order.items.0.product_variant_id', null)
            ->assertJsonPath('order.items.0.image', null)
            ->assertJsonPath('order.items.0.product_name', 'Original product')
            ->assertJsonPath('order.items.0.sku', 'ORIGINAL-SKU')
            ->assertJsonPath('order.items.0.attributes.0.label', 'Black');
    }

    public function test_orders_list_is_paginated_and_empty_list_is_valid(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user, ['customer']);
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(0, 'data');
        for ($i = 0; $i < 21; $i++) $this->order($user);
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 21);
        $this->getJson('/api/customer/orders?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_images_are_eager_loaded_for_multiple_items(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        for ($i = 0; $i < 4; $i++) {
            $product = $this->product();
            $product->images()->create(['path' => "product-{$i}.jpg", 'is_primary' => false]);
            $this->item($order, ['product_id' => $product->id]);
        }
        Sanctum::actingAs($user, ['customer']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()->assertJsonCount(4, 'order.items')
            ->assertJsonPath('order.items.0.image', 'product-0.jpg');
        $imageQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'product_images'));
        DB::disableQueryLog();
        $this->assertCount(1, $imageQueries);
    }

    public function test_orders_pages_resolve_to_vue_entry_point(): void
    {
        $this->get('/orders')->assertOk()->assertViewIs('welcome');
        $this->get('/orders/123')->assertOk()->assertViewIs('welcome');
    }

    // ── Cancellation tests ──────────────────────────────────────

    public function test_customer_can_cancel_own_pending_order(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        Sanctum::actingAs($user, ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonStructure(['order' => ['cancelled_at']]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_records_reason(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        Sanctum::actingAs($user, ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel", [
            'reason' => 'Changed my mind',
        ])->assertOk()->assertJsonPath('order.cancelled_reason', 'Changed my mind');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'cancelled_reason' => 'Changed my mind',
        ]);
    }

    public function test_customer_cannot_cancel_another_users_order(): void
    {
        $owner = $this->customer();
        $order = $this->order($owner, ['status' => 'pending']);
        Sanctum::actingAs($this->customer(), ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel")->assertNotFound();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_processing_order_cannot_be_cancelled(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'processing']);
        Sanctum::actingAs($user, ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel")->assertStatus(409);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
    }

    public function test_shipped_order_cannot_be_cancelled(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'shipped']);
        Sanctum::actingAs($user, ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel")->assertStatus(409);
    }

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'delivered']);
        Sanctum::actingAs($user, ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel")->assertStatus(409);
    }

    public function test_cancelled_order_cannot_be_cancelled_again(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'cancelled']);
        Sanctum::actingAs($user, ['customer']);

        $this->patchJson("/api/customer/orders/{$order->id}/cancel")->assertStatus(409);
    }

    public function test_show_includes_new_fields(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        Sanctum::actingAs($user, ['customer']);

        $this->getJson("/api/customer/orders/{$order->id}")->assertOk()
            ->assertJsonStructure([
                'order' => [
                    'paid_at', 'payment_method', 'payment_ref',
                    'cancelled_at', 'cancelled_reason',
                ],
            ]);
    }

    // ── Status transition tests (unit-level on model) ───────────

    public function test_status_transitions(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);

        $order->markConfirmed();
        $this->assertSame('confirmed', $order->fresh()->status);

        $order->markProcessing();
        $this->assertSame('processing', $order->fresh()->status);

        $order->markShipped();
        $this->assertSame('shipped', $order->fresh()->status);

        $order->markDelivered();
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_invalid_transition_throws(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'delivered']);

        $this->expectException(\LogicException::class);
        $order->markProcessing();
    }

    public function test_cancelled_cannot_transition(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'cancelled']);

        $this->expectException(\LogicException::class);
        $order->markConfirmed();
    }

    // ── markPaidAndDecrementStock tests ─────────────────────────

    public function test_mark_paid_and_decrement_stock_success(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $order->markPaidAndDecrementStock('zarinpal', 'ref-123');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('zarinpal', $order->fresh()->payment_method);
        $this->assertSame('ref-123', $order->fresh()->payment_ref);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_mark_paid_prevents_duplicate_payment(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 2,
            'unit_price' => $product->price, 'subtotal' => '1998.00',
        ]);

        $order->markPaidAndDecrementStock('zarinpal', 'ref-1');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only pending orders can be marked as paid');
        $order->markPaidAndDecrementStock('zarinpal', 'ref-2');
    }

    public function test_mark_paid_stock_failure_rolls_back_payment(): void
    {
        $product = $this->product(['stock' => 1]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 5,
            'unit_price' => $product->price, 'subtotal' => '4995.00',
        ]);

        try {
            $order->markPaidAndDecrementStock('zarinpal', 'ref-fail');
        } catch (\RuntimeException $e) {
            // expected: insufficient stock
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertNull($order->fresh()->payment_method);
        $this->assertNull($order->fresh()->payment_ref);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_mark_paid_variant_stock_failure_rolls_back(): void
    {
        $product = $this->product(['stock' => 0]);
        $variant = $this->variant($product, ['stock' => 1]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name, 'sku' => $variant->sku,
            'quantity' => 3, 'unit_price' => $variant->price, 'subtotal' => '376.50',
        ]);

        try {
            $order->markPaidAndDecrementStock('zarinpal', 'ref-fail');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(1, $variant->fresh()->stock);
    }

    public function test_mark_paid_multi_item_rollback_on_partial_stock_failure(): void
    {
        $productA = $this->product(['stock' => 10]);
        $productB = $this->product(['stock' => 1]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        $order->items()->create([
            'product_id' => $productA->id, 'product_name' => $productA->name,
            'sku' => $productA->sku, 'quantity' => 2,
            'unit_price' => $productA->price, 'subtotal' => '1998.00',
        ]);
        $order->items()->create([
            'product_id' => $productB->id, 'product_name' => $productB->name,
            'sku' => $productB->sku, 'quantity' => 5,
            'unit_price' => $productB->price, 'subtotal' => '4995.00',
        ]);

        try {
            $order->markPaidAndDecrementStock('zarinpal', 'ref-fail');
        } catch (\RuntimeException $e) {
            // expected: productB insufficient
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(10, $productA->fresh()->stock);
        $this->assertSame(1, $productB->fresh()->stock);
    }

    public function test_mark_paid_non_pending_order_throws(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only pending orders can be marked as paid');
        $order->markPaidAndDecrementStock('zarinpal', 'ref');
    }

    public function test_mark_paid_cancelled_order_throws(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'cancelled']);

        $this->expectException(\LogicException::class);
        $order->markPaidAndDecrementStock('zarinpal', 'ref');
    }

    // ── Inventory decrement tests ────────────────────────────────

    public function test_decrement_stock_for_product(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        Order::decrementStockForOrder($order);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_decrement_stock_for_variant(): void
    {
        $product = $this->product(['stock' => 0]);
        $variant = $this->variant($product, ['stock' => 8]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name, 'sku' => $variant->sku,
            'quantity' => 2, 'unit_price' => $variant->price, 'subtotal' => '251.00',
        ]);

        Order::decrementStockForOrder($order);
        $this->assertSame(6, $variant->fresh()->stock);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_decrement_stock_insufficient_throws(): void
    {
        $product = $this->product(['stock' => 1]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 5,
            'unit_price' => $product->price, 'subtotal' => '4995.00',
        ]);

        $this->expectException(\RuntimeException::class);
        Order::decrementStockForOrder($order);
    }

    public function test_decrement_stock_variant_insufficient_throws(): void
    {
        $product = $this->product(['stock' => 0]);
        $variant = $this->variant($product, ['stock' => 1]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name, 'sku' => $variant->sku,
            'quantity' => 3, 'unit_price' => $variant->price, 'subtotal' => '376.50',
        ]);

        $this->expectException(\RuntimeException::class);
        Order::decrementStockForOrder($order);
    }

    public function test_decrement_stock_non_confirmed_throws(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'pending']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 1,
            'unit_price' => $product->price, 'subtotal' => '999.00',
        ]);

        $this->expectException(\LogicException::class);
        Order::decrementStockForOrder($order);
    }

    public function test_decrement_stock_never_goes_negative(): void
    {
        $product = $this->product(['stock' => 2]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        try {
            Order::decrementStockForOrder($order);
        } catch (\RuntimeException $e) {
            // expected
        }
        $this->assertGreaterThanOrEqual(0, $product->fresh()->stock);
    }

    public function test_decrement_stock_rollback_on_partial_failure(): void
    {
        $productA = $this->product(['stock' => 10]);
        $productB = $this->product(['stock' => 1]);
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $productA->id, 'product_name' => $productA->name,
            'sku' => $productA->sku, 'quantity' => 2,
            'unit_price' => $productA->price, 'subtotal' => '1998.00',
        ]);
        $order->items()->create([
            'product_id' => $productB->id, 'product_name' => $productB->name,
            'sku' => $productB->sku, 'quantity' => 5,
            'unit_price' => $productB->price, 'subtotal' => '4995.00',
        ]);

        try {
            DB::transaction(fn () => Order::decrementStockForOrder($order));
        } catch (\RuntimeException $e) {
            // expected – stockB insufficient
        }
        $this->assertSame(10, $productA->fresh()->stock);
        $this->assertSame(1, $productB->fresh()->stock);
    }
}
