<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Payment\PaymentResult;
use App\Services\Payment\PaymentVerificationResult;

class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Initiate payment for an order.
     *
     * Only pending orders can start payment.
     * Creates a new PaymentAttempt for each initiate call.
     */
    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        if ($order->status !== 'pending') {
            throw new \LogicException(
                "Only pending orders can initiate payment, current status [{$order->status}]."
            );
        }

        $amount = (int) round((float) $order->total);

        $attempt = $order->paymentAttempts()->create([
            'gateway' => class_basename($this->gateway),
            'amount'  => $amount,
            'status'  => PaymentAttempt::STATUS_PENDING,
        ]);

        try {
            $result = $this->gateway->initiate(
                amount: $amount,
                orderId: (string) $order->id,
                callbackUrl: $callbackUrl,
            );
        } catch (\Throwable $e) {
            $attempt->update(['status' => PaymentAttempt::STATUS_PENDING]);
            throw $e;
        }

        if ($result->success) {
            $attempt->update([
                'authority' => $result->authority,
                'status'    => PaymentAttempt::STATUS_INITIATED,
            ]);
        } else {
            $attempt->update(['status' => PaymentAttempt::STATUS_FAILED]);
        }

        return $result;
    }

    /**
     * Verify payment and complete the order on success.
     *
     * Authority must belong to a registered attempt for this order.
     * Attempt amount must match current order total.
     */
    public function verify(Order $order, string $authority): PaymentVerificationResult
    {
        $attempt = $order->paymentAttempts()
            ->where('authority', $authority)
            ->first();

        if (!$attempt) {
            throw new \LogicException(
                "Payment authority [{$authority}] is not registered for order [{$order->id}]."
            );
        }

        if ($attempt->status === PaymentAttempt::STATUS_VERIFIED) {
            throw new \LogicException(
                "Payment attempt [{$attempt->id}] has already been verified."
            );
        }

        $orderTotal = (int) round((float) $order->total);
        if ($attempt->amount !== $orderTotal) {
            throw new \LogicException(
                "Attempt amount [{$attempt->amount}] does not match order total [{$orderTotal}]."
            );
        }

        $result = $this->gateway->verify(
            authority: $authority,
            amount: $attempt->amount,
        );

        if ($result->success) {
            $order->markPaidAndDecrementStock(
                method: 'online',
                ref: $result->reference,
            );

            $attempt->update([
                'status'      => PaymentAttempt::STATUS_VERIFIED,
                'reference'   => $result->reference,
                'verified_at' => now(),
            ]);
        } else {
            $attempt->update(['status' => PaymentAttempt::STATUS_FAILED]);
        }

        return $result;
    }
}
