<?php

namespace App\Services\Payment;

class PaymentVerificationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $reference = null,
        public readonly ?string $authority = null,
        public readonly string $message = '',
    ) {}

    public static function success(string $reference, string $authority): self
    {
        return new self(success: true, reference: $reference, authority: $authority);
    }

    public static function failed(string $message = ''): self
    {
        return new self(success: false, message: $message);
    }
}
