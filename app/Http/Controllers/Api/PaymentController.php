<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    /**
     * Initiate payment for an order.
     *
     * POST /api/customer/orders/{order}/pay
     */
    public function initiate(Request $request, string $order): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($order);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'این سفارش قبلاً پردازش شده است.',
            ], 422);
        }

        $callbackUrl = route('payment.callback', [
            'order' => $order->id,
        ]);

        try {
            $result = $this->paymentService->initiate($order, $callbackUrl);
        } catch (\Throwable $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'اتصال به درگاه پرداخت انجام نشد. لطفاً دوباره تلاش کنید.',
            ], 502);
        }

        if (!$result->success) {
            return response()->json([
                'message' => $result->message ?: 'پردازش پرداخت انجام نشد.',
            ], 422);
        }

        return response()->json([
            'payment_url' => $result->paymentUrl,
            'authority' => $result->authority,
        ]);
    }

    /**
     * Handle the payment gateway callback redirect.
     *
     * GET /payment/callback/{order}
     *
     * Idempotent:
     *   code 1 → mark paid once (no duplicate effects)
     *   code 2 → already verified (treat as success, no duplicate effects)
     *   other  → mark failed
     */
    public function callback(Request $request, string $order): RedirectResponse
    {
        $authority = $request->query('transid', $request->query('authority', ''));
        $status = $request->query('status', '');
        $bank = $request->query('bank', '');
        $trackingNumber = $request->query('tracking_number', '');
        $cardNumber = $request->query('cardnumber', '');

        Log::info('Payment callback received', [
            'order_id' => $order,
            'transid' => $authority,
            'status' => $status,
            'bank' => $bank,
        ]);

        if (!$authority) {
            return redirect('/orders/' . $order . '?payment=failed&reason=no_authority');
        }

        $orderModel = Order::find($order);

        if (!$orderModel) {
            return redirect('/orders/' . $order . '?payment=failed&reason=order_not_found');
        }

        $result = $this->paymentService->verify($orderModel, $authority);

        if ($result->success) {
            return redirect('/orders/' . $order . '?payment=success');
        }

        Log.warning('Payment verification failed via callback', [
            'order_id' => $order,
            'transid' => $authority,
            'message' => $result->message,
        ]);

        return redirect('/orders/' . $order . '?payment=failed&reason=verification_failed');
    }

    /**
     * Check payment status for an order.
     *
     * GET /api/customer/orders/{order}/payment-status
     */
    public function status(Request $request, string $order): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($order);

        $latestAttempt = $order->paymentAttempts()
            ->latest('created_at')
            ->first();

        return response()->json([
            'order_status' => $order->status,
            'paid_at' => $order->paid_at,
            'payment_method' => $order->payment_method,
            'payment_ref' => $order->payment_ref,
            'latest_attempt' => $latestAttempt ? [
                'status' => $latestAttempt->status,
                'authority' => $latestAttempt->authority,
                'reference' => $latestAttempt->reference,
                'created_at' => $latestAttempt->created_at,
            ] : null,
        ]);
    }
}
