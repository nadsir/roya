<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class AghayePardakhtGateway implements PaymentGatewayInterface
{
    private string $pin;
    private string $createUrl;
    private string $verifyUrl;
    private string $startPayUrl;
    private ?string $caBundle;

    public function __construct()
    {
        $this->pin = config('services.aghayepardakht.pin', '');
        $this->createUrl = config('services.aghayepardakht.create_url', 'https://panel.aqayepardakht.ir/api/v2/create');
        $this->verifyUrl = config('services.aghayepardakht.verify_url', 'https://panel.aqayepardakht.ir/api/v2/verify');
        $this->startPayUrl = config('services.aghayepardakht.startpay_url', 'https://panel.aqayepardakht.ir/startpay');
        $this->caBundle = config('services.aghayepardakht.ca_bundle', '') ?: null;
    }

    private function withCaOptions(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout(10);
        if ($this->caBundle) {
            $request = $request->withOptions(['verify' => $this->caBundle]);
        }
        return $request;
    }

    public function initiate(int $amount, string $orderId, string $callbackUrl): PaymentResult
    {
        $response = $this->withCaOptions()->post($this->createUrl, [
            'pin' => $this->pin,
            'amount' => $amount,
            'callback' => $callbackUrl,
            'callback_method' => 'GET',
            'invoice_id' => $orderId,
        ]);

        if ($response->failed()) {
            return PaymentResult::failed(
                'AghayePardakht create request failed: ' . $response->body()
            );
        }

        $body = $response->json();

        if (($body['status'] ?? '') !== 'success') {
            return PaymentResult::failed(
                $body['message'] ?? 'AghayePardakht create returned non-success status.'
            );
        }

        $transId = $body['transid'] ?? null;

        if (!$transId) {
            return PaymentResult::failed('AghayePardakht create returned no transid.');
        }

        return PaymentResult::success(
            authority: $transId,
            paymentUrl: "{$this->startPayUrl}/{$transId}",
        );
    }

    public function verify(string $authority, int $amount): PaymentVerificationResult
    {
        $response = $this->withCaOptions()->post($this->verifyUrl, [
            'pin' => $this->pin,
            'amount' => $amount,
            'transid' => $authority,
        ]);

        if ($response->failed()) {
            return PaymentVerificationResult::failed(
                'AghayePardakht verify request failed: ' . $response->body()
            );
        }

        $body = $response->json();
        $code = $body['code'] ?? '';

        if (($body['status'] ?? '') === 'success' && $code === '1') {
            return PaymentVerificationResult::success(
                reference: $authority,
                authority: $authority,
            );
        }

        if ($code === '2') {
            return PaymentVerificationResult::success(
                reference: $authority,
                authority: $authority,
            );
        }

        return PaymentVerificationResult::failed(
            $body['message'] ?? "AghayePardakht verify failed (code: {$code})."
        );
    }
}
