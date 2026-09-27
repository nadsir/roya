<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['user:id,name,email', 'items'])
            ->withCount('items')
            ->latest('created_at')->latest('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(20)->through(function ($order) {
            return [
                'id' => $order->id,
                'status' => $order->status,
                'total' => $order->total,
                'paid_at' => $order->paid_at,
                'payment_method' => $order->payment_method,
                'payment_ref' => $order->payment_ref,
                'created_at' => $order->created_at,
                'customer' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
                'items_count' => $order->items_count,
            ];
        });

        return response()->json($orders);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        $order = Order::query()
            ->with(['user:id,name,email', 'items.product.images', 'paymentAttempts'])
            ->findOrFail($order);

        $data = $order->only([
            'id', 'status', 'created_at', 'customer_name', 'customer_phone',
            'customer_email', 'shipping_address', 'shipping_postal_code',
            'shipping_city', 'shipping_province', 'notes', 'subtotal',
            'discount', 'shipping_cost', 'total', 'paid_at',
            'payment_method', 'payment_ref',
        ]);

        $data['user'] = $order->user ? [
            'id' => $order->user->id,
            'name' => $order->user->name,
            'email' => $order->user->email,
        ] : null;

        $data['items'] = $order->items->map(function ($item) {
            $images = $item->product?->images;
            $image = $images?->firstWhere('is_primary', true) ?? $images?->first();

            return array_merge($item->only([
                'id', 'product_id', 'product_variant_id', 'product_name', 'sku',
                'quantity', 'unit_price', 'subtotal', 'attributes',
            ]), ['image' => $image?->path]);
        })->values();

        $data['payment_attempts'] = $order->paymentAttempts
            ->sortByDesc('created_at')
            ->map(function ($attempt) {
                return $attempt->only([
                    'id', 'gateway', 'authority', 'amount', 'status',
                    'reference', 'verified_at', 'created_at',
                ]);
            })->values();

        return response()->json(['order' => $data]);
    }

    public function updateStatus(Request $request, string $order): JsonResponse
    {
        $order = Order::findOrFail($order);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'cancelled_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = $validated['status'];

        if (!$order->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "سفارش نمی‌تواند از [{$order->status}] به [{$newStatus}] تغییر وضعیت دهد.",
            ]);
        }

        if ($newStatus === 'cancelled') {
            $order->cancel($validated['cancelled_reason'] ?? null);
        } else {
            $method = 'mark' . ucfirst($newStatus);
            if (method_exists($order, $method)) {
                $order->$method();
            } else {
                $order->transitionTo($newStatus);
            }
        }

        return response()->json([
            'message' => 'وضعیت سفارش با موفقیت به‌روزرسانی شد.',
            'order' => [
                'id' => $order->fresh()->id,
                'status' => $order->fresh()->status,
            ],
        ]);
    }
}