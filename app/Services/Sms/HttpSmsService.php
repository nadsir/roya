<?php

namespace App\Services\Sms;

use App\Contracts\SmsServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HttpSmsService implements SmsServiceInterface
{
    /**
     * Send an OTP through the SMS.ir REST API v1 verification endpoint.
     *
     * @see https://sms.ir/rest-api/  POST /v1/send/verify
     */
    public function sendOtp(string $mobile, string $code): void
    {
        $url = config('sms.endpoint');
        $apiKey = config('sms.token');
        $templateId = config('sms.template_id');
        $parameterName = config('sms.parameter_name') ?: 'CODE';

        if (
            !$url ||
            !str_starts_with($url, 'https://') ||
            !$apiKey ||
            !$templateId
        ) {
            throw new RuntimeException(
                'SMS.ir endpoint, API key and template ID must be configured.'
            );
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout(5)
                ->timeout(10)
                ->post($url, [
                    'mobile' => $mobile,
                    'templateId' => (int) $templateId,
                    'parameters' => [
                        [
                            'name' => $parameterName,
                            'value' => $code,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->reportFailure($mobile, 'connection failed');

            throw new RuntimeException('SMS.ir could not be reached.', 0, $exception);
        } catch (Throwable $exception) {
            $this->reportFailure($mobile, 'unexpected transport failure');

            throw new RuntimeException('SMS.ir request could not be completed.', 0, $exception);
        }

        // SMS.ir answers HTTP 200 with `status: 0` when the request is understood but
        // refused (unapproved template, wrong parameter, no credit...). Treating any
        // 2xx as success would tell the user a code was sent when it was not.
        $body = $response->json();
        $status = is_array($body) ? ($body['status'] ?? null) : null;

        if ($response->failed() || !is_numeric($status) || (int) $status !== 1) {
            $this->reportFailure(
                $mobile,
                'HTTP '.$response->status().' with status '.var_export($status, true),
                is_array($body) ? $body : null
            );

            throw new RuntimeException('SMS.ir did not accept the message.');
        }
    }

    /**
     * Log enough to debug a failed send without ever persisting the API key or the OTP.
     */
    private function reportFailure(string $mobile, string $reason, ?array $body = null): void
    {
        Log::warning('SMS.ir OTP send failed.', [
            'mobile' => $this->maskMobile($mobile),
            'reason' => $reason,
            'provider_message' => isset($body['message']) && is_string($body['message'])
                ? mb_substr($body['message'], 0, 200)
                : null,
            'template_id' => config('sms.template_id'),
        ]);
    }

    private function maskMobile(string $mobile): string
    {
        return strlen($mobile) > 6
            ? substr($mobile, 0, 4).'***'.substr($mobile, -3)
            : '***';
    }
}