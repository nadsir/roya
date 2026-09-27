<?php

namespace App\Contracts;

interface SmsServiceInterface
{
    public function sendOtp(string $mobile, string $code): void;
}
