<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Illuminate\Support\Facades\Http;

class HttpSmsService implements SmsServiceInterface
{
    public function sendOtp(string $mobile, string $code): void
    {
        $url = config('sms.endpoint');
        $apiKey = config('sms.token');
        $templateId = config('sms.template_id');

        if (
            !$url ||
            !str_starts_with($url, 'https://') ||
            !$apiKey ||
            !$templateId
        ) {
            throw new \RuntimeException(
                'SMS.ir endpoint, API key and template ID must be configured.'
            );
        }

        Http::withHeaders([
            'X-API-KEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout(5)
            ->timeout(10)
            ->post($url, [
                'Mobile' => $mobile,
                'TemplateId' => (int) $templateId,
                'Parameters' => [
                    [
                        'Name' => 'CODE',
                        'Value' => $code,
                    ],
                ],
            ])
            ->throw();
    }
}