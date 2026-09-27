<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        Sanctum::actingAs($user, ['customer']);
        return $user;
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Brake Pad', 'slug' => 'brake-'.fake()->uuid(),
            'sku' => fake()->uuid(), 'price' => '100.25', 'stock' => 10, 'is_active' => true,
        ], $overrides));
    }

    private function variant(Product $product, array $overrides = []): ProductVariant
    {
        return $product->variants()->create(array_merge([
            'sku' => fake()->uuid(), 'combination_key' => fake()->uuid(),
            'price' => '125.50', 'stock' => 5, 'is_active' => true,
        ], $overrides));
    }

    private function payload(array $items): array
    {
        return [
            'customer_name' => 'Test Customer', 'customer_phone' => '09123456789',
            'customer_email' => 'customer@example.com', 'shipping_address' => 'Test address 12',
            'shipping_postal_code' => '1234567890', 'shipping_city' => 'Tehran',
            'shipping_province' => 'Tehran', 'notes' => 'Call before delivery', 'items' => $items,
        ];
    }

    private function line(Product $product, int $quantity = 2, ?ProductVariant $variant = null): array
    {
        return ['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $quantity];
    }

    private function assertRejected(array $items, string $field): void
    {
        $this->postJson('/api/customer/checkout', $this->payload($items))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->postJson('/api/customer/checkout', [])->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_authenticated_customer_can_checkout_product_with_order_and_items(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $response = $this->postJson('/api/customer/checkout', $this->payload([$this->line($product)]))
            ->assertCreated()->assertJsonPath('order.user_id', $user->id)
            ->assertJsonPath('order.status', 'pending')->assertJsonPath('order.total', '200.50')
            ->assertJsonPath('order.subtotal', '200.50')->assertJsonPath('order.discount', '0.00')
            ->assertJsonPath('order.shipping_cost', '0.00')->assertJsonCount(1, 'order.items')
            ->assertJsonPath('order.items.0.product_name', $product->name)
            ->assertJsonPath('order.items.0.sku', $product->sku)
            ->assertJsonPath('order.items.0.quantity', 2)->assertJsonPath('order.items.0.unit_price', '100.25')
            ->assertJsonPath('order.items.0.subtotal', '200.50')
            ->assertJsonPath('order.customer_phone', '09123456789')
            ->assertJsonPath('order.shipping_address', 'Test address 12');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $order = Order::findOrFail($response->json('order.id'));
        $this->assertTrue($order->user->is($user));
        $this->assertTrue($user->orders->first()->is($order));
        $this->assertTrue($order->items->first()->product->is($product));
        $this->assertTrue($order->items->first()->order->is($order));
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_variant_checkout_uses_variant_price_and_stock(): void
    {
        $this->customer();
        $product = $this->product(['stock' => 0]);
        $variant = $this->variant($product);
        $this->postJson('/api/customer/checkout', $this->payload([$this->line($product, 2, $variant)]))
            ->assertCreated()->assertJsonPath('order.total', '251.00')
            ->assertJsonPath('order.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('order.items.0.sku', $variant->sku);
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertTrue(OrderItem::first()->productVariant->is($variant));
    }

    public function test_variant_null_price_falls_back_to_product_price(): void
    {
        $this->customer();
        $product = $this->product();
        $variant = $this->variant($product, ['price' => null]);
        $this->postJson('/api/customer/checkout', $this->payload([$this->line($product, 1, $variant)]))
            ->assertCreated()->assertJsonPath('order.total', '100.25');
    }

    public function test_variant_attributes_are_snapshotted_from_database(): void
    {
        $this->customer();
        $product = $this->product();
        $variant = $this->variant($product);
        $attribute = \App\Models\Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'select']);
        $value = \App\Models\AttributeValue::create([
            'attribute_id' => $attribute->id, 'label' => 'Black', 'value' => 'black',
        ]);
        $variant->attributeValues()->attach($value->id);
        $this->postJson('/api/customer/checkout', $this->payload([$this->line($product, 1, $variant)]))
            ->assertCreated()->assertJsonPath('order.items.0.attributes.0.label', 'Black');
        $value->update(['label' => 'Changed']);
        $product->update(['name' => 'Changed']);
        $this->assertSame('Black', OrderItem::first()->attributes[0]['label']);
        $this->assertSame('Brake Pad', OrderItem::first()->product_name);
    }

    public function test_order_amount_overflow_is_rejected(): void
    {
        $this->customer();
        $product = $this->product(['price' => '100000000.00', 'stock' => 100000]);
        $this->assertRejected([$this->line($product, 100000)], 'items');
    }

    public function test_invalid_product_is_rejected(): void
    {
        $this->customer();
        $this->assertRejected([['product_id' => 999999, 'quantity' => 1]], 'items.0.product_id');
    }

    public function test_inactive_product_is_rejected(): void
    {
        $this->customer();
        $this->assertRejected([$this->line($this->product(['is_active' => false]))], 'items.0.product_id');
    }

    public function test_invalid_variant_is_rejected(): void
    {
        $this->customer();
        $line = $this->line($this->product());
        $line['variant_id'] = 999999;
        $this->assertRejected([$line], 'items.0.variant_id');
    }

    public function test_variant_of_another_product_is_rejected(): void
    {
        $this->customer();
        $product = $this->product();
        $variant = $this->variant($this->product());
        $this->assertRejected([$this->line($product, 1, $variant)], 'items.0.variant_id');
    }

    public function test_inactive_variant_is_rejected(): void
    {
        $this->customer();
        $product = $this->product();
        $variant = $this->variant($product, ['is_active' => false]);
        $this->assertRejected([$this->line($product, 1, $variant)], 'items.0.variant_id');
    }

    public function test_insufficient_product_stock_is_rejected(): void
    {
        $this->customer();
        $this->assertRejected([$this->line($this->product(['stock' => 1]))], 'items.0.quantity');
    }

    public function test_insufficient_variant_stock_is_rejected(): void
    {
        $this->customer();
        $product = $this->product();
        $variant = $this->variant($product, ['stock' => 1]);
        $this->assertRejected([$this->line($product, 2, $variant)], 'items.0.quantity');
    }

    public function test_frontend_prices_totals_identity_and_status_are_ignored(): void
    {
        $other = User::factory()->create();
        $user = $this->customer();
        $product = $this->product();
        $payload = $this->payload([array_merge($this->line($product), [
            'price' => 1, 'unit_price' => 1, 'subtotal' => 1, 'stock' => 9999,
            'product_name' => 'Fake', 'attributes' => ['fake'],
        ])]);
        $payload = array_merge($payload, [
            'total' => 1, 'subtotal' => 1, 'discount' => 999, 'shipping_cost' => 999,
            'status' => 'confirmed', 'user_id' => $other->id,
        ]);
        $this->postJson('/api/customer/checkout', $payload)->assertCreated()
            ->assertJsonPath('order.user_id', $user->id)->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.total', '200.50')->assertJsonPath('order.discount', '0.00')
            ->assertJsonPath('order.shipping_cost', '0.00')
            ->assertJsonPath('order.items.0.product_name', $product->name)
            ->assertJsonPath('order.items.0.attributes', null);
    }

    public function test_multiple_cart_items_have_exact_total(): void
    {
        $this->customer();
        $first = $this->product(['price' => '0.10']);
        $second = $this->product(['price' => '0.20']);
        $this->postJson('/api/customer/checkout', $this->payload([$this->line($first, 3), $this->line($second, 1)]))
            ->assertCreated()->assertJsonCount(2, 'order.items')->assertJsonPath('order.total', '0.50');
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_empty_items_are_rejected(): void
    {
        $this->customer();
        $this->assertRejected([], 'items');
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        $this->customer();
        $product = $this->product();
        foreach ([0, -1, 1.5, 'invalid', null, 100001] as $quantity) {
            $this->assertRejected([['product_id' => $product->id, 'quantity' => $quantity]], 'items.0.quantity');
        }
    }

    public function test_invalid_later_item_leaves_no_partial_order(): void
    {
        $this->customer();
        $product = $this->product();
        $this->assertRejected([$this->line($product), ['product_id' => 999999, 'quantity' => 1]], 'items.1.product_id');
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_duplicate_lines_cannot_bypass_stock(): void
    {
        $this->customer();
        $product = $this->product(['stock' => 3]);
        $this->assertRejected([$this->line($product), $this->line($product)], 'items.0.quantity');
    }

    public function test_duplicate_lines_are_combined(): void
    {
        $this->customer();
        $product = $this->product();
        $this->postJson('/api/customer/checkout', $this->payload([$this->line($product), $this->line($product)]))
            ->assertCreated()->assertJsonCount(1, 'order.items')
            ->assertJsonPath('order.items.0.quantity', 4)->assertJsonPath('order.total', '401.00');
    }

    public function test_customer_and_shipping_fields_are_required(): void
    {
        $this->customer();
        $this->postJson('/api/customer/checkout', ['items' => [$this->line($this->product())]])
            ->assertUnprocessable()->assertJsonValidationErrors([
                'customer_name', 'customer_phone', 'customer_email', 'shipping_address',
                'shipping_postal_code', 'shipping_city', 'shipping_province',
            ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_insert_failure_rolls_back_order_and_earlier_items(): void
    {
        $this->customer();
        $first = $this->product();
        $second = $this->product();
        OrderItem::creating(function ($item) use ($second) {
            if ($item->product_id === $second->id) {
                throw new \RuntimeException('Simulated item insert failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/customer/checkout', $this->payload([$this->line($first), $this->line($second)]));
            $this->fail('Expected an insert failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated item insert failure', $exception->getMessage());
        } finally {
            OrderItem::flushEventListeners();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_checkout_does_not_decrement_stock(): void
    {
        $this->customer();
        $product = $this->product(['stock' => 10]);
        $variant = $this->variant($product, ['stock' => 5]);
        $this->postJson('/api/customer/checkout', $this->payload([$this->line($product, 2, $variant)]))
            ->assertCreated();
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_checkout_and_success_pages_have_spa_routes(): void
    {
        $this->get('/checkout')->assertOk()->assertViewIs('welcome');
        $this->get('/order-success')->assertOk()->assertViewIs('welcome');
    }
}
