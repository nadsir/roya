<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()
            ->select(['id', 'status', 'created_at', 'total'])
            ->withCount('items')
            ->latest('created_at')->latest('id')
            ->paginate(20);

        return response()->json($orders);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        // Scope the lookup before resolving the ID, including all eager loads.
        $order = $request->user()->orders()
            ->with('items.product.images')
            ->findOrFail($order);

        $data = $order->only([
            'id', 'status', 'created_at', 'customer_name', 'customer_phone',
            'customer_email', 'shipping_address', 'shipping_postal_code',
            'shipping_city', 'shipping_province', 'notes', 'subtotal',
            'discount', 'shipping_cost', 'total',
            'paid_at', 'payment_method', 'payment_ref',
            'cancelled_at', 'cancelled_reason',
        ]);
        $data['items'] = $order->items->map(function ($item) {
            $images = $item->product?->images;
            $image = $images?->firstWhere('is_primary', true) ?? $images?->first();

            return array_merge($item->only([
                'id', 'product_id', 'product_variant_id', 'product_name', 'sku',
                'quantity', 'unit_price', 'subtotal', 'attributes',
            ]), ['image' => $image?->path]);
        })->values();

        return response()->json(['order' => $data]);
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($order);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $order->cancel($data['reason'] ?? null);
        } catch (\LogicException $e) {
            return response()->json([
                'message' => 'این سفارش قابل لغو نیست.',
            ], 409);
        }

        return response()->json([
            'message' => 'سفارش شما لغو شد.',
            'order' => $order->fresh(),
        ]);
    }
}
