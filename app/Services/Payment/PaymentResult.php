<?php

namespace App\Services\Payment;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $authority = null,
        public readonly ?string $paymentUrl = null,
        public readonly string $message = '',
    ) {}

    public static function success(string $authority, string $paymentUrl): self
    {
        return new self(success: true, authority: $authority, paymentUrl: $paymentUrl);
    }

    public static function failed(string $message = ''): self
    {
        return new self(success: false, message: $message);
    }
}
