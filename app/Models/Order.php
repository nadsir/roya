<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'subtotal', 'discount', 'shipping_cost', 'total',
        'customer_name', 'customer_phone', 'customer_email', 'shipping_address',
        'shipping_postal_code', 'shipping_city', 'shipping_province', 'notes',
        'paid_at', 'payment_method', 'payment_ref',
        'cancelled_at', 'cancelled_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── Allowed transitions ─────────────────────────────────────

    private const ALLOWED_TRANSITIONS = [
        'pending'    => ['confirmed', 'cancelled'],
        'confirmed'  => ['processing', 'cancelled'],
        'processing' => ['shipped'],
        'shipped'    => ['delivered'],
        'delivered'  => [],
        'cancelled'  => [],
    ];

    // ── Relationships ───────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    // ── Status helpers ──────────────────────────────────────────

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    protected function transitionTo(string $status): void
    {
        if (!$this->canTransitionTo($status)) {
            throw new \LogicException(
                "Cannot transition order from [{$this->status}] to [{$status}]."
            );
        }
        $this->update(['status' => $status]);
    }

    public function markConfirmed(): void
    {
        $this->transitionTo('confirmed');
    }

    public function markProcessing(): void
    {
        $this->transitionTo('processing');
    }

    public function markShipped(): void
    {
        $this->transitionTo('shipped');
    }

    public function markDelivered(): void
    {
        $this->transitionTo('delivered');
    }

    // ── Payment ─────────────────────────────────────────────────

    /**
     * Mark order as paid and decrement stock atomically.
     *
     * This is the primary entry point for the Payment Gateway.
     * Both payment status and stock decrement succeed or fail together.
     */
    public function markPaidAndDecrementStock(string $method = 'online', ?string $ref = null): void
    {
        DB::transaction(function () use ($method, $ref) {
            if ($this->status !== 'pending') {
                throw new \LogicException(
                    "Only pending orders can be marked as paid, current status [{$this->status}]."
                );
            }

            $this->update([
                'status'         => 'confirmed',
                'paid_at'        => now(),
                'payment_method' => $method,
                'payment_ref'    => $ref,
            ]);

            self::doDecrementStock($this);
        });
    }

    // ── Cancellation ────────────────────────────────────────────

    public function cancel(?string $reason = null): void
    {
        if (!$this->canTransitionTo('cancelled')) {
            throw new \LogicException(
                "Order with status [{$this->status}] cannot be cancelled."
            );
        }

        $this->update([
            'status'            => 'cancelled',
            'cancelled_at'      => now(),
            'cancelled_reason'  => $reason,
        ]);
    }

    // ── Inventory ───────────────────────────────────────────────

    /**
     * Atomically decrement stock for all items in an order.
     *
     * Wraps the operation in its own transaction with lockForUpdate.
     */
    public static function decrementStockForOrder(self $order): void
    {
        DB::transaction(fn () => self::doDecrementStock($order));
    }

    /**
     * Core stock decrement logic. Must be called inside a DB transaction.
     */
    private static function doDecrementStock(self $order): void
    {
        if ($order->status !== 'confirmed') {
            throw new \LogicException(
                "Stock can only be decremented for confirmed orders, current status [{$order->status}]."
            );
        }

        $items = $order->items()->get();

        foreach ($items as $item) {
            if ($item->product_variant_id) {
                $variant = ProductVariant::query()
                    ->where('id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (!$variant || $variant->stock < $item->quantity) {
                    throw new \RuntimeException(
                        "Insufficient stock for variant [{$item->product_variant_id}] " .
                        "(available: " . ($variant->stock ?? 0) . ", required: {$item->quantity})."
                    );
                }

                $variant->decrement('stock', $item->quantity);
            } else {
                $product = Product::query()
                    ->where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->stock < $item->quantity) {
                    throw new \RuntimeException(
                        "Insufficient stock for product [{$item->product_id}] " .
                        "(available: " . ($product->stock ?? 0) . ", required: {$item->quantity})."
                    );
                }

                $product->decrement('stock', $item->quantity);
            }
        }
    }
}
