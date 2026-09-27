<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Log;

class LogSmsService implements SmsServiceInterface
{
    public function sendOtp(string $mobile, string $code): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Log SMS is restricted to local/testing environments.');
        }
        Log::debug('Development SMS OTP (expires in 2 minutes)', ['mobile' => $mobile, 'code' => $code]);
    }
}
