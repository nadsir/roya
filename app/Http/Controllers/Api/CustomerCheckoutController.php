<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_email' => ['required', 'email', 'max:255'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_province' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $lines = [];
            foreach ($data['items'] as $index => $item) {
                $key = (int) $item['product_id'].':'.(int) ($item['variant_id'] ?? 0);
                if (!isset($lines[$key])) {
                    $lines[$key] = [
                        'product_id' => (int) $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'quantity' => 0,
                        'index' => $index,
                    ];
                }
                $lines[$key]['quantity'] += (int) $item['quantity'];
            }

            $items = [];
            $total = 0;
            $maxAmount = 999999999999999; // DECIMAL(15, 2), in hundredths.
            foreach ($lines as $line) {
                $field = 'items.'.$line['index'];
                $product = Product::query()->where('id', $line['product_id'])->lockForUpdate()->first();
                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([$field.'.product_id' => 'محصول موجود یا فعال نیست.']);
                }

                $variant = null;
                if ($line['variant_id'] !== null) {
                    $variant = $product->variants()->with('attributeValues.attribute')->where('id', $line['variant_id'])->lockForUpdate()->first();
                    if (!$variant || !$variant->is_active) {
                        throw ValidationException::withMessages([$field.'.variant_id' => 'تنوع انتخاب‌شده معتبر یا فعال نیست.']);
                    }
                }

                $stock = $variant ? $variant->stock : $product->stock;
                if ($line['quantity'] > $stock) {
                    throw ValidationException::withMessages([$field.'.quantity' => 'موجودی محصول برای تعداد درخواستی کافی نیست.']);
                }

                // Use decimal strings from DB, never client prices or float arithmetic.
                $price = $variant?->price ?? $product->price;
                if (str_starts_with($price, '-')) {
                    throw ValidationException::withMessages([$field.'.product_id' => 'قیمت محصول معتبر نیست.']);
                }
                [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '00');
                $unitPrice = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
                if ($unitPrice * $line['quantity'] > $maxAmount - $total) {
                    throw ValidationException::withMessages(['items' => 'مبلغ سفارش از سقف مجاز بیشتر است.']);
                }
                $subtotal = $unitPrice * $line['quantity'];
                $total += $subtotal;

                $items[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $this->decimal($unitPrice),
                    'subtotal' => $this->decimal($subtotal),
                    'attributes' => $variant ? $variant->attributeValues->map(fn ($value) => [
                        'attribute' => $value->attribute->name,
                        'slug' => $value->attribute->slug,
                        'label' => $value->label,
                        'value' => $value->value,
                    ])->all() : null,
                ];
            }

            unset($data['items']);
            $order = $request->user()->orders()->create(array_merge($data, [
                'status' => 'pending',
                'subtotal' => $this->decimal($total),
                'discount' => '0.00',
                'shipping_cost' => '0.00',
                'total' => $this->decimal($total),
            ]));
            $order->items()->createMany($items);

            return $order->load('items');
        });

        return response()->json([
            'message' => 'سفارش شما با موفقیت ثبت شد.',
            'order' => $order,
        ], 201);
    }

    private function decimal(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
