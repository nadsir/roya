<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin, ['admin']);
        return $admin;
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'slug' => fake()->uuid(),
            'price' => '100.00',
            'stock' => 10,
            'is_active' => true,
        ], $overrides));
    }

    private function order(User $user, array $overrides = []): Order
    {
        return $user->orders()->create(array_merge([
            'status' => 'pending',
            'subtotal' => '200.00',
            'discount' => '0.00',
            'shipping_cost' => '0.00',
            'total' => '200.00',
            'customer_name' => 'Test Customer',
            'customer_phone' => '09123456789',
            'customer_email' => 'customer@example.com',
            'shipping_address' => 'Test Address',
            'shipping_postal_code' => '1234567890',
            'shipping_city' => 'Tehran',
            'shipping_province' => 'Tehran',
        ], $overrides));
    }

    private function orderItem(Order $order, array $overrides = []): OrderItem
    {
        return $order->items()->create(array_merge([
            'product_id' => null,
            'product_variant_id' => null,
            'product_name' => 'Test Product',
            'sku' => 'TEST-SKU',
            'quantity' => 2,
            'unit_price' => '100.00',
            'subtotal' => '200.00',
            'attributes' => null,
        ], $overrides));
    }

    private function paymentAttempt(Order $order, array $overrides = []): PaymentAttempt
    {
        return $order->paymentAttempts()->create(array_merge([
            'gateway' => 'AghayePardakhtGateway',
            'authority' => 'test-authority-' . fake()->uuid(),
            'amount' => 20000,
            'status' => 'verified',
            'reference' => 'ref-123',
            'verified_at' => now(),
        ], $overrides));
    }

    // ── Auth / Authorization ──────────────────────────────────────

    public function test_guest_cannot_list_admin_orders(): void
    {
        $this->getJson('/api/admin/orders')->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_orders(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer, ['customer']);
        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_admin_can_list_orders(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order1 = $this->order($customer, ['status' => 'pending', 'total' => '100.00']);
        $order2 = $this->order($customer, ['status' => 'confirmed', 'total' => '200.00']);

        $response = $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $order2->id) // newest first
            ->assertJsonPath('data.1.id', $order1->id);

        $this->assertArrayHasKey('customer', $response->json('data.0'));
        $this->assertArrayHasKey('items_count', $response->json('data.0'));
    }

    public function test_admin_orders_list_is_paginated(): void
    {
        $this->admin();
        $customer = $this->customer();
        for ($i = 0; $i < 25; $i++) {
            $this->order($customer);
        }

        $response = $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 25);

        $this->getJson('/api/admin/orders?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_admin_orders_filter_by_status(): void
    {
        $this->admin();
        $customer = $this->customer();
        $this->order($customer, ['status' => 'pending']);
        $this->order($customer, ['status' => 'confirmed']);
        $this->order($customer, ['status' => 'processing']);

        $this->getJson('/api/admin/orders?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');

        $this->getJson('/api/admin/orders?status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_admin_orders_search_by_customer_name(): void
    {
        $this->admin();
        $customer = $this->customer();
        $this->order($customer, ['customer_name' => 'UNIQUE-SEARCH-ALI-AHMADI']);
        $this->order($customer, ['customer_name' => 'Reza Mohammadi']);

        $this->getJson('/api/admin/orders?search=UNIQUE-SEARCH-ALI')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.name', $customer->name);
    }

    // ── Show ──────────────────────────────────────────────────────

    public function test_guest_cannot_view_admin_order(): void
    {
        $order = $this->order($this->customer());
        $this->getJson("/api/admin/orders/{$order->id}")->assertUnauthorized();
    }

    public function test_customer_cannot_view_admin_order(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer, ['customer']);
        $order = $this->order($this->customer());
        $this->getJson("/api/admin/orders/{$order->id}")->assertForbidden();
    }

    public function test_admin_can_view_order_with_full_details(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending']);
        $this->orderItem($order);
        $this->paymentAttempt($order);

        $response = $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.total', '200.00')
            ->assertJsonPath('order.customer_name', 'Test Customer')
            ->assertJsonPath('order.customer_phone', '09123456789')
            ->assertJsonPath('order.paid_at', null)
            ->assertJsonPath('order.payment_method', null)
            ->assertJsonPath('order.payment_ref', null);

        // Items
        $response->assertJsonPath('order.items.0.id', $order->items->first()->id);
        $response->assertJsonPath('order.items.0.product_name', 'Test Product');
        $response->assertJsonPath('order.items.0.quantity', 2);
        $response->assertJsonPath('order.items.0.unit_price', '100.00');
        $response->assertJsonPath('order.items.0.subtotal', '200.00');

        // Payment attempts
        $response->assertJsonPath('order.payment_attempts.0.gateway', 'AghayePardakhtGateway');
        $response->assertJsonPath('order.payment_attempts.0.status', 'verified');
    }

    public function test_admin_nonexistent_order_returns_not_found(): void
    {
        $this->admin();
        $this->getJson('/api/admin/orders/999999')->assertNotFound();
    }

    // ── Update Status ─────────────────────────────────────────────

    public function test_guest_cannot_update_admin_order_status(): void
    {
        $order = $this->order($this->customer());
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertUnauthorized();
    }

    public function test_customer_cannot_update_admin_order_status(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($customer, ['customer']);
        $order = $this->order($this->customer());
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();
    }

    public function test_admin_can_transition_pending_to_confirmed(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending']);
        $originalPaidAt = $order->paid_at;
        $originalPaymentRef = $order->payment_ref;

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('message', 'وضعیت سفارش با موفقیت به‌روزرسانی شد.')
            ->assertJsonPath('order.status', 'confirmed');

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame($originalPaidAt, $order->paid_at); // unchanged
        $this->assertSame($originalPaymentRef, $order->payment_ref); // unchanged
    }

    public function test_admin_can_transition_confirmed_to_processing(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'confirmed']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('order.status', 'processing');

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_admin_can_transition_processing_to_shipped(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'processing']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertOk()
            ->assertJsonPath('order.status', 'shipped');

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_admin_can_transition_shipped_to_delivered(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'shipped']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('order.status', 'delivered');

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_admin_can_cancel_pending_order(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
            'cancelled_reason' => 'Customer requested',
        ])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('Customer requested', $order->cancelled_reason);
    }

    public function test_admin_can_cancel_confirmed_order(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'confirmed']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_admin_cannot_transition_pending_to_shipped(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_admin_cannot_transition_pending_to_delivered(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_admin_cannot_transition_confirmed_to_pending(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'confirmed']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_admin_cannot_transition_delivered_to_any(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'delivered']);

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'cancelled'] as $status) {
            $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_admin_cannot_transition_cancelled_to_any(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'cancelled']);

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_status_validation_rejects_invalid_status(): void
    {
        $this->admin();
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending']);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'invalid-status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_status_does_not_change_paid_at_payment_ref_or_stock(): void
    {
        $this->admin();
        $customer = $this->customer();
        $product = $this->product(['stock' => 10]);
        $order = $this->order($customer, ['status' => 'pending', 'total' => '200.00']);
        $this->orderItem($order, ['product_id' => $product->id, 'quantity' => 2]);

        // First pay the order (this would decrement stock)
        $order->markPaidAndDecrementStock('online', 'ref-123');
        $this->assertSame(8, $product->fresh()->stock);

        $paidAt = $order->paid_at;
        $paymentRef = $order->payment_ref;

        // Now admin changes status from confirmed to processing
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk();

        $order->refresh();
        $product->refresh();

        $this->assertSame('processing', $order->status);
        $this->assertEquals($paidAt, $order->paid_at); // unchanged
        $this->assertSame($paymentRef, $order->payment_ref); // unchanged
        $this->assertSame(8, $product->stock); // stock unchanged
    }

    public function test_cancelled_order_does_not_restore_stock(): void
    {
        $this->admin();
        $customer = $this->customer();
        $product = $this->product(['stock' => 10]);
        $order = $this->order($customer, ['status' => 'pending', 'total' => '200.00']);
        $this->orderItem($order, ['product_id' => $product->id, 'quantity' => 2]);

        // Pay first to confirm (this decrements stock)
        $order->markPaidAndDecrementStock('online', 'ref-123');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);

        // Admin cancels the confirmed order - stock should NOT be restored
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock); // stock NOT restored
    }
}