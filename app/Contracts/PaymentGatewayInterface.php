<?php

namespace App\Contracts;

use App\Services\Payment\PaymentResult;
use App\Services\Payment\PaymentVerificationResult;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment request to the gateway.
     *
     * @param  int     $amount  Amount in Toman
     * @param  string  $orderId Internal order ID
     * @param  string  $callbackUrl  Gateway return URL
     * @return PaymentResult
     */
    public function initiate(int $amount, string $orderId, string $callbackUrl): PaymentResult;

    /**
     * Verify a payment after user returns from gateway.
     *
     * @param  string  $authority  Transaction authority/reference from gateway
     * @param  int     $amount     Expected amount in Toman
     * @return PaymentVerificationResult
     */
    public function verify(string $authority, int $amount): PaymentVerificationResult;
}
