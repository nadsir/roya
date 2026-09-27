<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\Payment\FakePaymentGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $fakeGateway;
    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeGateway = new FakePaymentGateway();
        $this->app->instance(\App\Contracts\PaymentGatewayInterface::class, $this->fakeGateway);
        $this->paymentService = app(PaymentService::class);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function order(User $user, array $overrides = []): Order
    {
        return $user->orders()->create(array_merge([
            'status' => 'pending', 'subtotal' => '200.50', 'discount' => '0.00',
            'shipping_cost' => '0.00', 'total' => '200.50',
            'customer_name' => 'Recipient', 'customer_phone' => '09123456789',
            'customer_email' => 'recipient@example.com', 'shipping_address' => 'Street 12',
            'shipping_postal_code' => '1234567890', 'shipping_city' => 'Tehran',
            'shipping_province' => 'Tehran',
        ], $overrides));
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test product', 'slug' => fake()->uuid(),
            'price' => '999.00', 'stock' => 10, 'is_active' => true,
        ], $overrides));
    }

    private function registerAttempt(Order $order, string $authority, ?int $amount = null): PaymentAttempt
    {
        return $order->paymentAttempts()->create([
            'gateway'   => 'FakePaymentGateway',
            'authority' => $authority,
            'amount'    => $amount ?? (int) round((float) $order->total),
            'status'    => PaymentAttempt::STATUS_INITIATED,
        ]);
    }

    // ── Initiate tests ──────────────────────────────────────────

    public function test_initiate_creates_payment_attempt(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $result = $this->paymentService->initiate($order, 'https://example.com/callback');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->authority);
        $this->assertNotNull($result->paymentUrl);

        $attempt = $order->paymentAttempts()->first();
        $this->assertNotNull($attempt);
        $this->assertSame($order->id, $attempt->order_id);
        $this->assertSame('FakePaymentGateway', $attempt->gateway);
        $this->assertSame($result->authority, $attempt->authority);
        $this->assertSame(201, $attempt->amount);
        $this->assertSame(PaymentAttempt::STATUS_INITIATED, $attempt->status);
    }

    public function test_initiate_failure_attempt_is_failed_order_pending(): void
    {
        $this->fakeGateway->failInitiate();
        $user = $this->customer();
        $order = $this->order($user);

        $result = $this->paymentService->initiate($order, 'https://example.com/callback');

        $this->assertFalse($result->success);
        $this->assertNull($result->authority);
        $this->assertSame('pending', $order->fresh()->status);

        $attempt = $order->paymentAttempts()->first();
        $this->assertNotNull($attempt);
        $this->assertSame(PaymentAttempt::STATUS_FAILED, $attempt->status);
        $this->assertNull($attempt->authority);
    }

    public function test_initiate_exception_order_pending_attempt_pending(): void
    {
        $this->fakeGateway->throwOnInitiate();
        $user = $this->customer();
        $order = $this->order($user);

        try {
            $this->paymentService->initiate($order, 'https://example.com/callback');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Gateway connection failed', $e->getMessage());
        }

        $this->assertSame('pending', $order->fresh()->status);

        $attempt = $order->paymentAttempts()->first();
        $this->assertNotNull($attempt);
        $this->assertSame(PaymentAttempt::STATUS_PENDING, $attempt->status);
        $this->assertNull($attempt->authority);
    }

    public function test_confirmed_order_cannot_initiate_payment(): void
    {
        $user = $this->customer();
        $order = $this->order($user, ['status' => 'confirmed']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only pending orders can initiate payment');
        $this->paymentService->initiate($order, 'https://example.com/callback');
    }

    public function test_each_initiate_creates_new_attempt(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $this->paymentService->initiate($order, 'https://example.com/callback');
        $this->paymentService->initiate($order, 'https://example.com/callback');

        $this->assertSame(2, $order->paymentAttempts()->count());
    }

    // ── Verify tests ────────────────────────────────────────────

    public function test_unknown_authority_rejects_verify(): void
    {
        $user = $this->customer();
        $order = $this->order($user);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not registered for order');
        $this->paymentService->verify($order, 'unknown-auth');
    }

    public function test_authority_for_different_order_rejects_verify(): void
    {
        $user = $this->customer();
        $order1 = $this->order($user);
        $order2 = $this->order($user);

        $this->registerAttempt($order1, 'auth-for-order-1');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not registered for order');
        $this->paymentService->verify($order2, 'auth-for-order-1');
    }

    public function test_successful_verify_marks_attempt_verified_with_reference(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $this->registerAttempt($order, 'auth-success');

        $result = $this->paymentService->verify($order, 'auth-success');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->reference);

        $attempt = $order->paymentAttempts()->where('authority', 'auth-success')->first();
        $this->assertSame(PaymentAttempt::STATUS_VERIFIED, $attempt->status);
        $this->assertSame('ref-auth-success', $attempt->reference);
        $this->assertNotNull($attempt->verified_at);
    }

    public function test_successful_verify_confirms_order_and_decrements_stock(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $this->registerAttempt($order, 'auth-confirm');

        $result = $this->paymentService->verify($order, 'auth-confirm');

        $this->assertTrue($result->success);

        $order = $order->fresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('online', $order->payment_method);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_failed_verify_attempt_failed_order_pending(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $this->registerAttempt($order, 'auth-fail');

        $this->fakeGateway->failVerify();
        $result = $this->paymentService->verify($order, 'auth-fail');

        $this->assertFalse($result->success);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(10, $product->fresh()->stock);

        $attempt = $order->paymentAttempts()->where('authority', 'auth-fail')->first();
        $this->assertSame(PaymentAttempt::STATUS_FAILED, $attempt->status);
    }

    public function test_retry_after_failure_creates_new_attempt(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 2,
            'unit_price' => $product->price, 'subtotal' => '1998.00',
        ]);

        $this->registerAttempt($order, 'auth-retry-1');
        $this->fakeGateway->failVerify();
        $this->paymentService->verify($order, 'auth-retry-1');
        $this->assertSame('pending', $order->fresh()->status);

        $attempt1 = $order->paymentAttempts()->where('authority', 'auth-retry-1')->first();
        $this->assertSame(PaymentAttempt::STATUS_FAILED, $attempt1->status);

        $this->fakeGateway->reset();
        $this->registerAttempt($order, 'auth-retry-2');
        $result = $this->paymentService->verify($order, 'auth-retry-2');

        $this->assertTrue($result->success);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);

        $attempt2 = $order->paymentAttempts()->where('authority', 'auth-retry-2')->first();
        $this->assertSame(PaymentAttempt::STATUS_VERIFIED, $attempt2->status);
        $this->assertSame(PaymentAttempt::STATUS_FAILED, $attempt1->fresh()->status);
    }

    public function test_verify_already_verified_attempt_rejects(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $this->registerAttempt($order, 'auth-double');

        $this->paymentService->verify($order, 'auth-double');
        $this->assertSame(7, $product->fresh()->stock);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has already been verified');
        $this->paymentService->verify($order, 'auth-double');

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_amount_mismatch_rejects_verify(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user, ['total' => '500.00']);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        // Order total = 500.00 Toman → 5000 Rial
        // Register attempt with wrong amount (999 instead of 5000)
        $this->registerAttempt($order, 'auth-mismatch', amount: 999);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not match order total');
        $this->paymentService->verify($order, 'auth-mismatch');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_insufficient_stock_does_not_confirm_order(): void
    {
        $product = $this->product(['stock' => 2]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 5,
            'unit_price' => $product->price, 'subtotal' => '4995.00',
        ]);

        $this->registerAttempt($order, 'auth-stock');

        try {
            $this->paymentService->verify($order, 'auth-stock');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->stock);

        $attempt = $order->paymentAttempts()->where('authority', 'auth-stock')->first();
        $this->assertNotSame(PaymentAttempt::STATUS_VERIFIED, $attempt->status);
    }

    public function test_gateway_exception_on_verify_attempt_not_failed(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $this->registerAttempt($order, 'auth-exception');
        $this->fakeGateway->throwOnVerify();

        try {
            $this->paymentService->verify($order, 'auth-exception');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unavailable', $e->getMessage());
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);

        $attempt = $order->paymentAttempts()->where('authority', 'auth-exception')->first();
        $this->assertSame(PaymentAttempt::STATUS_INITIATED, $attempt->status);
    }

    public function test_stock_only_decrements_once_on_success(): void
    {
        $product = $this->product(['stock' => 10]);
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'sku' => $product->sku, 'quantity' => 3,
            'unit_price' => $product->price, 'subtotal' => '2997.00',
        ]);

        $this->registerAttempt($order, 'auth-once');
        $this->paymentService->verify($order, 'auth-once');
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->expectException(\LogicException::class);
        $this->paymentService->verify($order, 'auth-once');
    }

    public function test_confirmed_order_cannot_initiate_payment_again(): void
    {
        $user = $this->customer();
        $order = $this->order($user);
        $order->items()->create([
            'product_id' => $this->product()->id, 'product_name' => 'P',
            'sku' => 'SKU', 'quantity' => 1, 'unit_price' => '100.00', 'subtotal' => '100.00',
        ]);

        $this->registerAttempt($order, 'auth-confirm-retry');
        $this->paymentService->verify($order, 'auth-confirm-retry');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only pending orders can initiate payment');
        $this->paymentService->initiate($order, 'https://example.com/callback');
    }
}
