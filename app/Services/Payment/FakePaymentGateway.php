<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;

class FakePaymentGateway implements PaymentGatewayInterface
{
    private bool $shouldFailInitiate = false;
    private bool $shouldFailVerify = false;
    private bool $shouldThrowOnInitiate = false;
    private bool $shouldThrowOnVerify = false;
    private ?string $customAuthority = null;

    public function initiate(int $amount, string $orderId, string $callbackUrl): PaymentResult
    {
        if ($this->shouldThrowOnInitiate) {
            throw new \RuntimeException('Gateway connection failed.');
        }

        if ($this->shouldFailInitiate) {
            return PaymentResult::failed('Gateway rejected the payment request.');
        }

        $authority = $this->customAuthority ?? 'fake-auth-' . $orderId;

        return PaymentResult::success(
            authority: $authority,
            paymentUrl: "https://fake-gateway.test/pay/{$authority}",
        );
    }

    public function verify(string $authority, int $amount): PaymentVerificationResult
    {
        if ($this->shouldThrowOnVerify) {
            throw new \RuntimeException('Gateway verification service unavailable.');
        }

        if ($this->shouldFailVerify) {
            return PaymentVerificationResult::failed('Payment not verified by gateway.');
        }

        return PaymentVerificationResult::success(
            reference: 'ref-' . $authority,
            authority: $authority,
        );
    }

    // ── Test helpers ────────────────────────────────────────────

    public function failInitiate(): void
    {
        $this->shouldFailInitiate = true;
    }

    public function failVerify(): void
    {
        $this->shouldFailVerify = true;
    }

    public function throwOnInitiate(): void
    {
        $this->shouldThrowOnInitiate = true;
    }

    public function throwOnVerify(): void
    {
        $this->shouldThrowOnVerify = true;
    }

    public function setAuthority(string $authority): void
    {
        $this->customAuthority = $authority;
    }

    public function reset(): void
    {
        $this->shouldFailInitiate = false;
        $this->shouldFailVerify = false;
        $this->shouldThrowOnInitiate = false;
        $this->shouldThrowOnVerify = false;
        $this->customAuthority = null;
    }
}
